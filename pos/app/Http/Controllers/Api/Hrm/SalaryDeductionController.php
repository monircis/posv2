<?php

namespace App\Http\Controllers\Api\Hrm;

use App\Models\Approval;
use App\Models\Attendance;
use App\Models\DeductionException;
use App\Models\Employee;
use App\Models\SalaryDeduction;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;

/**
 * HRM > Salary deductions. Nothing is taken automatically: calculate() makes deductions from the attendance and
 * the deduction policies, and they go through the "deduction" approval workflow (unless the policy says no
 * approval is needed). Only approved ones go into payroll. Needs `hrm.payroll`.
 */
#[Group('HRM', weight: 18)]
class SalaryDeductionController extends HrmController
{
    /**
     * List deductions
     *
     * Newest first. Filters: month, deduction_status, employee_id, branch_id, type. Needs `hrm.payroll`.
     */
    public function index(Request $request)
    {
        $query = SalaryDeduction::with('employee:id,code,first,last')->whereIn('employee_id', $this->employeeIds($request))->orderByDesc('id');
        foreach (['month' => 'month', 'deduction_status' => 'status', 'type' => 'type'] as $param => $field) {
            if ($request->filled($param)) {
                $query->where($field, $request->input($param));
            }
        }

        return $query->limit(1000)->get();
    }

    /**
     * Calculate a month
     *
     * Goes through the attendance of the month (days before today, without a pending fix) for the working
     * employees (one branch with branch_id) and adds the deductions the policies give.
     * Run it again any time: days already deducted are skipped. Needs `hrm.payroll`.
     */
    public function calculate(Request $request)
    {
        $data = $request->validate(['month' => 'required|date_format:Y-m']);
        $employees = $this->employeeQuery($request)->where(fn ($q) => $q->whereIn('status', Employee::WORKING)->orWhere('exit_date', '>=', $data['month'].'-01'))->get();
        $added = SalaryDeduction::calculate($data['month'], $employees, (string) $request->user()->id);

        return ['month' => $data['month'], 'added' => $added, 'employees' => $employees->count()];
    }

    /**
     * Add a deduction by hand
     *
     * An amount off one month's pay, with a note (e.g. a damaged item). Always goes through approval. Needs `hrm.payroll`.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'employee_id' => 'required|integer',
            'month' => 'required|date_format:Y-m',
            'amount' => 'required|numeric|gt:0',
            'note' => 'required|string|max:255',
        ]);
        $employee = $this->findEmployee($request, $data['employee_id']);
        if (! $employee) {
            return $this->notYourBranch();
        }

        $deduction = SalaryDeduction::create([
            'ref' => SalaryDeduction::nextRef(), 'employee_id' => $employee->id, 'branch_id' => $employee->branch_id, 'type' => 'manual',
            'month' => $data['month'], 'amount' => round($data['amount'], 2), 'quantity' => 0, 'status' => 'pending',
            'note' => $data['note'], 'user_id' => (string) $request->user()->id,
        ]);
        Approval::start('deduction', $deduction);

        return response()->json($deduction->fresh(), 201);
    }

    /** One deduction with its approval history. Needs `hrm.payroll`. */
    public function show(Request $request, SalaryDeduction $salaryDeduction)
    {
        if (! $this->canSeeEmployee($request, $salaryDeduction->employee)) {
            return $this->notYourBranch();
        }

        return $salaryDeduction->load('employee:id,code,first,last')->toArray() + ['approvals' => Approval::history('deduction', $salaryDeduction->id)];
    }

    /**
     * Remove a deduction
     *
     * Only while it is pending. (A rejected one stays, so calculate() does not add it again.) Needs `hrm.payroll`.
     */
    public function destroy(Request $request, SalaryDeduction $salaryDeduction)
    {
        if (! $this->canSeeEmployee($request, $salaryDeduction->employee)) {
            return $this->notYourBranch();
        }
        if ($salaryDeduction->status !== 'pending') {
            return $this->failed($salaryDeduction->ref.' is '.$salaryDeduction->status.'. Reject it instead.');
        }
        Approval::cancel('deduction', $salaryDeduction->id);
        $salaryDeduction->delete();

        return response()->noContent();
    }

    // ---- exceptions ----

    /** Employees excused from deductions. Needs `hrm.payroll`. */
    public function exceptions(Request $request)
    {
        return DeductionException::whereIn('employee_id', $this->employeeIds($request))->orderByDesc('date_from')->get();
    }

    /**
     * Excuse an employee
     *
     * From date_from (to date_to) the employee is not deducted by that policy (or by any, without deduction_policy_id).
     * Needs `hrm.payroll`.
     */
    public function storeException(Request $request)
    {
        $data = $request->validate([
            'employee_id' => 'required|integer',
            'deduction_policy_id' => 'nullable|exists:deduction_policies,id',
            'date_from' => 'required|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'reason' => 'nullable|string|max:255',
        ]);
        if (! $this->findEmployee($request, $data['employee_id'])) {
            return $this->notYourBranch();
        }

        return response()->json(DeductionException::create($data + ['created_by' => $this->by($request)]), 201);
    }

    /** Remove an exception. Needs `hrm.payroll`. */
    public function destroyException(Request $request, DeductionException $deductionException)
    {
        if (! $this->findEmployee($request, $deductionException->employee_id)) {
            return $this->notYourBranch();
        }
        $deductionException->delete();

        return response()->noContent();
    }
}
