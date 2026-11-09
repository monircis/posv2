<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * HRM: how much of one leave type an employee has in one year.
 * available = allocated + carried + accrued + adjusted - used - encashed
 * - yearly accrual: the year's days are allocated when the balance is made
 * - monthly accrual: 1/12 is added for each month that has started (worked out whenever the balance is read)
 * - carried: what was left last year, up to the policy's max_carry_days
 * - accrual stops at the policy's max_balance
 */
class LeaveBalance extends Model
{
    protected $table = 'leave_balances';

    protected $hidden = ['created_at', 'updated_at'];

    protected $appends = ['available'];

    protected $fillable = ['employee_id', 'leave_policy_id', 'year', 'allocated', 'carried', 'accrued', 'adjusted', 'used', 'encashed', 'accrued_months'];

    protected function casts(): array
    {
        return [
            'employee_id' => 'integer',
            'leave_policy_id' => 'integer',
            'year' => 'integer',
            'allocated' => 'float',
            'carried' => 'float',
            'accrued' => 'float',
            'adjusted' => 'float',
            'used' => 'float',
            'encashed' => 'float',
            'accrued_months' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // The HRM screen still shows the old balance fields (al, pl) on the employee: keep them the same.
        static::saved(function (LeaveBalance $balance) {
            $code = LeavePolicy::withTrashed()->whereKey($balance->leave_policy_id)->value('code');
            $field = ['AL' => 'al', 'PL' => 'pl'][$code] ?? null;
            if ($field && $balance->year === (int) Attendance::shopNow()->format('Y')) {
                Employee::withTrashed()->whereKey($balance->employee_id)->update([$field => $balance->available]);
            }
        });
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(LeavePolicy::class, 'leave_policy_id')->withTrashed();
    }

    public function getAvailableAttribute(): float
    {
        return round($this->allocated + $this->carried + $this->accrued + $this->adjusted - $this->used - $this->encashed, 2);
    }

    /** The balance for a year, made (with carry-forward) when it is not there yet, and accrued up to this month. */
    public static function for(Employee $employee, LeavePolicy $policy, int $year): LeaveBalance
    {
        $balance = static::firstOrNew(['employee_id' => $employee->id, 'leave_policy_id' => $policy->id, 'year' => $year]);

        if (! $balance->exists) {
            $balance->allocated = $policy->accrual === 'yearly' ? $policy->days_per_year : 0;
            if ($policy->carry_forward) {
                $last = static::where(['employee_id' => $employee->id, 'leave_policy_id' => $policy->id, 'year' => $year - 1])->first();
                if ($last) {
                    $balance->carried = max(0, min($last->available, $policy->max_carry_days));
                }
            }
            if ($policy->max_balance !== null) {
                $balance->allocated = max(0, min($balance->allocated, $policy->max_balance - $balance->carried));
            }
        }

        if ($policy->accrual === 'monthly') {
            $now = Attendance::shopNow();
            $months = $year < (int) $now->format('Y') ? 12 : ($year > (int) $now->format('Y') ? 0 : (int) $now->format('n'));
            // Only the months since the employee started count (someone who starts in September gets Sep, Oct ...).
            $start = $employee->start;
            if ($start && (int) $start->format('Y') === $year) {
                $months = max(0, $months - ((int) $start->format('n') - 1));
            } elseif ($start && (int) $start->format('Y') > $year) {
                $months = 0;
            }
            if ($months > $balance->accrued_months) {
                $add = round($policy->days_per_year / 12 * ($months - $balance->accrued_months), 2);
                if ($policy->max_balance !== null) {
                    $add = max(0, min($add, $policy->max_balance - $balance->available));
                }
                $balance->accrued = round($balance->accrued + $add, 2);
                $balance->accrued_months = $months;
            }
        }

        if ($balance->isDirty() || ! $balance->exists) {
            $balance->save();
        }

        return $balance;
    }

    /** Days asked for but not decided yet (they cannot be asked for twice). */
    public function pendingDays(): float
    {
        return (float) LeaveRequest::where('employee_id', $this->employee_id)->where('leave_policy_id', $this->leave_policy_id)
            ->where('status', 'Pending')->whereYear('date_from', $this->year)->sum('days');
    }
}
