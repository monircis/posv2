<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Price rules: from one date to another, sell products dearer or cheaper,
 * by a percent or a fixed amount. Settings > Price rules.
 * - price_rules: one row per rule
 * - price_rule_products: the products of a rule that is not for all products
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->date('start_date');
            $table->date('end_date');
            $table->string('change', 10);                    // increase or decrease
            $table->string('amount_type', 10);               // percent or fixed
            $table->decimal('amount', 10, 2);                // 10 = 10% or $10.00 per unit
            $table->boolean('all_products')->default(true);  // false: only the products listed below
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('price_rule_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('price_rule_id')->constrained('price_rules')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->unique(['price_rule_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_rule_products');
        Schema::dropIfExists('price_rules');
    }
};
