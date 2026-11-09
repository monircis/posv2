<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/**
 * HRM as related tables:
 * - employees link to their branch, and their PINs are hashed.
 * - attendances and leave_requests link to the employee.
 * - pay_runs and pay_run_items replace the "payroll" JSON document in app_settings.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ---- employees ----
        DB::table('employees')->where('start', '')->update(['start' => null]);

        Schema::table('employees', function (Blueprint $table) {
            $table->unsignedInteger('id', true)->change();
            $table->date('start')->nullable()->change();
            $table->foreignId('branch_id')->nullable()->after('dept')->constrained('branches')->restrictOnDelete();
        });

        foreach (DB::table('employees')->get(['id', 'loc', 'pin']) as $employee) {
            DB::table('employees')->where('id', $employee->id)->update([
                'branch_id' => DB::table('branches')->where('name', $employee->loc)->value('id'),
                'pin' => $employee->pin ? Hash::make($employee->pin) : null,
            ]);
        }

        // On leave is worked out from approved leave now, so it is not stored.
        DB::table('employees')->where('status', 'On leave')->update(['status' => 'Active']);

        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('loc');
        });

        // ---- attendances ----
        DB::table('attendances')->whereNotIn('employee_id', DB::table('employees')->select('id'))->delete();

        Schema::table('attendances', function (Blueprint $table) {
            $table->boolean('absent')->default(false)->after('out');
            $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
        });

        // ---- leave_requests ----
        Schema::rename('leave_requests', 'leave_requests_old');

        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->string('ref', 40)->unique();              // LV-122
            $table->unsignedInteger('employee_id');
            $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
            $table->string('type', 20);                       // Annual, Personal, Unpaid
            $table->date('date_from');
            $table->date('date_to');
            $table->decimal('days', 6, 2)->default(1);
            $table->string('status', 20)->default('Pending'); // Pending, Approved, Declined
            $table->text('note')->nullable();
            $table->string('decided_by')->nullable();
            $table->timestamps();
        });

        // The old dates were "19/10" with no year; the demo requests are all from 2026.
        foreach (DB::table('leave_requests_old')->orderBy('id')->get() as $old) {
            DB::table('leave_requests')->insert([
                'ref' => $old->id,
                'employee_id' => $old->emp,
                'type' => $old->type,
                'date_from' => '2026-'.implode('-', array_reverse(explode('/', $old->from))),
                'date_to' => '2026-'.implode('-', array_reverse(explode('/', $old->to))),
                'days' => $old->days,
                'status' => $old->status,
                'note' => $old->note ?: null,
                'created_at' => $old->created_at,
                'updated_at' => $old->updated_at,
            ]);
        }
        Schema::drop('leave_requests_old');

        // ---- pay runs ----
        Schema::create('pay_runs', function (Blueprint $table) {
            $table->id();
            $table->date('period_from');
            $table->date('period_to');
            $table->date('pay_date');
            $table->string('status', 20)->default('draft');   // draft, approved, paid
            $table->string('approved_by')->nullable();
            $table->string('paid_by')->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('pay_run_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pay_run_id')->constrained('pay_runs')->cascadeOnDelete();
            $table->unsignedInteger('employee_id');
            $table->foreign('employee_id')->references('id')->on('employees')->restrictOnDelete();
            $table->decimal('ord_hours', 8, 2)->default(0);
            $table->decimal('ot_hours', 8, 2)->default(0);
            $table->decimal('gross', 12, 2)->default(0);      // worked out when the run is approved
            $table->decimal('tax', 12, 2)->default(0);
            $table->decimal('super', 12, 2)->default(0);
            $table->decimal('net', 12, 2)->default(0);
            $table->timestamps();
            $table->unique(['pay_run_id', 'employee_id']);
        });

        $payroll = json_decode((string) DB::table('app_settings')->where('key', 'payroll')->value('value'), true);
        if ($payroll) {
            $runId = DB::table('pay_runs')->insertGetId([
                'period_from' => '2026-09-15', 'period_to' => '2026-09-28', 'pay_date' => '2026-09-30',
                'status' => $payroll['payStatus'] ?? 'draft', 'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach ($payroll['pay'] ?? [] as $employeeId => $hours) {
                if (DB::table('employees')->where('id', $employeeId)->exists()) {
                    DB::table('pay_run_items')->insert([
                        'pay_run_id' => $runId, 'employee_id' => $employeeId,
                        'ord_hours' => (float) ($hours['ord'] ?? 0), 'ot_hours' => (float) ($hours['ot'] ?? 0),
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }
            DB::table('app_settings')->where('key', 'payroll')->delete();
        }
    }

    public function down(): void
    {
        Schema::drop('pay_run_items');
        Schema::drop('pay_runs');

        Schema::rename('leave_requests', 'leave_requests_new');
        Schema::create('leave_requests', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->unsignedInteger('emp');
            $table->string('type');
            $table->string('from');
            $table->string('to');
            $table->decimal('days', 6, 2)->default(1);
            $table->string('status')->default('Pending');
            $table->text('note')->nullable();
            $table->timestamps();
        });
        foreach (DB::table('leave_requests_new')->get() as $new) {
            DB::table('leave_requests')->insert(['id' => $new->ref, 'emp' => $new->employee_id, 'type' => $new->type, 'from' => date('d/m', strtotime($new->date_from)), 'to' => date('d/m', strtotime($new->date_to)), 'days' => $new->days, 'status' => $new->status, 'note' => $new->note, 'created_at' => $new->created_at, 'updated_at' => $new->updated_at]);
        }
        Schema::drop('leave_requests_new');

        Schema::table('attendances', function (Blueprint $table) {
            $table->dropForeign(['employee_id']);
            $table->dropColumn('absent');
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->string('loc')->nullable()->after('dept');
        });
        foreach (DB::table('employees')->get(['id', 'branch_id']) as $employee) {
            DB::table('employees')->where('id', $employee->id)->update(['loc' => DB::table('branches')->where('id', $employee->branch_id)->value('name')]);
        }
        Schema::table('employees', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->dropColumn('branch_id');
        });
        Schema::table('employees', function (Blueprint $table) {
            $table->string('start')->nullable()->change();
            $table->unsignedInteger('id', false)->change();
        });
        // Hashed PINs stay hashed: restore the backup if the plain PINs are needed.
    }
};
