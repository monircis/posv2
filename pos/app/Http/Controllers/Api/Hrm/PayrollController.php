<?php

namespace App\Http\Controllers\Api\Hrm;

use App\Models\Approval;
use App\Models\Employee;
use App\Models\PayRun;
use App\Models\PayRunItem;
use Carbon\Carbon;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * HRM > Monthly payroll. Reuses the pay_runs table (kind "monthly"); the fortnight pay run (/pay-runs) is unchanged.
 * draft (made, can be recalculated) -> pending (in the "payroll" approval workflow) -> approved -> paid.
 * Net = basic + allowances + overtime + bonus - deductions - advance - loan.
 * Marking it paid records it; it does not move money. Needs `hrm.payroll`.
 */
#[Group('HRM payroll', weight: 21)]
class PayrollController extends HrmController
{
    /** List monthly pay runs with their totals, newest first. Needs `hrm.payroll`. */
    public function index(Request $request)
    {
        $query = PayRun::where('kind', 'monthly')->with('branch:id,name')->orderByDesc('month')->orderByDesc('id');
        $locked = $this->lockedBranch($request);
        if ($locked) {
            $query->where('branch_id', $locked);
        }

        return $query->get()->map(fn ($run) => $run->toArray() + $this->totals($run))->all();
    }

    /**
     * Make a month's payroll
     *
     * One line per employee with a salary (at branch_id, or all branches). Refused when that month already has a run
     * for that branch. Needs `hrm.payroll`.
     */
    public function store(Request $request)
    {
        $data = $request->validate(['month' => 'required|date_format:Y-m', 'branch_id' => 'nullable|exists:branches,id']);
        $branchId = $this->lockedBranch($request) ?? ($data['branch_id'] ?? null);
        if (! $this->isAtBranch($request, $branchId)) {
            return $this->notYourBranch();
        }
        $exists = PayRun::where('kind', 'monthly')->where('month', $data['month'])
            ->where(fn ($q) => $branchId ? $q->where('branch_id', $branchId)->orWhereNull('branch_id') : $q)->first();
        if ($exists) {
            return $this->failed('There is already a payroll for '.$data['month'].($exists->branch_id ? '' : ' (all branches)').': PR-'.$exists->id.'.');
        }

        [$from, $to] = PayRun::monthRange($data['month']);
        $run = DB::transaction(function () use ($data, $branchId, $from, $to) {
            $run = PayRun::create([
                'kind' => 'monthly', 'month' => $data['month'], 'branch_id' => $branchId, 'period_from' => $from, 'period_to' => $to,
                'pay_date' => $to, 'status' => 'draft',
            ]);
            $run->fillMonthly();

            return $run;
        });

        return response()->json($this->detail($run), 201);
    }

    /** A monthly run with every line and its approval history. Needs `hrm.payroll`. */
    public function show(Request $request, PayRun $payRun)
    {
        if ($payRun->kind !== 'monthly') {
            return $this->failed('Not found.', 404);
        }
        if ($this->lockedBranch($request) && ! $this->isAtBranch($request, $payRun->branch_id)) {
            return $this->notYourBranch();
        }

        return $this->detail($payRun);
    }

    /** Work a draft run out again (after changes to salaries, overtime or deductions). Needs `hrm.payroll`. */
    public function recalculate(Request $request, PayRun $payRun)
    {
        $problem = $this->check($request, $payRun, 'draft');
        if ($problem) {
            return $problem;
        }
        DB::transaction(fn () => $payRun->fillMonthly());

        return $this->detail($payRun->fresh());
    }

    /** Send a draft run for approval ("payroll" workflow). Needs `hrm.payroll`. */
    public function submit(Request $request, PayRun $payRun)
    {
        $problem = $this->check($request, $payRun, 'draft');
        if ($problem) {
            return $problem;
        }
        if (! $payRun->items()->exists()) {
            return $this->failed('This payroll has nobody in it.');
        }
        DB::transaction(function () use ($payRun) {
            $payRun->fillMonthly();
            $payRun->update(['status' => 'pending']);
            Approval::start('payroll', $payRun);
        });

        return $this->detail($payRun->fresh());
    }

    /** Mark an approved run as paid: its overtime, deductions, adjustments and loan instalments are settled. Needs `hrm.payroll`. */
    public function pay(Request $request, PayRun $payRun)
    {
        $problem = $this->check($request, $payRun, 'approved');
        if ($problem) {
            return $problem;
        }
        DB::transaction(function () use ($request, $payRun) {
            $payRun->settleMonthly();
            $payRun->update(['status' => 'paid', 'paid_by' => $this->by($request), 'paid_at' => now()]);
        });

        return $this->detail($payRun->fresh());
    }

    /** Delete a draft run. Needs `hrm.payroll`. */
    public function destroy(Request $request, PayRun $payRun)
    {
        $problem = $this->check($request, $payRun, 'draft');
        if ($problem) {
            return $problem;
        }
        $payRun->delete();

        return response()->noContent();
    }

    /**
     * Payroll report
     *
     * Totals per month of a year (paid and approved runs), and per department for one month (?month=). Needs `hrm.payroll`.
     */
    public function report(Request $request)
    {
        $year = (int) $request->input('year', now()->year);
        $runs = PayRun::where('kind', 'monthly')->whereIn('status', ['approved', 'paid'])->where('month', 'like', $year.'-%');
        $locked = $this->lockedBranch($request);
        if ($locked) {
            $runs->where('branch_id', $locked);
        }
        $runs = $runs->get();

        $months = [];
        foreach ($runs as $run) {
            $totals = $this->totals($run);
            $month = $months[$run->month] ?? ['month' => $run->month, 'staff' => 0, 'gross' => 0, 'deductions' => 0, 'net' => 0];
            foreach (['staff', 'gross', 'deductions', 'net'] as $key) {
                $month[$key] = round($month[$key] + $totals[$key], 2);
            }
            $months[$run->month] = $month;
        }
        ksort($months);

        $departments = [];
        if ($request->filled('month')) {
            $items = PayRunItem::with('employee.department:id,name')->whereIn('pay_run_id', $runs->where('month', $request->input('month'))->pluck('id'))->get();
            foreach ($items->groupBy(fn ($i) => $i->employee?->department?->name ?? 'No department') as $name => $list) {
                $departments[] = ['department' => $name, 'staff' => $list->count(), 'gross' => round($list->sum('gross'), 2), 'net' => round($list->sum('net'), 2)];
            }
        }

        return ['year' => $year, 'months' => array_values($months), 'departments' => $departments];
    }

    // ---- payslips ----

    /** Payslips: ?pay_run_id= or ?employee_id= (monthly runs). Needs `hrm.payroll`. */
    public function payslips(Request $request)
    {
        $query = PayRunItem::with('payRun:id,month,status,pay_date', 'employee:id,code,first,last')
            ->whereIn('employee_id', $this->employeeIds($request))
            ->whereHas('payRun', fn ($q) => $q->where('kind', 'monthly'))
            ->orderByDesc('id');
        if ($request->filled('pay_run_id')) {
            $query->where('pay_run_id', $request->input('pay_run_id'));
        }

        return $query->limit(500)->get();
    }

    /** One payslip. Needs `hrm.payroll`. */
    public function payslip(Request $request, PayRunItem $payRunItem)
    {
        if (! $this->canSeeEmployee($request, $payRunItem->employee)) {
            return $this->notYourBranch();
        }

        return self::payslipData($payRunItem);
    }

    /**
     * Printable payslip
     *
     * The payslip as an HTML page, ready to print or save as PDF from the browser. Needs `hrm.payroll`.
     */
    public function printPayslip(Request $request, PayRunItem $payRunItem)
    {
        if (! $this->canSeeEmployee($request, $payRunItem->employee)) {
            return $this->notYourBranch();
        }

        return response(self::payslipHtml($payRunItem))->header('Content-Type', 'text/html; charset=utf-8');
    }

    public static function payslipData(PayRunItem $item): array
    {
        $item->load('payRun', 'employee.department:id,name', 'employee.designation:id,name', 'employee.branch:id,name');
        $employee = $item->employee;
        $run = $item->payRun;

        return [
            'id' => $item->id,
            'month' => $run->month ?? $run->period_from->format('Y-m'),
            'period_from' => $run->period_from->toDateString(),
            'period_to' => $run->period_to->toDateString(),
            'pay_date' => $run->pay_date->toDateString(),
            'status' => $run->status,
            'employee' => [
                'id' => $employee->id, 'code' => $employee->code, 'name' => $employee->name, 'branch' => $employee->branch?->name,
                'department' => $employee->department?->name, 'designation' => $employee->designation?->name ?? $employee->role,
                'bank' => $employee->bank_name ? $employee->bank_name.' ••'.substr((string) $employee->bank_account_number, -4) : null,
            ],
            'lines' => $item->lines ?? [['name' => 'Gross', 'amount' => $item->gross], ['name' => 'Tax', 'amount' => -$item->tax]],
            'basic' => $item->basic, 'allowances' => $item->allowances, 'overtime' => $item->overtime, 'bonus' => $item->bonus,
            'deductions' => $item->deductions, 'advance' => $item->advance, 'loan' => $item->loan,
            'gross' => $item->gross, 'net' => $item->net,
        ];
    }

    public static function payslipHtml(PayRunItem $item): string
    {
        $p = self::payslipData($item);
        $e = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES);
        $money = fn ($v) => ($v < 0 ? '-' : '').'$'.number_format(abs($v), 2);
        $rows = '';
        foreach ($p['lines'] as $line) {
            $rows .= '<tr><td>'.$e($line['name']).'</td><td class="r">'.$money($line['amount']).'</td></tr>';
        }
        $title = 'Payslip '.Carbon::parse($p['month'].'-01')->format('F Y').' - '.$p['employee']['name'];

        return '<!doctype html><html><head><meta charset="utf-8"><title>'.$e($title).'</title><style>'
            .'body{font-family:Arial,sans-serif;font-size:13px;color:#111;max-width:640px;margin:24px auto}h1{font-size:20px;margin:0 0 4px}'
            .'table{width:100%;border-collapse:collapse;margin-top:14px}td{padding:6px 4px;border-bottom:1px solid #ddd}.r{text-align:right}'
            .'.net td{font-weight:bold;font-size:15px;border-top:2px solid #111}.muted{color:#666}@media print{button{display:none}}'
            .'</style></head><body><button onclick="window.print()">Print / Save as PDF</button>'
            .'<h1>'.$e(config('app.name')).' - Payslip</h1><div class="muted">'.$e(Carbon::parse($p['period_from'])->format('d/m/Y')).' to '.$e(Carbon::parse($p['period_to'])->format('d/m/Y')).' · paid '.$e(Carbon::parse($p['pay_date'])->format('d/m/Y')).'</div>'
            .'<table><tr><td>Employee</td><td class="r">'.$e($p['employee']['name']).' ('.$e($p['employee']['code']).')</td></tr>'
            .'<tr><td>Branch / department</td><td class="r">'.$e($p['employee']['branch']).' / '.$e($p['employee']['department']).'</td></tr>'
            .'<tr><td>Designation</td><td class="r">'.$e($p['employee']['designation']).'</td></tr>'
            .($p['employee']['bank'] ? '<tr><td>Paid to</td><td class="r">'.$e($p['employee']['bank']).'</td></tr>' : '')
            .'</table><table>'.$rows.'<tr><td>Gross</td><td class="r">'.$money($p['gross']).'</td></tr>'
            .'<tr class="net"><td>Net pay</td><td class="r">'.$money($p['net']).'</td></tr></table></body></html>';
    }

    // ---------------------------------------------------------------------

    private function check(Request $request, PayRun $payRun, string $status)
    {
        if ($payRun->kind !== 'monthly') {
            return $this->failed('Not found.', 404);
        }
        if ($this->lockedBranch($request) && ! $this->isAtBranch($request, $payRun->branch_id)) {
            return $this->notYourBranch();
        }
        if ($payRun->status !== $status) {
            return $this->failed('This payroll is '.$payRun->status.'; it must be '.$status.' for that.');
        }

        return null;
    }

    private function totals(PayRun $run): array
    {
        $items = $run->items()->get();

        return [
            'staff' => $items->count(), 'gross' => round($items->sum('gross'), 2),
            'deductions' => round($items->sum('deductions') + $items->sum('advance') + $items->sum('loan'), 2), 'net' => round($items->sum('net'), 2),
        ];
    }

    private function detail(PayRun $run): array
    {
        $items = $run->items()->with('employee:id,code,first,last,department_id')->get();

        return $run->load('branch:id,name')->toArray() + $this->totals($run) + [
            'items' => $items,
            'approvals' => Approval::history('payroll', $run->id),
        ];
    }
}
