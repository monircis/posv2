<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * - sale_items and recipes get the foreign key to product_variants that other tables already have.
 * - taxonomies and sell_returns are dropped: the real categories, brands and units replaced the first,
 *   and nothing uses the second, which is empty.
 * - sales get a card_surcharge field, for the setting in Settings > Payments.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['sale_items', 'recipes'] as $table) {
            DB::table($table)->whereNotNull('variant_id')->whereNotIn('variant_id', DB::table('product_variants')->select('id'))->update(['variant_id' => null]);

            Schema::table($table, function (Blueprint $t) {
                $t->foreign('variant_id')->references('id')->on('product_variants')->nullOnDelete();
            });
        }

        Schema::dropIfExists('taxonomies');
        Schema::dropIfExists('sell_returns');

        Schema::table('sales', function (Blueprint $table) {
            $table->decimal('card_surcharge', 12, 2)->default(0)->after('delivery_fee');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn('card_surcharge');
        });

        Schema::create('sell_returns', function (Blueprint $table) {
            $table->string('ref')->primary();
            $table->string('inv');
            $table->string('settlement')->nullable();
            $table->decimal('total', 12, 2)->default(0);
            $table->json('lines')->nullable();
            $table->timestamps();
        });
        Schema::create('taxonomies', function (Blueprint $table) {
            $table->id();
            $table->string('kind');
            $table->string('name');
            $table->string('note')->nullable();
            $table->integer('items')->default(0);
            $table->timestamps();
        });

        foreach (['sale_items', 'recipes'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropForeign(['variant_id']);
            });
        }
    }
};
