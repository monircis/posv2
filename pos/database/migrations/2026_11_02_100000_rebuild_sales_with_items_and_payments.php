<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sales as related tables:
 * - sales: id key, linked to the branch and the customer.
 * - sale_items: the lines, linked to the real products.
 * - sale_payments: the money taken, linked to the payment account.
 * The old rows are copied. They have no lines and no stock or balance changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('sales', 'sales_old');

        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('ref', 40)->unique();                  // 2026-5015
            $table->dateTime('sold_at');
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->restrictOnDelete(); // null = walk in
            $table->string('customer_name');
            $table->string('channel', 20)->default('POS');        // POS, Online, Order
            $table->string('order_type', 20)->nullable();         // Dine in, Takeaway, Delivery
            $table->unsignedSmallInteger('table_no')->nullable();
            $table->unsignedInteger('item_count')->default(0);
            $table->decimal('subtotal', 12, 2)->default(0);       // the lines, after line discounts
            $table->decimal('group_percent', 5, 2)->default(0);   // the customer group's discount
            $table->decimal('group_discount', 12, 2)->default(0);
            $table->decimal('discount_percent', 5, 2)->default(0); // the order discount
            $table->decimal('discount', 12, 2)->default(0);
            $table->string('discount_approved_by')->nullable();
            $table->decimal('delivery_fee', 12, 2)->default(0);
            $table->decimal('rounding', 12, 2)->default(0);       // cash is rounded to 5c
            $table->decimal('gst', 12, 2)->default(0);            // included in the total
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('paid', 12, 2)->default(0);
            $table->decimal('due', 12, 2)->default(0);
            $table->date('due_date')->nullable();
            $table->decimal('tendered', 12, 2)->nullable();       // cash handed over
            $table->decimal('change_given', 12, 2)->default(0);
            $table->string('method', 40)->nullable();             // Cash, Card, Cash + Card, On account
            $table->string('status', 20)->default('paid');        // paid, partial, due, cancelled, draft, quote
            $table->string('created_by')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->string('cancelled_by')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->unsignedBigInteger('variant_id')->nullable();
            $table->string('name');
            $table->string('sku', 60)->nullable();
            $table->string('unit', 20)->nullable();
            $table->decimal('qty', 12, 3);
            $table->decimal('price', 12, 2);                      // includes tax
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->decimal('total', 12, 2);
            $table->string('note')->nullable();
            $table->boolean('stock_taken')->default(false);       // stock goes back if the sale is cancelled
            $table->timestamps();
        });

        Schema::create('sale_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->string('method', 40);                         // cash, card (old rows: as they were)
            $table->decimal('amount', 12, 2);
            $table->string('payment_account_id')->nullable();
            $table->foreign('payment_account_id')->references('id')->on('payment_accounts')->restrictOnDelete();
            $table->string('account_name')->nullable();
            $table->string('user_name')->nullable();
            $table->timestamps();
        });

        // "29/09 10:12" had no year; the demo sales are all from 2026.
        foreach (DB::table('sales_old')->orderBy('created_at')->orderBy('inv')->get() as $old) {
            $parts = preg_split('/[\/ ]/', $old->sold_at);
            $soldAt = count($parts) === 3 ? '2026-'.$parts[1].'-'.$parts[0].' '.$parts[2].':00' : $old->created_at;
            $status = $old->status === 'void' ? 'cancelled' : $old->status;
            $paid = in_array($status, ['paid', 'partial'], true) ? round($old->total - $old->due, 2) : 0;
            $method = $old->method === '—' ? null : $old->method;

            $id = DB::table('sales')->insertGetId([
                'ref' => $old->inv,
                'sold_at' => $soldAt,
                'branch_id' => DB::table('stock_settings')->value('default_branch_id') ?? DB::table('branches')->orderBy('id')->value('id'),
                'contact_id' => $old->customer === 'Walk in' ? null : DB::table('contacts')->where('name', $old->customer)->value('id'),
                'customer_name' => $old->customer,
                'channel' => $old->channel,
                'item_count' => $old->item_count,
                'subtotal' => $old->total,
                'total' => $old->total,
                'paid' => $paid,
                'due' => $old->due,
                'method' => $method,
                'status' => $status,
                'created_by' => $old->served_by,
                'created_at' => $old->created_at,
                'updated_at' => $old->updated_at,
            ]);

            if ($paid > 0) {
                DB::table('sale_payments')->insert([
                    'sale_id' => $id,
                    'method' => strtolower((string) $method),
                    'amount' => $paid,
                    'user_name' => $old->served_by,
                    'created_at' => $old->created_at,
                    'updated_at' => $old->updated_at,
                ]);
            }
        }

        Schema::drop('sales_old');
    }

    public function down(): void
    {
        Schema::drop('sale_payments');
        Schema::drop('sale_items');
        Schema::rename('sales', 'sales_new');

        Schema::create('sales', function (Blueprint $table) {
            $table->string('inv')->primary();
            $table->string('sold_at');
            $table->string('customer')->default('Walk in');
            $table->integer('item_count')->default(0);
            $table->string('channel')->default('POS');
            $table->string('method')->nullable();
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('due', 12, 2)->default(0);
            $table->string('status')->default('paid');
            $table->string('served_by')->nullable();
            $table->json('lines')->nullable();
            $table->timestamps();
        });

        foreach (DB::table('sales_new')->get() as $new) {
            DB::table('sales')->insert([
                'inv' => $new->ref,
                'sold_at' => date('d/m H:i', strtotime($new->sold_at)),
                'customer' => $new->customer_name,
                'item_count' => $new->item_count,
                'channel' => $new->channel,
                'method' => $new->method,
                'total' => $new->total,
                'due' => $new->due,
                'status' => $new->status === 'cancelled' ? 'void' : $new->status,
                'served_by' => $new->created_by,
                'created_at' => $new->created_at,
                'updated_at' => $new->updated_at,
            ]);
        }

        Schema::drop('sales_new');
    }
};
