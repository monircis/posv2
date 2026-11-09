<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Settings > Invoice & receipt. One row. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_settings', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_prefix', 10)->default('INV-');
            $table->unsignedInteger('next_invoice_number')->default(1);
            $table->string('receipt_prefix', 10)->default('R-');
            $table->string('paper', 20)->default('80mm roll');
            $table->boolean('show_logo')->default(true);
            $table->boolean('show_abn')->default(true);
            $table->string('payment_terms', 20)->default('Due on receipt');
            $table->string('footer_text', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_settings');
    }
};
