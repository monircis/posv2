<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who made the record (the user's id in the auth service). A user without "<screen>.view-all"
 * only sees the records they made. created_by keeps the name for showing on screen.
 */
return new class extends Migration
{
    private const TABLES = ['purchases', 'purchase_returns', 'expenses', 'bookings', 'stock_transfers', 'stock_adjustments', 'production_orders'];

    public function up(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->string('user_id')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table) use ($name) {
                $table->dropIndex($name.'_user_id_index');
                $table->dropColumn('user_id');
            });
        }
    }
};
