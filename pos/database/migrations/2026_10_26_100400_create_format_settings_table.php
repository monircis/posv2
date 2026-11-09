<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Settings > Currency & formats. One row. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('format_settings', function (Blueprint $table) {
            $table->id();
            $table->string('currency', 60)->default('AUD · Australian dollar');
            $table->string('symbol_position', 10)->default('Before');
            $table->unsignedTinyInteger('decimal_places')->default(2);
            $table->string('date_format', 20)->default('DD/MM/YYYY');
            $table->boolean('time_24h')->default(true);
            $table->string('week_starts_on', 10)->default('Monday');
            $table->string('weight_unit', 5)->default('kg');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('format_settings');
    }
};
