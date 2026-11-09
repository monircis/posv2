<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Which branches sell which products. Existing products are sold at every branch. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branch_product', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unique(['branch_id', 'product_id']);
        });

        // Keep today's behaviour: every product at every branch.
        $branchIds = DB::table('branches')->pluck('id');
        foreach (DB::table('products')->pluck('id') as $productId) {
            foreach ($branchIds as $branchId) {
                DB::table('branch_product')->insert(['branch_id' => $branchId, 'product_id' => $productId]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_product');
    }
};
