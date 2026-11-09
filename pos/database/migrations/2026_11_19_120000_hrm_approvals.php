<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * HRM step 3: approval workflow.
 * approval_workflows: per kind of request, the levels it goes through, in order.
 *   Levels: reporting_manager, department_manager, hr_manager, administrator.
 * approvals: one row per level per request, with who decided, when and their remarks (the history).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_workflows', function (Blueprint $table) {
            $table->id();
            $table->string('module', 20)->unique();   // leave, overtime, correction, deduction, payroll
            $table->json('levels');                    // ["reporting_manager", "hr_manager"]
            $table->boolean('active')->default(true);  // off = one person with the approve permission decides
            $table->timestamps();
        });

        Schema::create('approvals', function (Blueprint $table) {
            $table->id();
            $table->string('module', 20);
            $table->unsignedBigInteger('record_id');
            $table->unsignedTinyInteger('step');       // 1, 2, 3 ...
            $table->string('level', 20);
            $table->string('status', 10)->default('waiting'); // waiting, pending (its turn), approved, rejected, skipped
            $table->string('approver_id')->nullable();
            $table->string('approver_name')->nullable();
            $table->string('remarks', 500)->nullable();
            $table->dateTime('acted_at')->nullable();
            $table->timestamps();
            $table->index(['module', 'record_id']);
            $table->index(['module', 'status']);
        });

        $now = now();
        foreach ([
            'leave' => ['reporting_manager', 'hr_manager'],
            'overtime' => ['reporting_manager', 'hr_manager'],
            'correction' => ['reporting_manager', 'hr_manager'],
            'deduction' => ['hr_manager'],
            'payroll' => ['hr_manager', 'administrator'],
        ] as $module => $levels) {
            DB::table('approval_workflows')->insert(['module' => $module, 'levels' => json_encode($levels), 'active' => true, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('approvals');
        Schema::dropIfExists('approval_workflows');
    }
};
