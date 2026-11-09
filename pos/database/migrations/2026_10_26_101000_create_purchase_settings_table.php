<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Settings > Purchases. One row. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_settings', function (Blueprint $table) {
            $table->id();
            $table->string('po_prefix', 10)->default('PO-');
            $table->unsignedInteger('approval_over')->default(5000); // dollars
            $table->string('default_supplier_terms', 20)->default('14 days');
            $table->boolean('allow_partial_receiving')->default(true);
            $table->string('cost_method', 20)->default('Weighted average');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_settings');
    }
};
