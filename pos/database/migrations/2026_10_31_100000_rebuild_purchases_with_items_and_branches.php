<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Purchases as related tables, per branch:
 * - purchases: id key, linked to the supplier (contacts) and the branch by id, with subtotal, discount, freight, tax.
 * - purchase_items: the lines of a purchase.
 * - purchase_payments: money paid to the supplier, from a payment account.
 * - purchase_returns and purchase_return_items: goods sent back.
 * The old rows are copied: supplier and branch are found by name.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('purchases', 'purchases_old');
        Schema::rename('purchase_returns', 'purchase_returns_old');

        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->string('ref', 40)->unique();             // PO-2043
            $table->date('date');
            $table->foreignId('supplier_id')->constrained('contacts')->restrictOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete(); // stock goes in here
            $table->string('status', 20)->default('ordered');         // ordered, pending, received
            $table->string('payment_status', 20)->default('due');     // paid, partial, due
            $table->decimal('subtotal', 12, 2)->default(0);           // lines after line discounts
            $table->decimal('order_discount', 12, 2)->default(0);
            $table->decimal('freight', 12, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);            // % on the day of the purchase
            $table->decimal('tax', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('paid', 12, 2)->default(0);
            $table->decimal('credit_applied', 12, 2)->default(0);     // taken off by returns
            $table->decimal('due', 12, 2)->default(0);
            $table->date('due_date')->nullable();
            $table->text('notes')->nullable();
            $table->string('invoice_file')->nullable();               // file in storage/app/public/purchases
            $table->string('created_by')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->string('received_by')->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained('purchases')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->string('name', 190);                      // kept even if the product is deleted later
            $table->string('unit', 20)->nullable();
            $table->decimal('qty', 12, 3);
            $table->decimal('cost', 12, 2);                   // unit cost ex tax
            $table->decimal('discount', 5, 2)->default(0);    // %
            $table->decimal('line_total', 12, 2)->default(0);
            $table->decimal('stock_after', 12, 3)->nullable(); // stock at the branch after receiving
            $table->timestamps();
        });

        Schema::create('purchase_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained('purchases')->cascadeOnDelete();
            $table->string('payment_account_id')->nullable();
            $table->foreign('payment_account_id')->references('id')->on('payment_accounts')->nullOnDelete();
            $table->string('account_name')->nullable();       // kept even if the account is deleted later
            $table->decimal('amount', 12, 2);
            $table->date('date');
            $table->string('note')->nullable();
            $table->string('user_name')->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_returns', function (Blueprint $table) {
            $table->id();
            $table->string('ref', 40)->unique();              // PR-0019
            $table->date('date');
            $table->foreignId('purchase_id')->nullable()->constrained('purchases')->nullOnDelete();
            $table->string('purchase_ref', 40);               // PO-2041, kept as text as well: some old returns have no purchase row
            $table->foreignId('supplier_id')->constrained('contacts')->restrictOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete(); // stock comes out of here
            $table->string('settlement', 30);                 // Reduced owed, Refund, Supplier credit
            $table->string('status', 20)->default('Credited'); // Credited, Awaiting refund
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax', 12, 2)->default(0);
            $table->decimal('credit', 12, 2)->default(0);     // subtotal + tax
            $table->text('notes')->nullable();
            $table->string('user_name')->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_return_id')->constrained('purchase_returns')->cascadeOnDelete();
            $table->foreignId('purchase_item_id')->constrained('purchase_items')->cascadeOnDelete();
            $table->string('name', 190);
            $table->decimal('qty', 12, 3);
            $table->decimal('cost', 12, 2);                   // unit cost after the line discount
            $table->string('reason', 60);
            $table->decimal('credit', 12, 2)->default(0);
            $table->timestamps();
        });

        // Copy the old purchases.
        foreach (DB::table('purchases_old')->orderBy('created_at')->get() as $old) {
            $paid = $old->payment_status === 'paid' ? $old->total : ($old->payment_status === 'partial' ? $old->total - $old->due : 0);
            $subtotal = round($old->total / 1.1, 2);

            DB::table('purchases')->insert([
                'ref' => $old->ref,
                'date' => $old->date,
                'supplier_id' => $this->supplierId($old->supplier),
                'branch_id' => $this->branchId($old->location),
                'status' => $old->status,
                'payment_status' => $old->payment_status,
                'subtotal' => $subtotal,
                'tax_rate' => 10,
                'tax' => round($old->total - $subtotal, 2),
                'total' => $old->total,
                'paid' => $paid,
                'due' => $old->total - $paid,
                'notes' => $old->notes,
                'received_at' => $old->status === 'received' ? $old->created_at : null,
                'created_at' => $old->created_at,
                'updated_at' => $old->updated_at,
            ]);
        }

        // Copy the old returns. A return whose purchase is not in the list keeps its reference as text.
        foreach (DB::table('purchase_returns_old')->orderBy('created_at')->get() as $old) {
            $purchase = DB::table('purchases')->where('ref', $old->purchase_ref)->first();
            $subtotal = round($old->credit / 1.1, 2);

            DB::table('purchase_returns')->insert([
                'ref' => $old->ref,
                'date' => $old->date,
                'purchase_id' => $purchase ? $purchase->id : null,
                'purchase_ref' => $old->purchase_ref,
                'supplier_id' => $this->supplierId($old->supplier),
                'branch_id' => $purchase ? $purchase->branch_id : $this->branchId(null),
                'settlement' => $old->settlement,
                'status' => $old->status,
                'subtotal' => $subtotal,
                'tax' => round($old->credit - $subtotal, 2),
                'credit' => $old->credit,
                'notes' => $old->reason,
                'created_at' => $old->created_at,
                'updated_at' => $old->updated_at,
            ]);
        }

        Schema::drop('purchases_old');
        Schema::drop('purchase_returns_old');
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_return_items');
        Schema::dropIfExists('purchase_returns');
        Schema::dropIfExists('purchase_payments');
        Schema::dropIfExists('purchase_items');
        Schema::dropIfExists('purchases');

        // The old shape, empty. Restore rows from a backup if they are needed.
        Schema::create('purchases', function (Blueprint $table) {
            $table->string('ref')->primary();
            $table->date('date');
            $table->string('supplier');
            $table->string('location')->nullable();
            $table->string('status')->default('ordered');
            $table->string('payment_status')->default('due');
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('due', 12, 2)->default(0);
            $table->integer('line_count')->default(0);
            $table->json('lines')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
        Schema::create('purchase_returns', function (Blueprint $table) {
            $table->string('ref')->primary();
            $table->date('date');
            $table->string('purchase_ref');
            $table->string('supplier');
            $table->string('reason')->nullable();
            $table->string('settlement')->nullable();
            $table->decimal('credit', 12, 2)->default(0);
            $table->string('status')->default('Credited');
            $table->json('lines')->nullable();
            $table->timestamps();
        });
    }

    /** The contact with this name; a supplier that is not in Contacts yet is added. */
    private function supplierId(string $name): int
    {
        $id = DB::table('contacts')->where('name', $name)->value('id');
        if ($id) {
            return $id;
        }

        return DB::table('contacts')->insertGetId([
            'name' => $name, 'type' => 'Supplier', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** The branch with this name, or the first branch if it was renamed. The old "Warehouse" is the Cold store. */
    private function branchId(?string $name): int
    {
        if ($name === 'Warehouse') {
            $name = 'Cold store';
        }

        return DB::table('branches')->where('name', $name)->value('id')
            ?? DB::table('branches')->orderBy('id')->value('id');
    }
};
