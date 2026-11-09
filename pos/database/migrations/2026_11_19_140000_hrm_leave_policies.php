<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * HRM step 5: leave.
 * leave_policies: one row per leave type (Annual, Sick, Maternity ...) with its rules.
 * leave_balances: what each employee has of each type, per year.
 * leave_requests: gets the policy, half-day / hourly leave and an attachment.
 * The old Annual, Personal and Unpaid types become the first three policies.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_policies', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60);
            $table->string('code', 20)->unique();
            $table->boolean('paid')->default(true);
            $table->boolean('track_balance')->default(true);       // false = no balance needed (e.g. unpaid)
            $table->decimal('days_per_year', 6, 2)->default(0);
            $table->string('accrual', 10)->default('yearly');      // yearly (all on 1 Jan), monthly (1/12 each month), none
            $table->boolean('carry_forward')->default(false);
            $table->decimal('max_carry_days', 6, 2)->default(0);
            $table->decimal('max_balance', 6, 2)->nullable();      // null = no limit
            $table->boolean('encashable')->default(false);
            $table->boolean('allow_half_day')->default(true);
            $table->boolean('allow_hourly')->default(false);
            $table->boolean('exclude_weekends')->default(true);    // weekly off days are not counted
            $table->boolean('exclude_holidays')->default(true);
            $table->boolean('probation_allowed')->default(true);   // false = not while on probation
            $table->decimal('attachment_after_days', 6, 2)->nullable(); // a file is needed for longer leave than this
            $table->string('gender', 10)->nullable();              // Female = maternity, Male = paternity
            $table->json('employment_types')->nullable();          // null = everyone, or ["Full-time","Part-time"]
            $table->foreignId('branch_id')->nullable()->constrained('branches')->restrictOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('leave_balances', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('employee_id');
            $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
            $table->foreignId('leave_policy_id')->constrained('leave_policies')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->decimal('allocated', 6, 2)->default(0);
            $table->decimal('carried', 6, 2)->default(0);
            $table->decimal('accrued', 6, 2)->default(0);
            $table->decimal('adjusted', 6, 2)->default(0);
            $table->decimal('used', 6, 2)->default(0);
            $table->decimal('encashed', 6, 2)->default(0);
            $table->unsignedTinyInteger('accrued_months')->default(0); // monthly accrual: months already added
            $table->timestamps();
            $table->unique(['employee_id', 'leave_policy_id', 'year']);
        });

        Schema::table('leave_requests', function (Blueprint $table) {
            $table->foreignId('leave_policy_id')->nullable()->after('employee_id')->constrained('leave_policies')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->after('leave_policy_id')->constrained('branches')->nullOnDelete();
            $table->string('duration', 6)->default('full')->after('date_to'); // full, half, hours
            $table->decimal('hours', 5, 2)->nullable()->after('duration');
            $table->string('attachment')->nullable()->after('note');
            $table->string('user_id')->nullable();                            // who asked
            $table->index(['status', 'date_from']);
        });

        $now = now();
        foreach ([
            ['Annual', 'AL', true, true, 20, 'monthly', true, 20],
            ['Personal', 'PL', true, true, 10, 'yearly', true, 10],
            ['Unpaid', 'UL', false, false, 0, 'none', false, 0],
        ] as [$name, $code, $paid, $track, $days, $accrual, $carry, $maxCarry]) {
            $id = DB::table('leave_policies')->insertGetId([
                'name' => $name, 'code' => $code, 'paid' => $paid, 'track_balance' => $track, 'days_per_year' => $days,
                'accrual' => $accrual, 'carry_forward' => $carry, 'max_carry_days' => $maxCarry,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            DB::table('leave_requests')->where('type', $name)->update(['leave_policy_id' => $id]);
        }

        // The old balances on the employee (al, pl) become this year's balances.
        $year = (int) $now->format('Y');
        $policies = DB::table('leave_policies')->pluck('id', 'code');
        foreach (DB::table('employees')->get(['id', 'al', 'pl']) as $employee) {
            foreach (['AL' => $employee->al, 'PL' => $employee->pl] as $code => $days) {
                DB::table('leave_balances')->insert([
                    'employee_id' => $employee->id, 'leave_policy_id' => $policies[$code], 'year' => $year,
                    'adjusted' => $days, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropForeign(['leave_policy_id']);
            $table->dropForeign(['branch_id']);
            $table->dropIndex(['status', 'date_from']);
            $table->dropColumn(['leave_policy_id', 'branch_id', 'duration', 'hours', 'attachment', 'user_id']);
        });
        Schema::dropIfExists('leave_balances');
        Schema::dropIfExists('leave_policies');
    }
};
