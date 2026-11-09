<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Settings > Products. One row. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('auto_sku')->default(true);
            $table->string('sku_prefix', 10)->nullable();
            $table->string('default_unit', 10)->default('kg');
            $table->unsignedInteger('low_stock_alert')->default(10);
            $table->string('barcode_type', 20)->default('EAN-13');
            $table->boolean('track_expiry')->default(true);
            $table->boolean('track_batches')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_settings');
    }
};
