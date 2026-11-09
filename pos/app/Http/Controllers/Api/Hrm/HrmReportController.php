<?php

namespace App\Http\Controllers\Api\Hrm;

use App\Models\Attendance;
use App\Models\AttendanceCorrection;
use App\Models\Branch;
use App\Models\OvertimeRequest;
use App\Models\Shift;
use Carbon\Carbon;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\Request;

/**
 * HRM > Attendance reports. GET /attendance-reports/{report}, e.g. /attendance-reports/late?from=2026-11-01&to=2026-11-30
 *
 * Reports, one row per employee per day: daily, late, early, absent, present, half_day, missing_punch, leave, working_hours, breaks.
 * Reports, one row per employee: monthly (and employee). One row per branch / department: branch, department.
 * From their own tables: overtime, corrections.
 *
 * Filters: from, to (at most 93 days), employee_id, branch_id, department_id, designation_id, shift_id, day_status.
 * format: json (default), csv, excel (.xls that Excel opens) or pdf (a print-ready page: use the browser's Save as PDF).
 * Needs `hrm.view`.
 */
#[Group('HRM reports', 'Attendance reports with filters and export, and the HRM dashboard.', weight: 22)]
class HrmReportController extends HrmController
{
    public const REPORTS = [
        'daily' => 'Daily attendance', 'monthly' => 'Monthly attendance', 'employee' => 'Employee-wise attendance',
        'branch' => 'Branch-wise attendance', 'department' => 'Department-wise attendance', 'late' => 'Late attendance',
        'early' => 'Early checkout', 'absent' => 'Absent employees', 'present' => 'Present employees', 'half_day' => 'Half days',
        'overtime' => 'Overtime', 'missing_punch' => 'Missing punches', 'leave' => 'Leave attendance',
        'working_hours' => 'Working hours', 'breaks' => 'Break duration', 'corrections' => 'Attendance corrections',
    ];

    private const DAY_COLUMNS = [
        'date' => 'Date', 'code' => 'Code', 'name' => 'Employee', 'branch' => 'Branch', 'department' => 'Department', 'shift' => 'Shift',
        'status' => 'Status', 'in' => 'In', 'out' => 'Out', 'worked' => 'Worked (h)', 'break' => 'Break (min)', 'late' => 'Late (min)',
        'early' => 'Early (min)', 'extra' => 'Extra (min)',
    ];

    private const SUMMARY_COLUMNS = [
        'attended' => 'Attended', 'present' => 'On time', 'late' => 'Late', 'half_day' => 'Half day', 'missing_punch' => 'Missing punch',
        'absent' => 'Absent', 'leave' => 'Leave', 'weekend' => 'Day off', 'holiday' => 'Holiday', 'worked_hours' => 'Worked (h)',
        'late_minutes' => 'Late (min)', 'early_minutes' => 'Early (min)', 'extra_minutes' => 'Extra (min)', 'attendance_percent' => 'Attendance %',
    ];

    /** The list of reports. */
    public function index()
    {
        return collect(self::REPORTS)->map(fn ($title, $key) => ['key' => $key, 'title' => $title])->values();
    }

    /**
     * Run a report
     *
     * Answers {report, title, from, to, columns: [{key, label}], rows: [...]} or the file for `format`.
     */
    #[QueryParameter('from', 'Default: the first of this month.', type: 'string', format: 'date')]
    #[QueryParameter('to', 'Default: today.', type: 'string', format: 'date')]
    #[QueryParameter('format', 'json, csv, excel or pdf.', type: 'string')]
    public function show(Request $request, string $report)
    {
        if (! isset(self::REPORTS[$report])) {
            return $this->failed('Unknown report. Reports: '.implode(', ', array_keys(self::REPORTS)).'.', 404);
        }
        $now = Attendance::shopNow();
        $from = $request->input('from', $now->copy()->startOfMonth()->toDateString());
        $to = $request->input('to', $now->toDateString());
        if ($to < $from) {
            return $this->failed('"to" is before "from".');
        }
        if (Carbon::parse($from)->diffInDays(Carbon::parse($to)) > 93) {
            return $this->failed('A report can cover at most 93 days.');
        }

        [$columns, $rows] = match ($report) {
            'monthly', 'employee' => $this->perEmployee($request, $from, $to),
            'branch', 'department' => $this->perGroup($request, $from, $to, $report),
            'overtime' => $this->overtime($request, $from, $to),
            'corrections' => $this->corrections($request, $from, $to),
            default => [self::DAY_COLUMNS, $this->dayRows($request, $from, $to, $report)],
        };

        $result = [
            'report' => $report, 'title' => self::REPORTS[$report], 'from' => $from, 'to' => $to,
            'columns' => collect($columns)->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values()->all(),
            'rows' => $rows,
        ];

        return match ($request->input('format', 'json')) {
            'csv' => $this->csv($result),
            'excel' => $this->excel($result),
            'pdf' => $this->printable($result),
            default => $result,
        };
    }

    // ---- building the rows ----

    /** Every employee-day in the range that fits the report. */
    private function dayRows(Request $request, string $from, string $to, string $report): array
    {
        $keep = [
            'daily' => fn ($d) => $d['status'] !== 'none',
            'late' => fn ($d) => $d['row'] && $d['row']->late_minutes > 0,
            'early' => fn ($d) => $d['row'] && $d['row']->early_minutes > 0,
            'absent' => fn ($d) => $d['status'] === 'absent',
            'present' => fn ($d) => in_array($d['status'], ['present', 'late', 'half_day', 'missing_punch'], true),
            'half_day' => fn ($d) => $d['status'] === 'half_day',
            'missing_punch' => fn ($d) => $d['status'] === 'missing_punch',
            'leave' => fn ($d) => $d['status'] === 'leave',
            'working_hours' => fn ($d) => $d['row'] && $d['row']->worked_minutes > 0,
            'breaks' => fn ($d) => $d['row'] && $d['row']->break_minutes > 0,
        ][$report];

        $rows = [];
        foreach ($this->employees($request) as $employee) {
            foreach (Attendance::daysFor($employee, $from, $to) as $day) {
                if (! $keep($day) || ($request->filled('day_status') && $day['status'] !== $request->input('day_status'))) {
                    continue;
                }
                $row = $day['row'];
                $shift = $row?->shift ?? Shift::forEmployee($employee, $day['date']);
                if ($request->filled('shift_id') && (int) $shift?->id !== (int) $request->input('shift_id')) {
                    continue;
                }
                $rows[] = [
                    'date' => $day['date'], 'code' => $employee->code, 'name' => $employee->name, 'branch' => $employee->branch?->name,
                    'department' => $employee->department?->name, 'shift' => $shift?->name, 'status' => $day['status'],
                    'in' => $row?->first_in_at?->format('H:i'), 'out' => $row?->last_out_at?->format('H:i'),
                    'worked' => $row ? round($row->worked_minutes / 60, 2) : 0, 'break' => $row?->break_minutes ?? 0,
                    'late' => $row?->late_minutes ?? 0, 'early' => $row?->early_minutes ?? 0, 'extra' => $row?->extra_minutes ?? 0,
                ];
            }
        }
        usort($rows, fn ($a, $b) => [$a['date'], $a['name']] <=> [$b['date'], $b['name']]);

        return $rows;
    }

    private function perEmployee(Request $request, string $from, string $to): array
    {
        $rows = [];
        foreach ($this->employees($request) as $employee) {
            $sum = Attendance::summaryFor($employee, $from, $to);
            $rows[] = ['code' => $employee->code, 'name' => $employee->name, 'branch' => $employee->branch?->name, 'department' => $employee->department?->name]
                + $this->summaryRow($sum);
        }

        return [['code' => 'Code', 'name' => 'Employee', 'branch' => 'Branch', 'department' => 'Department'] + self::SUMMARY_COLUMNS, $rows];
    }

    private function perGroup(Request $request, string $from, string $to, string $by): array
    {
        $groups = [];
        foreach ($this->employees($request) as $employee) {
            $name = $by === 'branch' ? ($employee->branch?->name ?? 'No branch') : ($employee->department?->name ?? 'No department');
            $sum = Attendance::summaryFor($employee, $from, $to);
            $group = $groups[$name] ?? ['staff' => 0] + array_fill_keys(array_keys($sum), 0);
            $group['staff']++;
            foreach ($sum as $key => $value) {
                $group[$key] += (float) $value;
            }
            $groups[$name] = $group;
        }

        $rows = [];
        ksort($groups);
        foreach ($groups as $name => $sum) {
            $workdays = $sum['attended'] + $sum['absent'] + $sum['leave'];
            $sum['attendance_percent'] = $workdays ? round($sum['attended'] / $workdays * 100, 1) : null;
            $rows[] = ['group' => $name, 'staff' => $sum['staff']] + $this->summaryRow($sum);
        }

        return [['group' => $by === 'branch' ? 'Branch' : 'Department', 'staff' => 'Staff'] + self::SUMMARY_COLUMNS, $rows];
    }

    private function summaryRow(array $sum): array
    {
        $out = [];
        foreach (array_keys(self::SUMMARY_COLUMNS) as $key) {
            $out[$key] = $key === 'worked_hours' ? round($sum['worked_minutes'] / 60, 2) : $sum[$key];
        }

        return $out;
    }

    private function overtime(Request $request, string $from, string $to): array
    {
        $query = OvertimeRequest::with('employee:id,code,first,last')->whereIn('employee_id', $this->employeeIds($request))
            ->whereBetween('date', [$from, $to])->orderBy('date');
        if ($request->filled('overtime_status')) {
            $query->where('status', $request->input('overtime_status'));
        }
        $rows = $query->get()->map(fn ($o) => [
            'ref' => $o->ref, 'date' => $o->date->toDateString(), 'code' => $o->employee?->code, 'name' => $o->employee?->name,
            'day_type' => $o->day_type, 'hours' => round($o->minutes / 60, 2), 'rate' => $o->rate, 'amount' => $o->amount,
            'source' => $o->source, 'status' => $o->status,
        ])->all();

        return [['ref' => 'Ref', 'date' => 'Date', 'code' => 'Code', 'name' => 'Employee', 'day_type' => 'Day', 'hours' => 'Hours',
            'rate' => 'Rate', 'amount' => 'Amount', 'source' => 'Source', 'status' => 'Status'], $rows];
    }

    private function corrections(Request $request, string $from, string $to): array
    {
        $rows = AttendanceCorrection::with('employee:id,code,first,last')->whereIn('employee_id', $this->employeeIds($request))
            ->whereBetween('date', [$from, $to])->orderBy('date')->get()->map(fn ($c) => [
                'ref' => $c->ref, 'date' => $c->date->toDateString(), 'code' => $c->employee?->code, 'name' => $c->employee?->name,
                'in' => $c->in_at->format('H:i'), 'out' => $c->out_at?->format('H:i'), 'reason' => $c->reason, 'status' => $c->status,
                'asked_by' => $c->created_by,
            ])->all();

        return [['ref' => 'Ref', 'date' => 'Date', 'code' => 'Code', 'name' => 'Employee', 'in' => 'In', 'out' => 'Out',
            'reason' => 'Reason', 'status' => 'Status', 'asked_by' => 'Asked by'], $rows];
    }

    private function employees(Request $request)
    {
        return $this->employeeQuery($request)->with(['branch:id,name', 'department:id,name'])->orderBy('first')->get();
    }

    // ---- export ----

    private function fileName(array $result, string $ext): string
    {
        return str_replace('_', '-', $result['report']).'-'.$result['from'].'-to-'.$result['to'].'.'.$ext;
    }

    private function csv(array $result)
    {
        $out = fopen('php://temp', 'r+');
        fputcsv($out, array_column($result['columns'], 'label'));
        foreach ($result['rows'] as $row) {
            fputcsv($out, array_map(fn ($c) => $row[$c['key']] ?? '', $result['columns']));
        }
        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return response($csv)->header('Content-Type', 'text/csv; charset=utf-8')
            ->header('Content-Disposition', 'attachment; filename="'.$this->fileName($result, 'csv').'"');
    }

    private function table(array $result): string
    {
        $e = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES);
        $html = '<table border="1" cellspacing="0" cellpadding="4"><thead><tr>';
        foreach ($result['columns'] as $column) {
            $html .= '<th>'.$e($column['label']).'</th>';
        }
        $html .= '</tr></thead><tbody>';
        foreach ($result['rows'] as $row) {
            $html .= '<tr>';
            foreach ($result['columns'] as $column) {
                $html .= '<td>'.$e($row[$column['key']] ?? '').'</td>';
            }
            $html .= '</tr>';
        }

        return $html.'</tbody></table>';
    }

    /** An HTML table saved as .xls: Excel opens it as a sheet. */
    private function excel(array $result)
    {
        $html = '<html><head><meta charset="utf-8"></head><body>'.$this->table($result).'</body></html>';

        return response($html)->header('Content-Type', 'application/vnd.ms-excel; charset=utf-8')
            ->header('Content-Disposition', 'attachment; filename="'.$this->fileName($result, 'xls').'"');
    }

    /** A print-ready page; the browser's print dialog saves it as PDF. */
    private function printable(array $result)
    {
        $e = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES);
        $branch = request()->filled('branch_id') ? Branch::whereKey(request('branch_id'))->value('name') : null;
        $html = '<!doctype html><html><head><meta charset="utf-8"><title>'.$e($result['title']).'</title><style>'
            .'body{font-family:Arial,sans-serif;font-size:11px;margin:18px}h1{font-size:17px;margin:0 0 4px}table{border-collapse:collapse;width:100%;margin-top:10px}'
            .'th{background:#eee;text-align:left}th,td{border:1px solid #ccc;padding:3px 5px}@media print{button{display:none}}@page{size:landscape}'
            .'</style></head><body><button onclick="window.print()">Print / Save as PDF</button>'
            .'<h1>'.$e($result['title']).'</h1><div>'.$e(Carbon::parse($result['from'])->format('d/m/Y')).' to '.$e(Carbon::parse($result['to'])->format('d/m/Y'))
            .($branch ? ' · '.$e($branch) : '').' · '.count($result['rows']).' rows</div>'.$this->table($result).'</body></html>';

        return response($html)->header('Content-Type', 'text/html; charset=utf-8');
    }
}
