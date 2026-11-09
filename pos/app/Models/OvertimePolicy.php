<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * HRM: overtime rules. The rates multiply the hourly rate (1.5 = time and a half).
 * Which one applies: the employee's own policy, else their branch's, else the one for all branches.
 */
class OvertimePolicy extends Model
{
    use SoftDeletes;

    protected $table = 'overtime_policies';

    protected $hidden = ['created_at', 'updated_at', 'deleted_at'];

    protected $fillable = [
        'name', 'branch_id', 'min_minutes', 'max_daily_minutes', 'max_monthly_minutes', 'regular_rate', 'weekend_rate',
        'holiday_rate', 'auto_calculate', 'active',
    ];

    protected function casts(): array
    {
        return [
            'branch_id' => 'integer',
            'min_minutes' => 'integer',
            'max_daily_minutes' => 'integer',
            'max_monthly_minutes' => 'integer',
            'regular_rate' => 'float',
            'weekend_rate' => 'float',
            'holiday_rate' => 'float',
            'auto_calculate' => 'boolean',
            'active' => 'boolean',
        ];
    }

    public static function forEmployee(Employee $employee): ?OvertimePolicy
    {
        if ($employee->overtime_policy_id) {
            $own = static::where('active', true)->find($employee->overtime_policy_id);
            if ($own) {
                return $own;
            }
        }

        return static::where('active', true)->where('branch_id', $employee->branch_id)->orderBy('id')->first()
            ?? static::where('active', true)->whereNull('branch_id')->orderBy('id')->first();
    }

    public function rateFor(string $dayType): float
    {
        return ['weekend' => $this->weekend_rate, 'holiday' => $this->holiday_rate][$dayType] ?? $this->regular_rate;
    }
}
