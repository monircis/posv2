<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The order discount can be a percentage (10%) or a fixed amount ($5.00).
// For a fixed amount, discount_percent keeps the percentage it works out to.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->string('discount_type', 10)->default('percent')->after('discount_percent');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn('discount_type');
        });
    }
};
