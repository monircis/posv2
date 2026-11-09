<?php

namespace App\Http\Controllers\Api\Hrm;

use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\LeaveRequestController;
use App\Models\Approval;
use App\Models\Attendance;
use App\Models\AttendanceCorrection;
use App\Models\Employee;
use App\Models\LeavePolicy;
use App\Models\LeaveRequest;
use App\Models\OvertimeRequest;
use App\Models\PayRunItem;
use Carbon\Carbon;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;

/**
 * HRM > Self-service (ESS): the signed-in user's own HR records.
 * Works for any signed-in user whose sign-in is linked to an employee (employees.user_id). No HRM permission needed:
 * it only ever shows their own data (and their team's leave calendar when they are someone's manager).
 */
#[Group('HRM self-service', 'The signed-in employee\'s own profile, attendance, leave, overtime and payslips.', weight: 23)]
class MyHrmController extends HrmController
{
    private function me(Request $request): ?Employee
    {
        return Employee::where('user_id', (string) $request->user()->id)->first();
    }

    private function notLinked()
    {
        return $this->failed('Your sign-in is not linked to an employee record. Ask HR to link it.', 404);
    }

    /** My profile (without salary details; see /me/hrm/salary). */
    public function profile(Request $request)
    {
        $me = $this->me($request);

        return $me ? EmployeeController::profile($me, false) : $this->notLinked();
    }

    /** My attendance, one row per day from..to (default this month), with the totals. */
    public function attendance(Request $request)
    {
        $me = $this->me($request);

        return $me ? AttendanceController::historyFor($me, $request->input('from'), $request->input('to')) : $this->notLinked();
    }

    /** My monthly attendance summary (?month=2026-11). */
    public function summary(Request $request)
    {
        $me = $this->me($request);
        if (! $me) {
            return $this->notLinked();
        }
        $month = $request->input('month', Attendance::shopNow()->format('Y-m'));
        $from = $month.'-01';
        $to = min(Carbon::parse($from)->endOfMonth()->toDateString(), Attendance::shopNow()->toDateString());

        return ['month' => $month] + Attendance::summaryFor($me, $from, $to);
    }

    /** My shift schedule (?from, ?to; default the next 14 days). */
    public function shifts(Request $request)
    {
        $me = $this->me($request);

        return $me ? ShiftAssignmentController::scheduleFor($me, $request->input('from'), $request->input('to')) : $this->notLinked();
    }

    /** Company holidays for my branch (?year). */
    public function holidays(Request $request)
    {
        $me = $this->me($request);

        return $me ? HolidayController::calendarFor($me->branch_id, (int) $request->input('year', now()->year), $request->input('month')) : $this->notLinked();
    }

    // ---- leave ----

    /** My leave requests, newest first, each with its approval steps. */
    public function leave(Request $request)
    {
        $me = $this->me($request);
        if (! $me) {
            return $this->notLinked();
        }

        return LeaveRequest::where('employee_id', $me->id)->orderByDesc('date_from')->limit(200)->get()
            ->map(fn ($l) => $l->toArray() + ['approvals' => Approval::history('leave', $l->id)]);
    }

    /** The leave types I can ask for (id, name, half day / hourly allowed). */
    public function leaveTypes(Request $request)
    {
        $me = $this->me($request);

        return $me ? LeavePolicy::forEmployee($me)->map(fn ($p) => $p->only('id', 'name', 'code', 'paid', 'allow_half_day', 'allow_hourly'))->values() : $this->notLinked();
    }

    /** My leave balances (?year). */
    public function balances(Request $request)
    {
        $me = $this->me($request);

        return $me ? LeaveBalanceController::balancesFor($me, (int) $request->input('year', Attendance::shopNow()->year)) : $this->notLinked();
    }

    /** Apply for leave (same fields as POST /leave-requests, without employee_id). */
    public function applyLeave(Request $request)
    {
        $me = $this->me($request);
        if (! $me) {
            return $this->notLinked();
        }
        $request->merge(['employee_id' => $me->id]);
        $data = $request->validate(LeaveRequestController::rules());

        return LeaveRequestController::apply($me, $data, $request->file('attachment'), (string) $request->user()->id);
    }

    /** Withdraw my leave request while it is pending. */
    public function cancelLeave(Request $request, LeaveRequest $leaveRequest)
    {
        $me = $this->me($request);
        if (! $me || $leaveRequest->employee_id !== $me->id) {
            return $this->failed('That is not your leave request.', 403);
        }
        if ($leaveRequest->status !== 'Pending') {
            return $this->failed('Only a waiting request can be withdrawn. Ask HR to cancel approved leave.');
        }
        $leaveRequest->update(['status' => 'Cancelled', 'decided_by' => $request->user()->name]);
        Approval::cancel('leave', $leaveRequest->id);

        return $leaveRequest->fresh();
    }

    /** Team leave calendar (?month): the people who report to me, or my department when I manage nobody. */
    public function teamLeave(Request $request)
    {
        $me = $this->me($request);
        if (! $me) {
            return $this->notLinked();
        }
        $team = Employee::where('reporting_manager_id', $me->id)->pluck('id');
        if ($team->isEmpty() && $me->department_id) {
            $team = Employee::where('department_id', $me->department_id)->pluck('id');
        }
        $team->push($me->id);

        return LeaveRequestController::calendarFor($team->unique()->values(), $request->input('month', Attendance::shopNow()->format('Y-m')));
    }

    // ---- attendance corrections ----

    /** My attendance correction requests. */
    public function corrections(Request $request)
    {
        $me = $this->me($request);

        return $me ? AttendanceCorrection::where('employee_id', $me->id)->orderByDesc('id')->limit(200)->get() : $this->notLinked();
    }

    /** Ask for an attendance correction (date, in_at, out_at, break_minutes, reason). */
    public function askCorrection(Request $request)
    {
        $me = $this->me($request);
        if (! $me) {
            return $this->notLinked();
        }
        $request->merge(['employee_id' => $me->id]);
        $data = $request->validate(AttendanceCorrectionController::rules());

        return AttendanceCorrectionController::create($me, $data, (string) $request->user()->id, $request->user()->name);
    }

    /** Withdraw my pending correction. */
    public function cancelCorrection(Request $request, AttendanceCorrection $attendanceCorrection)
    {
        $me = $this->me($request);
        if (! $me || $attendanceCorrection->employee_id !== $me->id) {
            return $this->failed('That is not your request.', 403);
        }

        return AttendanceCorrectionController::cancelIt($attendanceCorrection);
    }

    // ---- overtime ----

    /** My overtime (?month). */
    public function overtime(Request $request)
    {
        $me = $this->me($request);
        if (! $me) {
            return $this->notLinked();
        }
        $query = OvertimeRequest::where('employee_id', $me->id)->orderByDesc('date');
        if ($request->filled('month')) {
            $from = Carbon::parse($request->input('month').'-01');
            $query->whereBetween('date', [$from->toDateString(), $from->copy()->endOfMonth()->toDateString()]);
        }

        return $query->limit(200)->get();
    }

    /** Ask for overtime (date, minutes, reason). */
    public function askOvertime(Request $request)
    {
        $me = $this->me($request);
        if (! $me) {
            return $this->notLinked();
        }
        $request->merge(['employee_id' => $me->id]);
        $data = $request->validate(OvertimeRequestController::rules());

        return OvertimeRequestController::create($me, $data, (string) $request->user()->id);
    }

    // ---- salary and payslips ----

    /** My salary: the current structure and the history. */
    public function salary(Request $request)
    {
        $me = $this->me($request);
        if (! $me) {
            return $this->notLinked();
        }

        return [
            'current' => $me->salaryOn(Attendance::shopNow()->toDateString()),
            'history' => $me->salaryStructures()->get(),
            'bank' => $me->bank_name ? $me->bank_name.' ••'.substr((string) $me->bank_account_number, -4) : null,
        ];
    }

    /** My payslips (approved and paid monthly runs). */
    public function payslips(Request $request)
    {
        $me = $this->me($request);
        if (! $me) {
            return $this->notLinked();
        }

        return PayRunItem::with('payRun:id,month,status,pay_date')->where('employee_id', $me->id)
            ->whereHas('payRun', fn ($q) => $q->where('kind', 'monthly')->whereIn('status', ['approved', 'paid']))
            ->orderByDesc('id')->get(['id', 'pay_run_id', 'gross', 'deductions', 'net']);
    }

    /** Download one of my payslips (a print-ready page: Save as PDF from the browser). */
    public function payslip(Request $request, PayRunItem $payRunItem)
    {
        $me = $this->me($request);
        $run = $payRunItem->payRun;
        if (! $me || $payRunItem->employee_id !== $me->id || $run->kind !== 'monthly' || ! in_array($run->status, ['approved', 'paid'], true)) {
            return $this->failed('That is not one of your payslips.', 403);
        }

        return $request->input('format') === 'json'
            ? PayrollController::payslipData($payRunItem)
            : response(PayrollController::payslipHtml($payRunItem))->header('Content-Type', 'text/html; charset=utf-8');
    }
}
