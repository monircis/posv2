<?php

namespace App\Http\Controllers\Api\Hrm;

use App\Models\Approval;
use App\Models\ApprovalWorkflow;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;

/**
 * HRM > Approvals. One place to see and decide what is waiting for you:
 * leave, overtime, attendance corrections, deductions and payroll.
 * Any signed-in user can call these: what they may decide is checked per step (see App\Models\Approval):
 * the reporting manager, the department manager, HR (hrm.approve) or an administrator (hrm.admin).
 */
#[Group('HRM approvals', 'Approval steps, history and the approval workflows.', weight: 20)]
class ApprovalController extends HrmController
{
    /**
     * Waiting for me
     *
     * Everything whose current step this user can decide. ?module= for one kind
     * (leave, overtime, correction, deduction, payroll).
     */
    public function pending(Request $request)
    {
        $module = $request->input('module');
        if ($module && ! isset(Approval::MODULES[$module])) {
            return $this->failed('Unknown module.');
        }

        return Approval::pendingFor($request->user(), $module);
    }

    /**
     * Approve or reject
     *
     * Decides the current step. `approve` true or false, `remarks` optional (required when rejecting).
     * The answer has `result`: approved (all steps done), next (waiting for the next level) or rejected.
     */
    public function decide(Request $request, string $module, int $id)
    {
        $data = $request->validate(['approve' => 'required|boolean', 'remarks' => 'nullable|required_if:approve,false|string|max:500']);
        $record = $this->record($module, $id);
        if (! $record) {
            return $this->failed('Not found.', 404);
        }

        $answer = Approval::act($request->user(), $module, $record, (bool) $data['approve'], $data['remarks'] ?? null);
        if (isset($answer['error'])) {
            return $this->failed($answer['error'], str_contains($answer['error'], 'not you') ? 403 : 422);
        }

        return ['result' => $answer['result'], 'record' => $record->fresh(), 'approvals' => Approval::history($module, $id)];
    }

    /**
     * Approval history
     *
     * Every step of one request: level, status, who decided, when, and their remarks. Needs `hrm.view`.
     */
    public function history(Request $request, string $module, int $id)
    {
        $record = $this->record($module, $id);
        if (! $record) {
            return $this->failed('Not found.', 404);
        }
        $employee = $record->approvalEmployee();
        if ($employee && ! $this->canSeeEmployee($request, $employee)) {
            return $this->notYourBranch();
        }

        return Approval::history($module, $id);
    }

    /** Approval workflows: the levels for each kind of request. Needs `hrm.view`. */
    public function workflows()
    {
        return ApprovalWorkflow::orderBy('id')->get();
    }

    /**
     * Change a workflow
     *
     * `levels` in order, from: reporting_manager, department_manager, hr_manager, administrator.
     * Only new requests use the change. Needs `hrm.admin`.
     */
    public function updateWorkflow(Request $request, string $module)
    {
        $data = $request->validate([
            'levels' => 'required|array|min:1|max:4',
            'levels.*' => 'distinct|in:'.implode(',', Approval::LEVELS),
            'active' => 'boolean',
        ]);
        if (! isset(Approval::MODULES[$module])) {
            return $this->failed('Unknown module.');
        }
        $workflow = ApprovalWorkflow::updateOrCreate(['module' => $module], ['levels' => array_values($data['levels']), 'active' => $data['active'] ?? true]);

        return $workflow->fresh();
    }

    private function record(string $module, int $id)
    {
        $class = Approval::MODULES[$module] ?? null;

        return $class ? $class::find($id) : null;
    }
}
