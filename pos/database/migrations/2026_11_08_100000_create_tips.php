<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * POS tips: kept apart from the sale total, so they are not sales income.
 * Each tip is for one staff member and is paid out to them later (HRM > Tips).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->unsignedInteger('employee_id');
            $table->foreign('employee_id')->references('id')->on('employees')->restrictOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('method', 10);                  // cash or card
            $table->string('payment_account_id')->nullable(); // where the tip money went in (drawer or card account)
            $table->string('created_by')->nullable();
            $table->dateTime('paid_at')->nullable();       // paid out to the staff member
            $table->string('paid_by')->nullable();
            $table->string('paid_from_account_id')->nullable();
            $table->timestamps();
            $table->index(['employee_id', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tips');
    }
};
