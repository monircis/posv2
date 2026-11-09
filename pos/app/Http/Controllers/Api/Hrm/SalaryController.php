<?php

namespace App\Http\Controllers\Api\Hrm;

use App\Models\Employee;
use App\Models\EmployeeLoan;
use App\Models\SalaryAdjustment;
use App\Models\SalaryStructure;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;

/**
 * HRM > Salary: salary structures (pay changes keep the old rows as history), one-off adjustments
 * (bonus, incentive, encashment, advance) and loans. Needs `hrm.payroll`.
 */
#[Group('HRM payroll', 'Salary structures, adjustments, loans, monthly payroll and payslips.', weight: 21)]
class SalaryController extends HrmController
{
    // ---- structures ----

    /** Salary history of an employee: every structure, newest first. Needs `hrm.payroll`. */
    public function structures(Request $request, Employee $employee)
    {
        if (! $this->canSeeEmployee($request, $employee)) {
            return $this->notYourBranch();
        }

        return $employee->salaryStructures()->get();
    }

    /**
     * New salary structure
     *
     * Monthly basic, house_rent, medical, transport, other_allowances and fixed_deductions ([{name, amount}]),
     * in force from effective_from. Needs `hrm.payroll`.
     */
    public function storeStructure(Request $request, Employee $employee)
    {
        $data = $request->validate([
            'effective_from' => 'required|date',
            'basic' => 'required|numeric|gt:0',
            'house_rent' => 'nullable|numeric|min:0',
            'medical' => 'nullable|numeric|min:0',
            'transport' => 'nullable|numeric|min:0',
            'other_allowances' => 'nullable|array',
            'other_allowances.*.name' => 'required|string|max:60',
            'other_allowances.*.amount' => 'required|numeric|min:0',
            'fixed_deductions' => 'nullable|array',
            'fixed_deductions.*.name' => 'required|string|max:60',
            'fixed_deductions.*.amount' => 'required|numeric|min:0',
            'note' => 'nullable|string|max:255',
        ]);
        if (! $this->canSeeEmployee($request, $employee)) {
            return $this->notYourBranch();
        }

        $structure = $employee->salaryStructures()->create([
            'house_rent' => $data['house_rent'] ?? 0, 'medical' => $data['medical'] ?? 0, 'transport' => $data['transport'] ?? 0,
            'created_by' => $this->by($request),
        ] + $data);

        return response()->json($structure->fresh(), 201);
    }

    /** Delete a structure that has not started yet. Needs `hrm.payroll`. */
    public function destroyStructure(Request $request, SalaryStructure $salaryStructure)
    {
        if (! $this->findEmployee($request, $salaryStructure->employee_id)) {
            return $this->notYourBranch();
        }
        if ($salaryStructure->effective_from->toDateString() <= now()->toDateString()) {
            return $this->failed('This salary has already started. Add a new one instead.');
        }
        $salaryStructure->delete();

        return response()->noContent();
    }

    // ---- adjustments ----

    /** Adjustments of a month (?month=2026-11). Needs `hrm.payroll`. */
    public function adjustments(Request $request)
    {
        $query = SalaryAdjustment::whereIn('employee_id', $this->employeeIds($request))->orderByDesc('id');
        if ($request->filled('month')) {
            $query->where('month', $request->input('month'));
        }

        return $query->limit(1000)->get();
    }

    /** Add a bonus, incentive or advance to a month's pay. Needs `hrm.payroll`. */
    public function storeAdjustment(Request $request)
    {
        $data = $request->validate([
            'employee_id' => 'required|integer',
            'month' => 'required|date_format:Y-m',
            'type' => 'required|in:bonus,incentive,advance',
            'amount' => 'required|numeric|gt:0',
            'note' => 'nullable|string|max:255',
        ]);
        if (! $this->findEmployee($request, $data['employee_id'])) {
            return $this->notYourBranch();
        }

        return response()->json(SalaryAdjustment::create($data + ['created_by' => $this->by($request)]), 201);
    }

    /** Remove an adjustment that has not been paid. Needs `hrm.payroll`. */
    public function destroyAdjustment(Request $request, SalaryAdjustment $salaryAdjustment)
    {
        if (! $this->findEmployee($request, $salaryAdjustment->employee_id)) {
            return $this->notYourBranch();
        }
        if ($salaryAdjustment->pay_run_id) {
            return $this->failed('This has been paid already.');
        }
        $salaryAdjustment->delete();

        return response()->noContent();
    }

    // ---- loans ----

    /** Loans (?employee_id=, ?loan_status=active). Needs `hrm.payroll`. */
    public function loans(Request $request)
    {
        $query = EmployeeLoan::whereIn('employee_id', $this->employeeIds($request))->orderByDesc('id');
        if ($request->filled('loan_status')) {
            $query->where('status', $request->input('loan_status'));
        }

        return $query->get();
    }

    /** Give a loan, paid back by `installment` each month from start_month. Needs `hrm.payroll`. */
    public function storeLoan(Request $request)
    {
        $data = $request->validate([
            'employee_id' => 'required|integer',
            'amount' => 'required|numeric|gt:0',
            'installment' => 'required|numeric|gt:0|lte:amount',
            'start_month' => 'required|date_format:Y-m',
            'note' => 'nullable|string|max:255',
        ]);
        if (! $this->findEmployee($request, $data['employee_id'])) {
            return $this->notYourBranch();
        }

        return response()->json(EmployeeLoan::create($data + ['status' => 'active', 'created_by' => $this->by($request)])->fresh(), 201);
    }

    /** Close a loan (no more instalments). Needs `hrm.payroll`. */
    public function closeLoan(Request $request, EmployeeLoan $employeeLoan)
    {
        if (! $this->findEmployee($request, $employeeLoan->employee_id)) {
            return $this->notYourBranch();
        }
        $employeeLoan->update(['status' => 'closed']);

        return $employeeLoan->fresh();
    }
}
