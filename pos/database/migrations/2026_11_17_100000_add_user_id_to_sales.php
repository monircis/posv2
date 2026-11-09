<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Who made the sale (the user's id in the auth service), so a salesperson's dashboard shows only their own sales. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->string('user_id')->nullable()->after('created_by');
            $table->index(['user_id', 'sold_at']);
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'sold_at']);
            $table->dropColumn('user_id');
        });
    }
};
