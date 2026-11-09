<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Loyalty coins: customers earn coins on sales and spend them on later sales.
 * - coin_settings: Settings > Loyalty coins (one row)
 * - contacts.coins: each customer's coin balance
 * - sales: coins spent, what they were worth, coins earned
 * - coin_transactions: every + or - on a customer's coins
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coin_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('enabled')->default(false);
            $table->decimal('earn_per_dollar', 8, 2)->default(1);   // coins for each $1 of goods
            $table->decimal('coin_value', 8, 4)->default(0.01);     // what 1 coin is worth when spent
            $table->unsignedInteger('min_redeem')->default(100);    // fewest coins spent at a time
            $table->unsignedTinyInteger('max_redeem_percent')->default(50); // most of a sale coins can pay
            $table->timestamps();
        });

        Schema::table('contacts', function (Blueprint $table) {
            $table->integer('coins')->default(0)->after('balance');
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->unsignedInteger('coins_used')->default(0)->after('discount_approved_by');
            $table->decimal('coin_discount', 12, 2)->default(0)->after('coins_used');
            $table->unsignedInteger('coins_earned')->default(0)->after('coin_discount');
        });

        Schema::create('coin_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->foreignId('sale_id')->nullable()->constrained('sales')->nullOnDelete();
            $table->integer('change');                  // + earned or added, - spent or taken off
            $table->integer('balance');                 // the customer's coins after this change
            $table->string('kind', 30);                 // Earned, Spent, Sale cancelled, Adjusted
            $table->string('note')->nullable();
            $table->string('user_name')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coin_transactions');

        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['coins_used', 'coin_discount', 'coins_earned']);
        });

        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn('coins');
        });

        Schema::dropIfExists('coin_settings');
    }
};
