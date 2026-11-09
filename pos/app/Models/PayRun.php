<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * HRM: a pay run.
 * - kind "fortnight": the HRM > Payroll screen, as before. draft, then approved (hours locked), then paid.
 * - kind "monthly":   made from salary structures, overtime, deductions, bonuses, advances and loans.
 *   draft -> pending (in the "payroll" approval workflow) -> approved -> paid. A rejected run goes back to draft.
 *   Net = basic + allowances + overtime + bonus - deductions - advance - loan.
 */
class PayRun extends Model
{
    /** Superannuation, percent. */
    public const SUPER_PERCENT = 12;

    protected $table = 'pay_runs';

    protected $hidden = ['created_at', 'updated_at'];

    protected $fillable = ['kind', 'month', 'branch_id', 'period_from', 'period_to', 'pay_date', 'status', 'approved_by', 'paid_by', 'paid_at'];

    protected function casts(): array
    {
        return [
            'branch_id' => 'integer',
            'period_from' => 'date:Y-m-d',
            'period_to' => 'date:Y-m-d',
            'pay_date' => 'date:Y-m-d',
            'paid_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(PayRunItem::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * The same sums as payRows() in the HRM screen. Overtime is paid at 1.5.
     * The tax here is the screen's simple estimate, not the ATO tax table.
     */
    public static function amounts(Employee $employee, float $ordHours, float $otHours): array
    {
        $hourly = $employee->basis === 'Salary' ? $employee->rate / 52 / 38 : $employee->rate;
        $base = $employee->basis === 'Salary' ? $employee->rate / 26 : $ordHours * $hourly;
        $gross = round($base + $otHours * $hourly * 1.5, 2);
        $tax = max(0, round(($gross - 700) * 0.21 + ($gross > 3000 ? ($gross - 3000) * 0.12 : 0), 2));

        return [
            'gross' => $gross,
            'tax' => $tax,
            'super' => round($gross * self::SUPER_PERCENT / 100, 2),
            'net' => round($gross - $tax, 2),
        ];
    }

    // ---- monthly payroll ----

    /** The employees this monthly run pays: working at the branch, or left during the month. */
    public function monthlyEmployees()
    {
        $from = $this->period_from->toDateString();
        $query = Employee::query()->where(fn ($q) => $q->whereIn('status', Employee::WORKING)->orWhere('exit_date', '>=', $from));
        if ($this->branch_id) {
            $query->where('branch_id', $this->branch_id);
        }

        return $query->orderBy('id')->get()->filter(fn ($e) => ! $e->start || $e->start->toDateString() <= $this->period_to->toDateString());
    }

    /** (Re)works out every line of a monthly draft run. Lines keep their id (the payslip id) when worked out again. */
    public function fillMonthly(): void
    {
        $keep = [];
        $to = $this->period_to->toDateString();
        $notPaidElsewhere = fn ($q) => $q->whereNull('pay_run_id')->orWhere('pay_run_id', $this->id);

        foreach ($this->monthlyEmployees() as $employee) {
            $basic = $employee->monthlyBasic($to);
            if ($basic <= 0) {
                continue;
            }
            $salary = $employee->salaryOn($to);
            $lines = [['Basic', $basic]];
            foreach ($salary ? [['House rent', $salary->house_rent], ['Medical', $salary->medical], ['Transport', $salary->transport]] : [] as $line) {
                if ($line[1] > 0) {
                    $lines[] = $line;
                }
            }
            foreach ($salary?->other_allowances ?? [] as $a) {
                $lines[] = [$a['name'] ?? 'Allowance', (float) ($a['amount'] ?? 0)];
            }
            $allowances = $salary ? $salary->allowances_total : 0;

            $overtime = round((float) OvertimeRequest::where('employee_id', $employee->id)->where('status', 'approved')
                ->whereBetween('date', [$this->period_from->toDateString(), $to])->where($notPaidElsewhere)->sum('amount'), 2);
            if ($overtime > 0) {
                $lines[] = ['Overtime', $overtime];
            }

            $adjustments = SalaryAdjustment::where('employee_id', $employee->id)->where('month', $this->month)->where($notPaidElsewhere)->get();
            $bonus = 0;
            $advance = 0;
            foreach ($adjustments as $adjustment) {
                if ($adjustment->type === 'advance') {
                    $advance += $adjustment->amount;
                    $lines[] = ['Advance'.($adjustment->note ? ': '.$adjustment->note : ''), -$adjustment->amount];
                } else {
                    $bonus += $adjustment->amount;
                    $lines[] = [ucfirst($adjustment->type).($adjustment->note ? ': '.$adjustment->note : ''), $adjustment->amount];
                }
            }

            $deductions = 0;
            foreach ($salary?->fixed_deductions ?? [] as $d) {
                $deductions += (float) ($d['amount'] ?? 0);
                $lines[] = [$d['name'] ?? 'Deduction', -(float) ($d['amount'] ?? 0)];
            }
            foreach (SalaryDeduction::where('employee_id', $employee->id)->where('month', $this->month)->where('status', 'approved')->where($notPaidElsewhere)->get() as $d) {
                $deductions += $d->amount;
                $lines[] = [($d->note ?: ucfirst(str_replace('_', ' ', $d->type))).($d->date ? ' '.$d->date->format('d/m') : ''), -$d->amount];
            }

            $loan = 0;
            foreach (EmployeeLoan::where('employee_id', $employee->id)->where('status', 'active')->get() as $l) {
                $part = $l->installmentFor($this->month);
                if ($part > 0) {
                    $loan += $part;
                    $lines[] = ['Loan', -$part];
                }
            }

            $gross = round($basic + $allowances + $overtime + $bonus, 2);
            $keep[] = $employee->id;
            $this->items()->updateOrCreate(['employee_id' => $employee->id], [
                'basic' => $basic, 'allowances' => $allowances, 'overtime' => $overtime, 'bonus' => round($bonus, 2),
                'deductions' => round($deductions, 2), 'advance' => round($advance, 2), 'loan' => round($loan, 2),
                'gross' => $gross,
                'net' => round($gross - $deductions - $advance - $loan, 2),
                'lines' => array_map(fn ($l) => ['name' => $l[0], 'amount' => round($l[1], 2)], $lines),
            ]);
        }

        // People who are no longer in this run (e.g. moved branch) lose their line.
        $this->items()->whereNotIn('employee_id', $keep)->delete();
    }

    /** Marks what this monthly run paid: overtime, deductions, adjustments and loan instalments. */
    public function settleMonthly(): void
    {
        $ids = $this->items()->pluck('employee_id');
        $range = [$this->period_from->toDateString(), $this->period_to->toDateString()];
        OvertimeRequest::whereIn('employee_id', $ids)->where('status', 'approved')->whereBetween('date', $range)->whereNull('pay_run_id')->update(['pay_run_id' => $this->id]);
        SalaryDeduction::whereIn('employee_id', $ids)->where('month', $this->month)->where('status', 'approved')->whereNull('pay_run_id')->update(['pay_run_id' => $this->id]);
        SalaryAdjustment::whereIn('employee_id', $ids)->where('month', $this->month)->whereNull('pay_run_id')->update(['pay_run_id' => $this->id]);
        foreach (EmployeeLoan::whereIn('employee_id', $ids)->where('status', 'active')->get() as $loan) {
            $part = $loan->installmentFor($this->month);
            if ($part > 0) {
                $loan->paid = round($loan->paid + $part, 2);
                $loan->status = $loan->left <= 0 ? 'closed' : 'active';
                $loan->save();
            }
        }
    }

    public static function monthRange(string $month): array
    {
        $from = Carbon::parse($month.'-01');

        return [$from, $from->copy()->endOfMonth()->startOfDay()];
    }

    // ---- approval (monthly runs) ----

    public function approvalEmployee(): ?Employee
    {
        return null;
    }

    public function approvalCheck(): ?string
    {
        return $this->status === 'pending' ? null : 'This pay run is '.$this->status.'.';
    }

    public function approvalApproved(AuthUser $user): void
    {
        $this->update(['status' => 'approved', 'approved_by' => $user->name]);
    }

    public function approvalRejected(AuthUser $user): void
    {
        $this->update(['status' => 'draft', 'approved_by' => null]);
    }

    public function approvalSummary(): array
    {
        $items = $this->items()->get();

        return [
            'ref' => 'PR-'.$this->id,
            'employee' => null,
            'title' => 'Payroll '.Carbon::parse($this->month.'-01')->format('F Y').' ('.($this->branch?->name ?? 'all branches').'): '.$items->count().' staff, net $'.number_format($items->sum('net'), 2),
            'note' => null,
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
