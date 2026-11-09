<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Stock Transfers and Stock Adjustment screens.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->string('ref')->primary(); // TR-0418
            $table->date('date');
            $table->string('from');
            $table->string('to');
            $table->string('status')->default('draft'); // draft, transit, received, variance, cancelled
            $table->decimal('ship', 12, 2)->default(0);
            $table->string('by')->nullable();
            $table->text('notes')->nullable();
            $table->json('lines'); // [{pid, qty, recv}]
            $table->json('log');   // [[text, when, tone]]
            $table->timestamps();
        });

        Schema::create('stock_adjustments', function (Blueprint $table) {
            $table->string('ref')->primary(); // SA-0231
            $table->date('date');
            $table->string('loc');
            $table->string('type'); // dec, inc, count
            $table->string('by')->nullable();
            $table->string('approved')->nullable();
            $table->text('notes')->nullable();
            $table->json('lines'); // [{pid, before, delta, reason}]
            $table->string('reverses')->nullable();    // ref this adjustment reverses
            $table->string('reversed_by')->nullable(); // ref of the adjustment that reversed this one
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_adjustments');
        Schema::dropIfExists('stock_transfers');
    }
};
