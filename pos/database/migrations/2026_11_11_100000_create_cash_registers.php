<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cash register shifts at the POS. A cashier counts the cash in the drawer to start (opening_cash),
 * every cash sale, refund, tip and cash in/out is a move, and at the end the cash is counted again.
 * Expected cash = opening_cash + all moves. The difference is what was over (+) or short (-).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_registers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->string('user_id');                       // the user in the auth service
            $table->string('user_name');
            $table->dateTime('opened_at');
            $table->decimal('opening_cash', 10, 2);
            $table->string('opening_note')->nullable();
            $table->dateTime('closed_at')->nullable();       // null = still open
            $table->string('closed_by')->nullable();
            $table->decimal('expected_cash', 10, 2)->nullable();
            $table->decimal('counted_cash', 10, 2)->nullable();
            $table->decimal('difference', 10, 2)->nullable(); // counted - expected
            $table->decimal('kept_cash', 10, 2)->nullable();  // left in the drawer for the next shift
            $table->string('close_note')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'branch_id', 'closed_at']);
        });

        Schema::create('cash_register_moves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cash_register_id')->constrained('cash_registers')->cascadeOnDelete();
            $table->string('type', 20);                       // sale, refund, tip, received, cash_in, cash_out
            $table->decimal('amount', 10, 2);                  // + into the drawer, - out of it
            $table->string('ref')->nullable();                 // the sale ref
            $table->string('note')->nullable();
            $table->string('user_name')->nullable();
            $table->timestamps();
        });

        // Settings > Sales & POS: cashiers must count the opening cash before they can sell.
        Schema::table('pos_settings', function (Blueprint $table) {
            $table->boolean('require_register')->default(true)->after('search_keypad');
        });
    }

    public function down(): void
    {
        Schema::table('pos_settings', function (Blueprint $table) {
            $table->dropColumn('require_register');
        });
        Schema::dropIfExists('cash_register_moves');
        Schema::dropIfExists('cash_registers');
    }
};
