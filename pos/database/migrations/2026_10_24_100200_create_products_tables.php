<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Products screen and POS: products by SKU, stock per location, categories / brands / units.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->string('sku')->primary();
            $table->string('name');
            $table->string('category')->nullable();
            $table->string('unit')->default('kg'); // kg or ea
            $table->string('description')->nullable(); // e.g. "Halal Prime", "house made"
            $table->decimal('cost', 12, 2)->default(0);
            $table->decimal('price', 12, 2)->default(0);
            $table->decimal('stock', 12, 3)->nullable(); // null = not stock managed (made to order)
            $table->decimal('alert', 12, 3)->nullable();
            $table->boolean('pos_favourite')->default(false);
            $table->boolean('kitchen')->default(false); // sends a ticket to the kitchen
            $table->timestamps();
        });

        Schema::create('product_stocks', function (Blueprint $table) {
            $table->id();
            $table->string('location');
            $table->string('sku');
            $table->decimal('qty', 12, 3)->default(0);
            $table->timestamps();
            $table->unique(['location', 'sku']);
        });

        Schema::create('taxonomies', function (Blueprint $table) {
            $table->id();
            $table->string('kind'); // categories, brands, units
            $table->string('name');
            $table->string('note')->nullable();
            $table->integer('items')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('taxonomies');
        Schema::dropIfExists('product_stocks');
        Schema::dropIfExists('products');
    }
};
