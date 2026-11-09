<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Manufacturing screen (recipes, production orders, raw materials) and Settings.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('materials', function (Blueprint $table) {
            $table->string('code')->primary(); // bm, cas, sp ...
            $table->string('name');
            $table->string('unit');
            $table->decimal('cost', 12, 2)->default(0);
            $table->decimal('stock', 12, 3)->default(0);
            $table->timestamps();
        });

        Schema::create('recipes', function (Blueprint $table) {
            $table->string('id')->primary(); // saus, kofta ...
            $table->string('name');
            $table->string('unit');
            $table->decimal('out', 12, 3)->default(0); // output per batch
            $table->decimal('price', 12, 2)->default(0);
            $table->json('lines'); // [[material code, qty per batch]]
            $table->timestamps();
        });

        Schema::create('production_orders', function (Blueprint $table) {
            $table->string('ref')->primary(); // MO-0117
            $table->string('rec');            // recipes.id
            $table->integer('b');             // batches
            $table->string('loc');
            $table->string('status')->default('planned'); // planned, progress, done, cancelled
            $table->string('date');
            $table->decimal('actual', 12, 3)->nullable(); // actual output when done
            $table->json('log');
            $table->timestamps();
        });

        // Settings pages and other small documents, stored as JSON by key.
        Schema::create('app_settings', function (Blueprint $table) {
            $table->string('key')->primary(); // settings, payroll, accounting.periods
            $table->json('value');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_settings');
        Schema::dropIfExists('production_orders');
        Schema::dropIfExists('recipes');
        Schema::dropIfExists('materials');
    }
};
