<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Purchases and Sell screens (POS sales are saved here too), with their returns.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchases', function (Blueprint $table) {
            $table->string('ref')->primary(); // PO-2043
            $table->date('date');
            $table->string('supplier');
            $table->string('location')->nullable();
            $table->string('status')->default('ordered');          // ordered, received, pending
            $table->string('payment_status')->default('due');      // paid, partial, due
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('due', 12, 2)->default(0);
            $table->integer('line_count')->default(0);
            $table->json('lines')->nullable(); // [{name, qty, cost, disc}]
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_returns', function (Blueprint $table) {
            $table->string('ref')->primary(); // PR-0019
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

        Schema::create('sales', function (Blueprint $table) {
            $table->string('inv')->primary(); // 2026-5014
            $table->string('sold_at'); // as shown: "29/09 10:12"
            $table->string('customer')->default('Walk in');
            $table->integer('item_count')->default(0);
            $table->string('channel')->default('POS'); // POS, Online, Order, Quote
            $table->string('method')->nullable();
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('due', 12, 2)->default(0);
            $table->string('status')->default('paid'); // paid, partial, due, void, draft, quote
            $table->string('served_by')->nullable();
            $table->json('lines')->nullable(); // [{sku, name, qty, price, disc, note}]
            $table->timestamps();
        });

        Schema::create('sell_returns', function (Blueprint $table) {
            $table->string('ref')->primary(); // CN-0036
            $table->string('inv');
            $table->string('settlement')->nullable();
            $table->decimal('total', 12, 2)->default(0);
            $table->json('lines')->nullable(); // [{name, qty, reason, restock}]
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sell_returns');
        Schema::dropIfExists('sales');
        Schema::dropIfExists('purchase_returns');
        Schema::dropIfExists('purchases');
    }
};
