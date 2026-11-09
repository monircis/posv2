<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * HRM approval steps. A request (leave, overtime, correction, deduction, payroll) gets one row per level
 * of its workflow (approval_workflows). Only the step whose turn it is has status "pending".
 *
 * Who can decide a step:
 * - reporting_manager:  the sign-in user linked to the employee's reporting manager
 * - department_manager: the sign-in user linked to the manager of the employee's department
 * - hr_manager:         a user with hrm.approve (at the employee's branch, unless they have hrm.all-locations)
 * - administrator:      a user with hrm.admin (they can also decide any other level)
 * Nobody decides their own request. A level with nobody to fill it (no reporting manager) is left out.
 *
 * Every request model has: approvalEmployee(), approvalCheck() (a problem that stops the last approval, or null),
 * approvalApproved($user), approvalRejected($user) and approvalSummary().
 */
class Approval extends Model
{
    public const LEVELS = ['reporting_manager', 'department_manager', 'hr_manager', 'administrator'];

    public const MODULES = [
        'leave' => LeaveRequest::class,
        'overtime' => OvertimeRequest::class,
        'correction' => AttendanceCorrection::class,
        'deduction' => SalaryDeduction::class,
        'payroll' => PayRun::class,
    ];

    protected $table = 'approvals';

    protected $hidden = ['created_at', 'updated_at', 'record_id', 'module'];

    protected $fillable = ['module', 'record_id', 'step', 'level', 'status', 'approver_id', 'approver_name', 'remarks', 'acted_at'];

    protected function casts(): array
    {
        return ['step' => 'integer', 'acted_at' => 'datetime'];
    }

    /** Creates the steps for a new request. */
    public static function start(string $module, Model $record): void
    {
        $employee = $record->approvalEmployee();
        $workflow = ApprovalWorkflow::where('module', $module)->first();
        $levels = $workflow && $workflow->active && $workflow->levels ? $workflow->levels : ['hr_manager'];

        $steps = [];
        foreach ($levels as $level) {
            if ($level === 'reporting_manager' && ! $employee?->manager?->user_id) {
                continue;
            }
            if ($level === 'department_manager' && ! $employee?->department?->manager?->user_id) {
                continue;
            }
            $steps[] = $level;
        }
        if (! $steps) {
            $steps = ['hr_manager'];
        }

        static::where('module', $module)->where('record_id', $record->id)->delete();
        foreach ($steps as $i => $level) {
            static::create(['module' => $module, 'record_id' => $record->id, 'step' => $i + 1, 'level' => $level, 'status' => $i === 0 ? 'pending' : 'waiting']);
        }
    }

    /** The step whose turn it is, or null. */
    public static function current(string $module, int $recordId): ?Approval
    {
        return static::where('module', $module)->where('record_id', $recordId)->where('status', 'pending')->first();
    }

    /** All steps of a request, in order: the approval history. */
    public static function history(string $module, int $recordId)
    {
        return static::where('module', $module)->where('record_id', $recordId)->orderBy('step')->get();
    }

    /** May this user decide this step? */
    public static function fits(AuthUser $user, Approval $step, ?Employee $employee): bool
    {
        if ($employee && $employee->user_id && (string) $employee->user_id === (string) $user->id) {
            return false;
        }
        if ($user->hasPermission('hrm.admin')) {
            return true;
        }

        return match ($step->level) {
            'reporting_manager' => (string) $employee?->manager?->user_id === (string) $user->id,
            'department_manager' => (string) $employee?->department?->manager?->user_id === (string) $user->id,
            'hr_manager' => $user->hasPermission('hrm.approve') && self::sameBranch($user, $employee),
            default => false,
        };
    }

    private static function sameBranch(AuthUser $user, ?Employee $employee): bool
    {
        return ! $user->branch_id || ! $employee || $user->hasPermission('hrm.all-locations') || (int) $employee->branch_id === (int) $user->branch_id;
    }

    /**
     * Approve or reject the current step.
     * Returns "approved" (all steps done), "next" (the next level's turn) or "rejected", or an error message.
     */
    public static function act(AuthUser $user, string $module, Model $record, bool $approve, ?string $remarks): array
    {
        return DB::transaction(function () use ($user, $module, $record, $approve, $remarks) {
            $step = static::where('module', $module)->where('record_id', $record->id)->where('status', 'pending')->lockForUpdate()->first();
            if (! $step) {
                return ['error' => 'Nothing is waiting for a decision on this.'];
            }
            if (! self::fits($user, $step, $record->approvalEmployee())) {
                return ['error' => 'This is waiting for the '.str_replace('_', ' ', $step->level).', not you.'];
            }

            $next = static::where('module', $module)->where('record_id', $record->id)->where('status', 'waiting')->orderBy('step')->first();

            // The last step: check it can still go through (e.g. enough leave left) before saving anything.
            if ($approve && ! $next) {
                $problem = $record->approvalCheck();
                if ($problem) {
                    return ['error' => $problem];
                }
            }

            $step->update([
                'status' => $approve ? 'approved' : 'rejected', 'approver_id' => (string) $user->id, 'approver_name' => $user->name,
                'remarks' => $remarks, 'acted_at' => now(),
            ]);

            if (! $approve) {
                static::where('module', $module)->where('record_id', $record->id)->where('status', 'waiting')->update(['status' => 'skipped']);
                $record->approvalRejected($user);

                return ['result' => 'rejected'];
            }

            if ($next) {
                $next->update(['status' => 'pending']);

                return ['result' => 'next'];
            }

            $record->approvalApproved($user);

            return ['result' => 'approved'];
        });
    }

    /** Stops the steps of a cancelled request. */
    public static function cancel(string $module, int $recordId): void
    {
        static::where('module', $module)->where('record_id', $recordId)->whereIn('status', ['pending', 'waiting'])->update(['status' => 'skipped']);
    }

    /** What is waiting for this user to decide, across all modules. */
    public static function pendingFor(AuthUser $user, ?string $onlyModule = null): array
    {
        $out = [];
        $query = static::where('status', 'pending')->orderBy('id');
        if ($onlyModule) {
            $query->where('module', $onlyModule);
        }
        foreach ($query->get() as $step) {
            $class = self::MODULES[$step->module];
            $record = $class::find($step->record_id);
            if ($record && self::fits($user, $step, $record->approvalEmployee())) {
                $out[] = ['module' => $step->module, 'record_id' => $record->id, 'step' => $step->step, 'level' => $step->level] + $record->approvalSummary();
            }
        }

        return $out;
    }
}
