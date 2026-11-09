<?php

namespace App\Http\Controllers\Api\Hrm;

use App\Models\Attendance;
use App\Models\AttendanceCorrection;
use App\Models\LeaveRequest;
use App\Models\OvertimeRequest;
use Carbon\Carbon;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;

/**
 * HRM > Dashboard. All numbers are for the user's branch (or ?branch_id, or all branches when they may see all).
 * Needs `hrm.view`.
 */
#[Group('HRM reports', weight: 22)]
class HrmDashboardController extends HrmController
{
    /** HRM dashboard numbers. */
    public function index(Request $request)
    {
        $now = Attendance::shopNow();
        $today = $now->toDateString();
        $monthFrom = $now->copy()->startOfMonth()->toDateString();

        $all = $this->employeeQuery($request)->with(['branch:id,name', 'department:id,name'])->get();
        $working = $all->filter(fn ($e) => $e->isWorking());
        $ids = $working->pluck('id');

        // Today
        $todayCount = ['present' => 0, 'late' => 0, 'absent' => 0, 'leave' => 0, 'not_in_yet' => 0, 'remote' => 0, 'day_off' => 0];
        $monthAttended = 0;
        $monthWorkdays = 0;
        foreach ($working as $employee) {
            $day = Attendance::daysFor($employee, $today, $today)[0];
            $status = $day['status'];
            if (in_array($status, ['present', 'late', 'half_day', 'missing_punch'], true)) {
                $todayCount['present']++;
            }
            if ($status === 'late') {
                $todayCount['late']++;
            }
            if (in_array($status, ['absent', 'leave', 'not_in_yet'], true)) {
                $todayCount[$status]++;
            }
            if (in_array($status, ['weekend', 'holiday'], true)) {
                $todayCount['day_off']++;
            }
            if ($day['row']?->remote) {
                $todayCount['remote']++;
            }
            $sum = Attendance::summaryFor($employee, $monthFrom, $today);
            $monthAttended += $sum['attended'];
            $monthWorkdays += $sum['attended'] + $sum['absent'] + $sum['leave'];
        }

        $overtimeMinutes = (int) OvertimeRequest::whereIn('employee_id', $ids)->where('status', 'approved')->whereBetween('date', [$monthFrom, $today])->sum('minutes');

        return [
            'date' => $today,
            'employees' => [
                'total' => $all->count(),
                'active' => $working->count(),
                'inactive' => $all->count() - $working->count(),
                'on_probation' => $working->where('status', 'Probation')->count(),
            ],
            'today' => [
                'present' => $todayCount['present'], 'late' => $todayCount['late'], 'absent' => $todayCount['absent'],
                'on_leave' => $todayCount['leave'], 'not_in_yet' => $todayCount['not_in_yet'], 'remote' => $todayCount['remote'],
                'day_off' => $todayCount['day_off'],
            ],
            'month' => [
                'overtime_hours' => round($overtimeMinutes / 60, 2),
                'attendance_percent' => $monthWorkdays ? round($monthAttended / $monthWorkdays * 100, 1) : null,
            ],
            'by_department' => $working->groupBy(fn ($e) => $e->department?->name ?? 'No department')->map->count(),
            'by_branch' => $working->groupBy(fn ($e) => $e->branch?->name ?? 'No branch')->map->count(),
            'birthdays' => $this->upcoming($working, 'dob', $now),
            'anniversaries' => $this->upcoming($working, 'start', $now),
            'pending' => [
                'leave' => LeaveRequest::whereIn('employee_id', $ids)->where('status', 'Pending')->count(),
                'corrections' => AttendanceCorrection::whereIn('employee_id', $ids)->where('status', 'pending')->count(),
                'overtime' => OvertimeRequest::whereIn('employee_id', $ids)->where('status', 'pending')->count(),
            ],
        ];
    }

    /** Birthdays (dob) or work anniversaries (start) in the next 30 days. */
    private function upcoming($employees, string $field, Carbon $now): array
    {
        $out = [];
        foreach ($employees as $employee) {
            $raw = $field === 'start' ? $employee->start?->toDateString() : $employee->dob;
            $date = $this->parseDate($raw);
            if (! $date) {
                continue;
            }
            $next = $date->copy()->year($now->year);
            if ($next->lt($now->copy()->startOfDay())) {
                $next->addYear();
            }
            $days = (int) $now->copy()->startOfDay()->diffInDays($next);
            $years = $next->year - $date->year;
            if ($days <= 30 && ($field === 'dob' || $years > 0)) {
                $out[] = ['employee_id' => $employee->id, 'name' => $employee->name, 'date' => $next->toDateString(), 'in_days' => $days, 'years' => $years];
            }
        }
        usort($out, fn ($a, $b) => $a['in_days'] <=> $b['in_days']);

        return $out;
    }

    /** dob is free text on older records: "1990-05-14" or "14/05/1990". */
    private function parseDate(?string $value): ?Carbon
    {
        if (! $value) {
            return null;
        }
        foreach (['Y-m-d', 'd/m/Y'] as $format) {
            if (Carbon::canBeCreatedFromFormat($value, $format)) {
                return Carbon::createFromFormat($format, $value)->startOfDay();
            }
        }

        return null;
    }
}
