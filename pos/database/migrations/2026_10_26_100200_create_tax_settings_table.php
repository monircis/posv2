<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Settings > Tax > GST. One row. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('gst_registered')->default(true);
            $table->boolean('prices_include_tax')->default(true);
            $table->string('bas_period', 20)->default('Quarterly');
            $table->string('tax_rounding', 20)->default('Per line');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_settings');
    }
};
