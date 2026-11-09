<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * HRM: overtime for one employee on one day. Status: pending, approved, rejected, cancelled.
 * source "auto" is made from the attendance (extra minutes past the shift), "manual" is asked for.
 * amount = minutes / 60 x hourly rate x the policy's rate for that kind of day.
 * Payroll pays the approved ones of the month (pay_run_id is set when paid).
 */
class OvertimeRequest extends Model
{
    protected $table = 'overtime_requests';

    protected $hidden = ['updated_at'];

    protected $fillable = [
        'ref', 'employee_id', 'branch_id', 'attendance_id', 'overtime_policy_id', 'date', 'minutes', 'day_type', 'rate',
        'hourly_rate', 'amount', 'source', 'reason', 'status', 'pay_run_id', 'user_id',
    ];

    protected function casts(): array
    {
        return [
            'employee_id' => 'integer',
            'branch_id' => 'integer',
            'attendance_id' => 'integer',
            'overtime_policy_id' => 'integer',
            'date' => 'date:Y-m-d',
            'minutes' => 'integer',
            'rate' => 'float',
            'hourly_rate' => 'float',
            'amount' => 'float',
            'pay_run_id' => 'integer',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class)->withTrashed();
    }

    public static function nextRef(): string
    {
        $last = static::orderByDesc('id')->value('ref');

        return 'OT-'.($last ? (int) substr($last, 3) + 1 : 1001);
    }

    /** Fills in the rate and amount for this employee, day and number of minutes. */
    public function price(Employee $employee, ?OvertimePolicy $policy): void
    {
        $date = $this->date->toDateString();
        $this->overtime_policy_id = $policy?->id;
        $this->rate = $policy ? $policy->rateFor($this->day_type) : 1;
        $this->hourly_rate = round($employee->hourlyRate($date), 2);
        $this->amount = round($this->minutes / 60 * $employee->hourlyRate($date) * $this->rate, 2);
    }

    /**
     * After an attendance changes: make, update or remove its automatic overtime.
     * Only a pending one is changed; an approved or rejected one is left alone.
     */
    public static function syncAuto(Attendance $row): void
    {
        $employee = $row->employee;
        $policy = OvertimePolicy::forEmployee($employee);
        $current = static::where('attendance_id', $row->id)->where('source', 'auto')->first();
        if ($current && $current->status !== 'pending') {
            return;
        }

        $minutes = $row->extra_minutes;
        if ($policy && $policy->max_daily_minutes > 0) {
            $minutes = min($minutes, $policy->max_daily_minutes);
        }
        if (! $policy || ! $policy->auto_calculate || $minutes < max(1, $policy->min_minutes)) {
            if ($current) {
                Approval::cancel('overtime', $current->id);
                $current->delete();
            }

            return;
        }

        $request = $current ?: new static(['ref' => static::nextRef(), 'source' => 'auto', 'status' => 'pending', 'attendance_id' => $row->id, 'employee_id' => $employee->id]);
        $request->fill(['branch_id' => $row->branch_id, 'date' => $row->date->toDateString(), 'minutes' => $minutes, 'day_type' => $row->day_type]);
        $request->price($employee, $policy);
        $request->save();

        if (! $current) {
            Approval::start('overtime', $request);
        }
    }

    /** Approved overtime minutes of the employee in that month, without this one. */
    public function approvedMinutesInMonth(): int
    {
        return (int) static::where('employee_id', $this->employee_id)->where('status', 'approved')->where('id', '!=', $this->id)
            ->whereYear('date', $this->date->year)->whereMonth('date', $this->date->month)->sum('minutes');
    }

    // ---- approval ----

    public function approvalEmployee(): ?Employee
    {
        return $this->employee;
    }

    public function approvalCheck(): ?string
    {
        if ($this->status !== 'pending') {
            return $this->ref.' is '.$this->status.'.';
        }
        $policy = OvertimePolicy::withTrashed()->find($this->overtime_policy_id);
        if ($policy && $policy->max_monthly_minutes > 0 && $this->approvedMinutesInMonth() >= $policy->max_monthly_minutes) {
            return $this->employee->first.' already has the most overtime allowed this month ('.round($policy->max_monthly_minutes / 60, 1).' hours).';
        }

        return null;
    }

    public function approvalApproved(AuthUser $user): void
    {
        // Only up to the monthly limit is approved.
        $policy = OvertimePolicy::withTrashed()->find($this->overtime_policy_id);
        if ($policy && $policy->max_monthly_minutes > 0) {
            $left = $policy->max_monthly_minutes - $this->approvedMinutesInMonth();
            if ($this->minutes > $left) {
                $this->minutes = $left;
                $this->price($this->employee, $policy);
            }
        }
        $this->status = 'approved';
        $this->save();
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
            'title' => 'Overtime '.round($this->minutes / 60, 2).' h on '.$this->date->format('d/m/Y').' ('.$this->day_type.', x'.$this->rate.') = $'.number_format($this->amount, 2),
            'note' => $this->reason,
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
