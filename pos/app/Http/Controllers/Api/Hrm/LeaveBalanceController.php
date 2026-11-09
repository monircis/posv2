<?php

namespace App\Http\Controllers\Api\Hrm;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeavePolicy;
use App\Models\SalaryAdjustment;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\Request;

/**
 * HRM > Leave balances, per employee, leave type and year.
 * Balances are made when first needed: yearly leave is allocated, last year's balance is carried forward
 * (up to the policy's limit) and monthly leave is accrued up to this month.
 */
#[Group('HRM', weight: 18)]
class LeaveBalanceController extends HrmController
{
    /**
     * List balances
     *
     * One row per employee and leave type (that keeps a balance) for the year. Filters as the employee list. Needs `hrm.view`.
     */
    #[QueryParameter('year', 'Default: this year.', type: 'integer')]
    public function index(Request $request)
    {
        $year = (int) $request->input('year', Attendance::shopNow()->year);
        $out = [];
        foreach ($this->employeeQuery($request)->whereIn('status', Employee::WORKING)->orderBy('first')->get() as $employee) {
            $out[] = ['employee_id' => $employee->id, 'code' => $employee->code, 'name' => $employee->name, 'balances' => self::balancesFor($employee, $year)];
        }

        return ['year' => $year, 'rows' => $out];
    }

    public static function balancesFor(Employee $employee, int $year): array
    {
        $out = [];
        foreach (LeavePolicy::forEmployee($employee) as $policy) {
            if (! $policy->track_balance) {
                continue;
            }
            $balance = LeaveBalance::for($employee, $policy, $year);
            $out[] = $balance->toArray() + ['policy' => $policy->name, 'code' => $policy->code, 'pending' => $balance->pendingDays(), 'encashable' => $policy->encashable];
        }

        return $out;
    }

    /**
     * Adjust a balance
     *
     * Adds `days` (or takes them off when negative), e.g. a correction or a gift of a day. Needs `hrm.edit`.
     */
    public function adjust(Request $request, Employee $employee, LeavePolicy $leavePolicy)
    {
        $data = $request->validate(['days' => 'required|numeric|between:-366,366|not_in:0', 'year' => 'nullable|integer|min:2000|max:2100']);
        if (! $this->canSeeEmployee($request, $employee)) {
            return $this->notYourBranch();
        }
        $balance = LeaveBalance::for($employee, $leavePolicy, (int) ($data['year'] ?? Attendance::shopNow()->year));
        if ($leavePolicy->max_balance !== null && $balance->available + $data['days'] > $leavePolicy->max_balance) {
            return $this->failed('That is more than the most allowed ('.$leavePolicy->max_balance.' days).');
        }
        $balance->adjusted = round($balance->adjusted + $data['days'], 2);
        $balance->save();

        return $balance->fresh();
    }

    /**
     * Encash leave
     *
     * Pays out `days` of an encashable leave balance: the days come off and their pay (days x a day's pay)
     * is added to that month's payroll as "encashment". Needs `hrm.payroll`.
     */
    public function encash(Request $request, Employee $employee, LeavePolicy $leavePolicy)
    {
        $data = $request->validate(['days' => 'required|numeric|min:0.5|max:366', 'year' => 'nullable|integer|min:2000|max:2100']);
        if (! $this->canSeeEmployee($request, $employee)) {
            return $this->notYourBranch();
        }
        if (! $leavePolicy->encashable) {
            return $this->failed($leavePolicy->name.' leave cannot be encashed.');
        }
        $balance = LeaveBalance::for($employee, $leavePolicy, (int) ($data['year'] ?? Attendance::shopNow()->year));
        if ($balance->available - $balance->pendingDays() < $data['days']) {
            return $this->failed($employee->first.' only has '.round($balance->available - $balance->pendingDays(), 2).' days to encash.');
        }

        $today = Attendance::shopNow()->toDateString();
        $amount = round($employee->dailyRate($today) * $data['days'], 2);
        $balance->encashed = round($balance->encashed + $data['days'], 2);
        $balance->save();
        $adjustment = SalaryAdjustment::create([
            'employee_id' => $employee->id, 'month' => substr($today, 0, 7), 'type' => 'encashment', 'amount' => $amount,
            'note' => $data['days'].' days '.$leavePolicy->name, 'created_by' => $this->by($request),
        ]);

        return ['balance' => $balance->fresh(), 'adjustment' => $adjustment];
    }

    /**
     * Allocate a year
     *
     * Makes every working employee's balances for `year` now (allocation and carry-forward), instead of waiting
     * until they are first used. Needs `hrm.edit`.
     */
    public function allocate(Request $request)
    {
        $data = $request->validate(['year' => 'required|integer|min:2000|max:2100']);
        $count = 0;
        foreach ($this->employeeQuery($request)->whereIn('status', Employee::WORKING)->get() as $employee) {
            $count += count(self::balancesFor($employee, (int) $data['year']));
        }

        return ['year' => (int) $data['year'], 'balances' => $count];
    }
}
