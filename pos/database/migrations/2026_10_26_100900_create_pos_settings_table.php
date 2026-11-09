<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Settings > Sales & POS. One row. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_settings', function (Blueprint $table) {
            $table->id();
            $table->string('default_customer', 30)->default('Walk-in customer');
            $table->boolean('allow_below_stock')->default(false);
            $table->unsignedTinyInteger('max_discount_percent')->default(15);
            $table->boolean('refund_needs_pin')->default(true);
            $table->boolean('use_scale')->default(true);
            $table->string('print_receipt', 10)->default('Ask');
            $table->string('clear_held_sales_after', 20)->default('End of day');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_settings');
    }
};
