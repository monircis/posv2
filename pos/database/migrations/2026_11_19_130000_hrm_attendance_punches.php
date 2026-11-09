<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HRM step 4: attendance.
 * attendances stays one row per employee per (shift) day, and keeps its old columns (in, out, absent).
 * It now also keeps the worked-out totals. Every clock-in/out is a row in attendance_punches,
 * breaks are in attendance_breaks, and fixes go through attendance_corrections.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('employee_id')->constrained('branches')->nullOnDelete();
            $table->foreignId('shift_id')->nullable()->after('branch_id')->constrained('shifts')->nullOnDelete();
            $table->string('day_type', 10)->default('workday')->after('date'); // workday, weekend, holiday
            $table->string('status', 15)->nullable()->after('day_type');     // present, late, half_day, absent, leave, missing_punch, weekend, holiday
            $table->dateTime('first_in_at')->nullable();
            $table->dateTime('last_out_at')->nullable();
            $table->unsignedSmallInteger('worked_minutes')->default(0);
            $table->unsignedSmallInteger('break_minutes')->default(0);
            $table->unsignedSmallInteger('late_minutes')->default(0);
            $table->unsignedSmallInteger('early_minutes')->default(0);
            $table->unsignedSmallInteger('extra_minutes')->default(0);       // worked past the shift (overtime before the policy)
            $table->boolean('remote')->default(false);
            $table->boolean('corrected')->default(false);
            $table->string('note')->nullable();
            $table->index(['date', 'status']);
            $table->index(['branch_id', 'date']);
        });

        Schema::create('attendance_punches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_id')->constrained('attendances')->cascadeOnDelete();
            $table->unsignedInteger('employee_id');
            $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
            $table->string('type', 12);              // in, out, break_start, break_end
            $table->dateTime('punched_at');          // shop time
            $table->string('source', 12)->default('manual'); // pos, kiosk, self, manual, correction
            $table->boolean('remote')->default(false);
            $table->string('created_by')->nullable();
            $table->timestamps();
            $table->index(['employee_id', 'punched_at']);
        });

        Schema::create('attendance_breaks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_id')->constrained('attendances')->cascadeOnDelete();
            $table->dateTime('start_at');
            $table->dateTime('end_at')->nullable();
            $table->unsignedSmallInteger('minutes')->default(0);
            $table->timestamps();
        });

        Schema::create('attendance_corrections', function (Blueprint $table) {
            $table->id();
            $table->string('ref', 20)->unique();     // AC-1001
            $table->unsignedInteger('employee_id');
            $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->date('date');
            $table->dateTime('in_at');
            $table->dateTime('out_at')->nullable();
            $table->unsignedSmallInteger('break_minutes')->nullable(); // null = keep the breaks that are there
            $table->string('reason', 500);
            $table->string('status', 12)->default('pending');           // pending, approved, rejected, cancelled
            $table->string('user_id')->nullable();                      // who asked
            $table->string('created_by')->nullable();
            $table->timestamps();
            $table->index(['employee_id', 'date']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_corrections');
        Schema::dropIfExists('attendance_breaks');
        Schema::dropIfExists('attendance_punches');
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->dropForeign(['shift_id']);
            $table->dropIndex(['date', 'status']);
            $table->dropIndex(['branch_id', 'date']);
            $table->dropColumn(['branch_id', 'shift_id', 'day_type', 'status', 'first_in_at', 'last_out_at', 'worked_minutes',
                'break_minutes', 'late_minutes', 'early_minutes', 'extra_minutes', 'remote', 'corrected', 'note']);
        });
    }
};
