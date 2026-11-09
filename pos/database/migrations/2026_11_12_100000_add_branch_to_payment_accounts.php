<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Payment accounts belong to a branch (branch_id), or to the whole business (null).
 * This replaces `loc`, which held a branch code for cash drawers only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_accounts', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('kind')->constrained('branches')->nullOnDelete();
        });

        foreach (DB::table('branches')->get(['id', 'code']) as $branch) {
            DB::table('payment_accounts')->where('loc', $branch->code)->update(['branch_id' => $branch->id]);
        }

        Schema::table('payment_accounts', function (Blueprint $table) {
            $table->dropColumn('loc');
        });
    }

    public function down(): void
    {
        Schema::table('payment_accounts', function (Blueprint $table) {
            $table->string('loc')->nullable()->after('kind');
        });

        foreach (DB::table('branches')->get(['id', 'code']) as $branch) {
            DB::table('payment_accounts')->where('branch_id', $branch->id)->update(['loc' => $branch->code]);
        }

        Schema::table('payment_accounts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('branch_id');
        });
    }
};
