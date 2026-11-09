<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Settings > Branches & locations > Defaults. One row. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('default_branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->boolean('track_stock_per_branch')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_settings');
    }
};
