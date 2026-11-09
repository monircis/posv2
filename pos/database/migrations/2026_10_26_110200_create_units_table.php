<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Products > Units, e.g. Kilogram (kg), Each (ea). products.unit stores the short name. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60)->unique();       // Kilogram
            $table->string('short_name', 10)->unique(); // kg
            $table->boolean('allow_decimals')->default(false); // 1.25 kg yes, 1.5 packs no
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};
