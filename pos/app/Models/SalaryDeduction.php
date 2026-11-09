<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * HRM: money taken off one employee's pay for one month. Status: pending, approved, rejected.
 * Made by calculate() from the attendance and the deduction policies, or added by hand (no policy, type "manual").
 * Only approved ones go into payroll. One per policy per day (a unique key), so the same thing is never deducted twice.
 */
class SalaryDeduction extends Model
{
    protected $table = 'salary_deductions';

    protected $hidden = ['updated_at'];

    protected $fillable = [
        'ref', 'employee_id', 'branch_id', 'deduction_policy_id', 'attendance_id', 'type', 'month', 'date', 'quantity',
        'amount', 'status', 'note', 'pay_run_id', 'user_id',
    ];

    protected function casts(): array
    {
        return [
            'employee_id' => 'integer',
            'branch_id' => 'integer',
            'deduction_policy_id' => 'integer',
            'attendance_id' => 'integer',
            'date' => 'date:Y-m-d',
            'quantity' => 'float',
            'amount' => 'float',
            'pay_run_id' => 'integer',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class)->withTrashed();
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(DeductionPolicy::class, 'deduction_policy_id')->withTrashed();
    }

    public static function nextRef(): string
    {
        $last = static::orderByDesc('id')->value('ref');

        return 'SD-'.($last ? (int) substr($last, 3) + 1 : 1001);
    }

    /**
     * Works out the deductions of one month for these employees, from their attendance.
     * Only finished days (before today) without a pending attendance fix count.
     * Days already deducted (or rejected) are skipped. Returns how many were added.
     */
    public static function calculate(string $month, $employees, ?string $userId): int
    {
        $from = $month.'-01';
        $to = min(Carbon::parse($from)->endOfMonth()->toDateString(), Attendance::shopNow()->subDay()->toDateString());
        $added = 0;

        foreach ($employees as $employee) {
            $rows = Attendance::where('employee_id', $employee->id)->whereBetween('date', [$from, $to])->get()->keyBy(fn ($r) => $r->date->toDateString());
            $leaves = LeaveRequest::with('policy')->where('employee_id', $employee->id)->where('status', 'Approved')
                ->where('date_from', '<=', $to)->where('date_to', '>=', $from)->get();
            $pendingFix = AttendanceCorrection::where('employee_id', $employee->id)->where('status', 'pending')
                ->whereBetween('date', [$from, $to])->pluck('date')->map(fn ($d) => $d->toDateString())->all();

            foreach (DeductionPolicy::forBranch($employee->branch_id) as $policy) {
                $times = 0;
                $total = (float) static::where('employee_id', $employee->id)->where('deduction_policy_id', $policy->id)
                    ->where('month', $month)->where('status', '!=', 'rejected')->sum('amount');

                for ($day = Carbon::parse($from); $day->lte(Carbon::parse($to)); $day->addDay()) {
                    $date = $day->toDateString();
                    if (in_array($date, $pendingFix, true) || DeductionException::covers($employee->id, $policy->id, $date)) {
                        continue;
                    }
                    $row = $rows[$date] ?? null;
                    $leave = $leaves->first(fn ($l) => $l->date_from->toDateString() <= $date && $l->date_to->toDateString() >= $date);
                    $quantity = self::quantityFor($policy, $employee, $date, $row, $leave);
                    if ($quantity <= 0) {
                        continue;
                    }

                    $times++;
                    if ($times <= $policy->free_times) {
                        continue;
                    }
                    if (static::where('employee_id', $employee->id)->where('deduction_policy_id', $policy->id)->whereDate('date', $date)->exists()) {
                        continue;
                    }

                    $amount = $policy->amountFor($employee, $date, $quantity);
                    if ($policy->monthly_limit !== null) {
                        $amount = round(min($amount, $policy->monthly_limit - $total), 2);
                    }
                    if ($amount <= 0) {
                        continue;
                    }
                    $total += $amount;

                    $deduction = static::create([
                        'ref' => static::nextRef(), 'employee_id' => $employee->id, 'branch_id' => $employee->branch_id,
                        'deduction_policy_id' => $policy->id, 'attendance_id' => $row?->id, 'type' => $policy->type,
                        'month' => $month, 'date' => $date, 'quantity' => $quantity, 'amount' => $amount,
                        'status' => $policy->needs_approval ? 'pending' : 'approved',
                        'note' => $policy->name, 'user_id' => $userId,
                    ]);
                    if ($policy->needs_approval) {
                        Approval::start('deduction', $deduction);
                    }
                    $added++;
                }
            }
        }

        return $added;
    }

    /** Minutes (late, early_leave) or days (the other types) this policy deducts for that day; 0 = nothing. */
    private static function quantityFor(DeductionPolicy $policy, Employee $employee, string $date, ?Attendance $row, ?LeaveRequest $leave): float
    {
        $status = Attendance::statusFor($employee, $date, $row, $leave);

        return match ($policy->type) {
            'late' => $row ? max(0, $row->late_minutes - $policy->grace_minutes) : 0,
            'early_leave' => $row && $row->last_out_at ? max(0, $row->early_minutes - $policy->grace_minutes) : 0,
            'absence' => $status === 'absent' ? 1 : 0,
            'half_day' => $status === 'half_day' ? 1 : 0,
            'missing_punch' => $status === 'missing_punch' ? 1 : 0,
            'unpaid_leave' => $leave && $leave->policy && ! $leave->policy->paid && ! ($leave->policy->exclude_weekends && Shift::isOffFor($employee, $date))
                ? ($leave->duration === 'full' ? 1 : $leave->days) : 0,
            default => 0,
        };
    }

    // ---- approval ----

    public function approvalEmployee(): ?Employee
    {
        return $this->employee;
    }

    public function approvalCheck(): ?string
    {
        return $this->status === 'pending' ? null : $this->ref.' is '.$this->status.'.';
    }

    public function approvalApproved(AuthUser $user): void
    {
        $this->update(['status' => 'approved']);
    }

    public function approvalRejected(AuthUser $user): void
    {
        $this->update(['status' => 'rejected']);
    }

    public function approvalSummary(): array
    {
        return [
            'ref' => $this->ref,
            'employee' => $this->employee?->name,
            'title' => 'Deduction $'.number_format($this->amount, 2).' ('.str_replace('_', ' ', $this->type).')'.($this->date ? ' for '.$this->date->format('d/m/Y') : ' in '.$this->month),
            'note' => $this->note,
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
