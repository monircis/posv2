<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * HRM: a salary deduction rule.
 * type:   late, early_leave (minutes past the grace), absence, half_day, missing_punch, unpaid_leave (days)
 * method: fixed (value each time), percent (value % of the monthly basic each time),
 *         per_minute / per_hour (value per minute or hour; for day types the shift's minutes count),
 *         day_salary (value x a day's pay, e.g. 1 = a full day, 0.5 = half a day)
 * The first free_times times in a month are free; monthly_limit is the most it takes in a month.
 */
class DeductionPolicy extends Model
{
    use SoftDeletes;

    public const TYPES = ['late', 'early_leave', 'absence', 'half_day', 'missing_punch', 'unpaid_leave'];

    public const METHODS = ['fixed', 'percent', 'per_minute', 'per_hour', 'day_salary'];

    protected $table = 'deduction_policies';

    protected $hidden = ['created_at', 'updated_at', 'deleted_at'];

    protected $fillable = ['name', 'branch_id', 'type', 'method', 'value', 'grace_minutes', 'free_times', 'monthly_limit', 'needs_approval', 'active'];

    protected function casts(): array
    {
        return [
            'branch_id' => 'integer',
            'value' => 'float',
            'grace_minutes' => 'integer',
            'free_times' => 'integer',
            'monthly_limit' => 'float',
            'needs_approval' => 'boolean',
            'active' => 'boolean',
        ];
    }

    /** The active policies at a branch (its own and the ones for all branches). */
    public static function forBranch(?int $branchId)
    {
        return static::where('active', true)->where(fn ($q) => $q->whereNull('branch_id')->orWhere('branch_id', $branchId))->orderBy('id')->get();
    }

    /**
     * The amount for one time. $quantity is minutes (late, early_leave) or days (the other types).
     */
    public function amountFor(Employee $employee, string $date, float $quantity): float
    {
        $byMinutes = in_array($this->type, ['late', 'early_leave'], true);
        $minutes = $byMinutes ? $quantity : $quantity * (Shift::forEmployee($employee, $date)?->planned_minutes ?: HrmSetting::get('hours_per_day') * 60);

        $amount = match ($this->method) {
            'fixed' => $this->value,
            'percent' => $employee->monthlyBasic($date) * $this->value / 100,
            'per_minute' => $minutes * $this->value,
            'per_hour' => $minutes / 60 * $this->value,
            'day_salary' => $employee->dailyRate($date) * $this->value * ($byMinutes ? 1 : $quantity),
            default => 0,
        };

        return round($amount, 2);
    }
}
