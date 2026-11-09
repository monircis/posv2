<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Hrm\HrmController;
use App\Models\Attendance;
use App\Models\Employee;
use Carbon\Carbon;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\Request;

/**
 * HRM > Attendance. Each clock-in, clock-out and break is a punch; the day's totals are worked out from them.
 * Times are the shop's own clock time. A second punch within a few minutes of the last one is refused
 * (settings: duplicate_punch_minutes). A night shift's punches belong to the day it started.
 */
#[Group('HRM', weight: 18)]
class AttendanceController extends HrmController
{
    /**
     * Attendance for one day
     *
     * The rows that exist for that day (employee_id, in, out, absent and the worked-out totals).
     * A user with a branch only sees staff of that branch. Needs `hrm.view`.
     */
    #[QueryParameter('date', 'The day, e.g. 2026-11-04. Default: today.', type: 'string', format: 'date')]
    public function index(Request $request)
    {
        return Attendance::whereDate('date', $request->input('date', Attendance::shopNow()->toDateString()))
            ->whereIn('employee_id', $this->employeeIds($request))
            ->orderBy('employee_id')
            ->get(['id', 'employee_id', 'in', 'out', 'absent', 'status', 'day_type', 'first_in_at', 'last_out_at', 'worked_minutes',
                'break_minutes', 'late_minutes', 'early_minutes', 'extra_minutes', 'remote', 'corrected']);
    }

    /**
     * Daily board
     *
     * Every working employee with their status for the day (absent, leave, day off and holiday included).
     * Filters: branch_id, department_id, designation_id, day_status (e.g. absent, late). Needs `hrm.view`.
     */
    #[QueryParameter('date', 'Default: today.', type: 'string', format: 'date')]
    public function daily(Request $request)
    {
        $date = $request->input('date', Attendance::shopNow()->toDateString());
        $filter = $request->input('day_status');

        $out = [];
        foreach ($this->employeeQuery($request)->whereIn('status', Employee::WORKING)->with('department:id,name')->orderBy('first')->get() as $employee) {
            $day = Attendance::daysFor($employee, $date, $date)[0];
            if ($filter && $day['status'] !== $filter) {
                continue;
            }
            $out[] = [
                'employee_id' => $employee->id, 'code' => $employee->code, 'name' => $employee->name, 'department' => $employee->department?->name,
                'status' => $day['status'], 'attendance' => $day['row'],
            ];
        }

        return $out;
    }

    /**
     * Punch
     *
     * `type`: in, out, break_start or break_end. `at` (shop time, "2026-11-19 09:02") defaults to now.
     * Refused within the duplicate-punch interval, or out of order (out before in). Needs `hrm.edit`.
     */
    public function punch(Request $request)
    {
        $data = $request->validate([
            'employee_id' => 'required|integer',
            'type' => 'required|in:'.implode(',', Attendance::PUNCH_TYPES),
            'at' => 'nullable|date',
            'remote' => 'boolean',
            'source' => 'nullable|in:pos,kiosk,manual',
        ]);
        $employee = $this->findEmployee($request, $data['employee_id']);
        if (! $employee) {
            return $this->notYourBranch();
        }

        return $this->doPunch($employee, $data['type'], isset($data['at']) ? Carbon::parse($data['at']) : Attendance::shopNow(), $data['source'] ?? 'manual', (bool) ($data['remote'] ?? false), $this->by($request));
    }

    /** Shared by punch(), clock() and self-service. */
    public static function doPunch(Employee $employee, string $type, Carbon $at, string $source, bool $remote, ?string $by)
    {
        if ($employee->status === 'Inactive' || ! $employee->employedOn($at->toDateString())) {
            return response()->json(['message' => $employee->first.' is not working here on that day.'], 422);
        }
        if ($at->gt(Attendance::shopNow()->addMinutes(5))) {
            return response()->json(['message' => 'A punch cannot be in the future.'], 422);
        }

        $result = Attendance::punch($employee, $type, $at, $source, $remote, $by);
        if (is_string($result)) {
            return response()->json(['message' => $result], 422);
        }

        return $result->fresh()->load('punches', 'breaks');
    }

    /**
     * Clock in, clock out or mark absent (the HRM screen)
     *
     * `action` is in, out, absent or undo. `minutes` is the time after midnight, from the shop's own clock.
     * In and out are saved as punches. Needs `hrm.edit`.
     */
    public function clock(Request $request)
    {
        $data = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'date' => 'required|date',
            'action' => 'required|in:in,out,absent,undo',
            'minutes' => 'required_if:action,in,out|nullable|integer|min:0|max:1439',
        ]);

        $employee = $this->findEmployee($request, $data['employee_id']);
        if (! $employee) {
            return $this->notYourBranch();
        }

        if (in_array($data['action'], ['in', 'out'], true)) {
            $response = self::doPunch($employee, $data['action'], Carbon::parse($data['date'])->addMinutes($data['minutes']), 'manual', false, $this->by($request));
            if ($response instanceof Attendance) {
                return $response->only('employee_id', 'in', 'out', 'absent', 'status');
            }

            return $response;
        }

        $row = Attendance::firstOrNew(['employee_id' => $employee->id, 'date' => $data['date']], ['branch_id' => $employee->branch_id]);
        if ($row->first_in_at) {
            return $this->failed($employee->first.' has clocked in on that day.');
        }
        $row->absent = $data['action'] === 'absent';
        $row->status = $row->absent ? 'absent' : null;
        $row->save();

        return $row->only('employee_id', 'in', 'out', 'absent', 'status');
    }

    /**
     * One day in detail
     *
     * The attendance with its punches and breaks. Needs `hrm.view`.
     */
    public function show(Request $request, Attendance $attendance)
    {
        if (! $this->canSeeEmployee($request, $attendance->employee)) {
            return $this->notYourBranch();
        }

        return $attendance->load('punches', 'breaks', 'shift:id,name,code,start_time,end_time');
    }

    /**
     * Manual correction
     *
     * Sets a day's clock-in (in_at), clock-out (out_at) and break minutes directly, replacing its punches.
     * break_minutes null keeps the breaks that are there. Needs `hrm.edit`.
     */
    public function manual(Request $request)
    {
        $data = $request->validate([
            'employee_id' => 'required|integer',
            'date' => 'required|date',
            'in_at' => 'required|date',
            'out_at' => 'nullable|date|after:in_at',
            'break_minutes' => 'nullable|integer|min:0|max:600',
            'note' => 'nullable|string|max:255',
        ]);
        $employee = $this->findEmployee($request, $data['employee_id']);
        if (! $employee) {
            return $this->notYourBranch();
        }
        $problem = self::correctionProblem($data);
        if ($problem) {
            return $this->failed($problem);
        }

        $row = Attendance::firstOrCreate(['employee_id' => $employee->id, 'date' => $data['date']], ['branch_id' => $employee->branch_id]);
        $row->replacePunches(Carbon::parse($data['in_at']), isset($data['out_at']) ? Carbon::parse($data['out_at']) : null, $data['break_minutes'] ?? null, 'manual', $this->by($request));
        if (! empty($data['note'])) {
            $row->update(['note' => $data['note']]);
        }

        return $row->fresh()->load('punches', 'breaks');
    }

    /** Checks the times of a correction belong to that day. */
    public static function correctionProblem(array $data): ?string
    {
        $date = Carbon::parse($data['date']);
        $in = Carbon::parse($data['in_at']);
        if ($in->lt($date) || $in->gte($date->copy()->addDay())) {
            return 'The clock-in must be on '.$date->format('d/m/Y').'.';
        }
        if (! empty($data['out_at']) && Carbon::parse($data['out_at'])->gt($in->copy()->addHours(24))) {
            return 'A day cannot be longer than 24 hours.';
        }
        if (! empty($data['out_at']) && isset($data['break_minutes']) && $data['break_minutes'] >= $in->diffInMinutes(Carbon::parse($data['out_at']))) {
            return 'The break is longer than the day.';
        }
        if ($in->gt(Attendance::shopNow())) {
            return 'That day has not happened yet.';
        }

        return null;
    }

    /**
     * Attendance history of an employee
     *
     * One row per day from..to (at most 93 days) with the status and the attendance, plus the totals.
     * Needs `hrm.view`.
     */
    #[QueryParameter('from', 'Default: the first of this month.', type: 'string', format: 'date')]
    #[QueryParameter('to', 'Default: today.', type: 'string', format: 'date')]
    public function history(Request $request, Employee $employee)
    {
        if (! $this->canSeeEmployee($request, $employee)) {
            return $this->notYourBranch();
        }

        return self::historyFor($employee, $request->input('from'), $request->input('to'));
    }

    public static function historyFor(Employee $employee, ?string $from, ?string $to): array
    {
        $now = Attendance::shopNow();
        $from = $from ?: $now->copy()->startOfMonth()->toDateString();
        $to = $to ?: $now->toDateString();
        if ($to < $from || Carbon::parse($from)->diffInDays(Carbon::parse($to)) > 93) {
            $to = Carbon::parse($from)->addDays(93)->toDateString();
        }

        return [
            'from' => $from, 'to' => $to,
            'days' => Attendance::daysFor($employee, $from, $to),
            'summary' => Attendance::summaryFor($employee, $from, $to),
        ];
    }

    /**
     * Monthly attendance summary
     *
     * One row per employee for a month: days present, late, half day, absent, on leave, days off, holidays,
     * worked / late / early / break / extra minutes and the attendance percent. Filters as the employee list. Needs `hrm.view`.
     */
    #[QueryParameter('month', 'e.g. 2026-11. Default: this month.', type: 'string')]
    public function summary(Request $request)
    {
        $month = $request->input('month', Attendance::shopNow()->format('Y-m'));
        $from = $month.'-01';
        $to = min(Carbon::parse($from)->endOfMonth()->toDateString(), Attendance::shopNow()->toDateString());

        $out = [];
        foreach ($this->employeeQuery($request)->with('department:id,name')->orderBy('first')->get() as $employee) {
            $out[] = ['employee_id' => $employee->id, 'code' => $employee->code, 'name' => $employee->name, 'department' => $employee->department?->name]
                + Attendance::summaryFor($employee, $from, $to);
        }

        return ['month' => $month, 'rows' => $out];
    }
}
