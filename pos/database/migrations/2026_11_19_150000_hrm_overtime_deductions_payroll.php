<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HRM step 6: overtime, salary deductions and monthly payroll.
 * The rates and rules are rows in the policy tables, never fixed in the code.
 * pay_runs / pay_run_items (the fortnight pay run) are reused: a run is now "fortnight" (as before) or "monthly".
 */
return new class extends Migration
{
    public function up(): void
    {
        // ---- overtime ----
        Schema::create('overtime_policies', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->foreignId('branch_id')->nullable()->constrained('branches')->restrictOnDelete(); // null = all branches
            $table->unsignedSmallInteger('min_minutes')->default(30);        // less extra time than this is not overtime
            $table->unsignedSmallInteger('max_daily_minutes')->default(0);   // 0 = no limit
            $table->unsignedSmallInteger('max_monthly_minutes')->default(0); // 0 = no limit
            $table->decimal('regular_rate', 4, 2)->default(1.5);             // x the hourly rate
            $table->decimal('weekend_rate', 4, 2)->default(2);
            $table->decimal('holiday_rate', 4, 2)->default(2.5);
            $table->boolean('auto_calculate')->default(true);                // make a request from the attendance
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->foreignId('overtime_policy_id')->nullable()->constrained('overtime_policies')->nullOnDelete();
        });

        Schema::create('overtime_requests', function (Blueprint $table) {
            $table->id();
            $table->string('ref', 20)->unique();       // OT-1001
            $table->unsignedInteger('employee_id');
            $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('attendance_id')->nullable()->constrained('attendances')->nullOnDelete();
            $table->foreignId('overtime_policy_id')->nullable()->constrained('overtime_policies')->nullOnDelete();
            $table->date('date');
            $table->unsignedSmallInteger('minutes');
            $table->string('day_type', 10)->default('workday'); // workday, weekend, holiday
            $table->decimal('rate', 4, 2)->default(1);           // multiplier used
            $table->decimal('hourly_rate', 10, 2)->default(0);
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('source', 6)->default('manual');      // auto, manual
            $table->string('reason', 500)->nullable();
            $table->string('status', 12)->default('pending');    // pending, approved, rejected, cancelled
            $table->unsignedBigInteger('pay_run_id')->nullable();
            $table->string('user_id')->nullable();
            $table->timestamps();
            $table->index(['employee_id', 'date']);
            $table->index('status');
        });

        // ---- deductions ----
        Schema::create('deduction_policies', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->foreignId('branch_id')->nullable()->constrained('branches')->restrictOnDelete(); // null = all branches
            $table->string('type', 15);                 // late, early_leave, absence, unpaid_leave, half_day, missing_punch
            $table->string('method', 12);               // fixed, percent (of monthly basic), per_minute, per_hour, day_salary
            $table->decimal('value', 10, 2)->default(0); // the amount, percent, or the day fraction for day_salary (1 = a full day)
            $table->unsignedSmallInteger('grace_minutes')->default(0); // late/early: this many minutes are free
            $table->unsignedSmallInteger('free_times')->default(0);    // the first N times a month are free
            $table->decimal('monthly_limit', 10, 2)->nullable();       // most it can take in a month
            $table->boolean('needs_approval')->default(true);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('deduction_exceptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('employee_id');
            $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
            $table->foreignId('deduction_policy_id')->nullable()->constrained('deduction_policies')->cascadeOnDelete(); // null = every policy
            $table->date('date_from');
            $table->date('date_to')->nullable();
            $table->string('reason')->nullable();
            $table->string('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('salary_deductions', function (Blueprint $table) {
            $table->id();
            $table->string('ref', 20)->unique();       // SD-1001
            $table->unsignedInteger('employee_id');
            $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('deduction_policy_id')->nullable()->constrained('deduction_policies')->nullOnDelete(); // null = added by hand
            $table->foreignId('attendance_id')->nullable()->constrained('attendances')->nullOnDelete();
            $table->string('type', 15);
            $table->char('month', 7);                  // 2026-11
            $table->date('date')->nullable();          // the day it is for
            $table->decimal('quantity', 8, 2)->default(0); // minutes or days
            $table->decimal('amount', 12, 2);
            $table->string('status', 12)->default('pending'); // pending, approved, rejected
            $table->string('note')->nullable();
            $table->unsignedBigInteger('pay_run_id')->nullable();
            $table->string('user_id')->nullable();
            $table->timestamps();
            // One deduction per policy per day: the same lateness is never deducted twice.
            $table->unique(['employee_id', 'deduction_policy_id', 'date'], 'salary_deductions_once');
            $table->index(['month', 'status']);
        });

        // ---- salary ----
        Schema::create('salary_structures', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('employee_id');
            $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
            $table->date('effective_from');
            $table->decimal('basic', 12, 2);            // per month
            $table->decimal('house_rent', 12, 2)->default(0);
            $table->decimal('medical', 12, 2)->default(0);
            $table->decimal('transport', 12, 2)->default(0);
            $table->json('other_allowances')->nullable(); // [{"name": "Phone", "amount": 50}]
            $table->json('fixed_deductions')->nullable(); // [{"name": "Uniform", "amount": 20}]
            $table->string('note')->nullable();
            $table->string('created_by')->nullable();
            $table->timestamps();
            $table->index(['employee_id', 'effective_from']);
        });

        Schema::create('salary_adjustments', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('employee_id');
            $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
            $table->char('month', 7);
            $table->string('type', 10);                // bonus, incentive, advance
            $table->decimal('amount', 12, 2);
            $table->string('note')->nullable();
            $table->unsignedBigInteger('pay_run_id')->nullable();
            $table->string('created_by')->nullable();
            $table->timestamps();
            $table->index(['employee_id', 'month']);
        });

        Schema::create('employee_loans', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('employee_id');
            $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->decimal('installment', 12, 2);     // taken each month
            $table->char('start_month', 7);
            $table->decimal('paid', 12, 2)->default(0);
            $table->string('status', 10)->default('active'); // active, closed
            $table->string('note')->nullable();
            $table->string('created_by')->nullable();
            $table->timestamps();
        });

        Schema::table('pay_runs', function (Blueprint $table) {
            $table->string('kind', 10)->default('fortnight')->after('id'); // fortnight (as before) or monthly
            $table->char('month', 7)->nullable()->after('kind');
            $table->foreignId('branch_id')->nullable()->after('month')->constrained('branches')->nullOnDelete(); // null = all branches
            $table->index(['kind', 'month']);
        });

        Schema::table('pay_run_items', function (Blueprint $table) {
            $table->decimal('basic', 12, 2)->default(0)->after('ot_hours');
            $table->decimal('allowances', 12, 2)->default(0)->after('basic');
            $table->decimal('overtime', 12, 2)->default(0)->after('allowances');
            $table->decimal('bonus', 12, 2)->default(0)->after('overtime');      // bonus + incentives
            $table->decimal('deductions', 12, 2)->default(0)->after('bonus');    // fixed + attendance deductions
            $table->decimal('advance', 12, 2)->default(0)->after('deductions');
            $table->decimal('loan', 12, 2)->default(0)->after('advance');
            $table->json('lines')->nullable()->after('net');                     // the payslip lines
        });
    }

    public function down(): void
    {
        Schema::table('pay_run_items', function (Blueprint $table) {
            $table->dropColumn(['basic', 'allowances', 'overtime', 'bonus', 'deductions', 'advance', 'loan', 'lines']);
        });
        Schema::table('pay_runs', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->dropIndex(['kind', 'month']);
            $table->dropColumn(['kind', 'month', 'branch_id']);
        });
        Schema::dropIfExists('employee_loans');
        Schema::dropIfExists('salary_adjustments');
        Schema::dropIfExists('salary_structures');
        Schema::dropIfExists('salary_deductions');
        Schema::dropIfExists('deduction_exceptions');
        Schema::dropIfExists('deduction_policies');
        Schema::dropIfExists('overtime_requests');
        Schema::table('employees', function (Blueprint $table) {
            $table->dropForeign(['overtime_policy_id']);
            $table->dropColumn('overtime_policy_id');
        });
        Schema::dropIfExists('overtime_policies');
    }
};
