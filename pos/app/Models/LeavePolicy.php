<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * HRM: a leave type and its rules (Annual, Sick, Maternity ...).
 * branch_id / department_id / gender / employment_types limit who can take it (null = everyone).
 */
class LeavePolicy extends Model
{
    use SoftDeletes;

    protected $table = 'leave_policies';

    protected $hidden = ['created_at', 'updated_at', 'deleted_at'];

    protected $fillable = [
        'name', 'code', 'paid', 'track_balance', 'days_per_year', 'accrual', 'carry_forward', 'max_carry_days', 'max_balance',
        'encashable', 'allow_half_day', 'allow_hourly', 'exclude_weekends', 'exclude_holidays', 'probation_allowed',
        'attachment_after_days', 'gender', 'employment_types', 'branch_id', 'department_id', 'active',
    ];

    protected function casts(): array
    {
        return [
            'paid' => 'boolean',
            'track_balance' => 'boolean',
            'days_per_year' => 'float',
            'carry_forward' => 'boolean',
            'max_carry_days' => 'float',
            'max_balance' => 'float',
            'encashable' => 'boolean',
            'allow_half_day' => 'boolean',
            'allow_hourly' => 'boolean',
            'exclude_weekends' => 'boolean',
            'exclude_holidays' => 'boolean',
            'probation_allowed' => 'boolean',
            'attachment_after_days' => 'float',
            'employment_types' => 'array',
            'branch_id' => 'integer',
            'department_id' => 'integer',
            'active' => 'boolean',
        ];
    }

    /** Can this employee take this leave at all? Returns the reason when not. */
    public function problemFor(Employee $employee): ?string
    {
        if (! $this->active) {
            return $this->name.' leave is switched off.';
        }
        if ($this->branch_id && $this->branch_id !== $employee->branch_id) {
            return $this->name.' leave is not for this branch.';
        }
        if ($this->department_id && $this->department_id !== $employee->department_id) {
            return $this->name.' leave is not for this department.';
        }
        if ($this->gender && $this->gender !== $employee->gender) {
            return $this->name.' leave is only for '.strtolower($this->gender).' staff.';
        }
        if ($this->employment_types && ! in_array($employee->type, $this->employment_types, true)) {
            return $employee->type.' staff cannot take '.strtolower($this->name).' leave.';
        }

        return null;
    }

    /** The policies this employee can take. */
    public static function forEmployee(Employee $employee)
    {
        return static::orderBy('name')->get()->filter(fn ($p) => $p->problemFor($employee) === null)->values();
    }

    /**
     * How many days of leave from..to costs, leaving out days off and holidays (when the policy says so).
     * duration: full, half (one day, 0.5) or hours (one day, hours / a day's hours).
     */
    public function countDays(Employee $employee, string $from, string $to, string $duration, ?float $hours): float
    {
        $days = 0;
        for ($day = Carbon::parse($from); $day->lte(Carbon::parse($to)); $day->addDay()) {
            $date = $day->toDateString();
            if ($this->exclude_weekends && Shift::isOffFor($employee, $date)) {
                continue;
            }
            if ($this->exclude_holidays && Holiday::onDay($employee->branch_id, $date)) {
                continue;
            }
            $days++;
        }
        if ($days === 0) {
            return 0;
        }
        if ($duration === 'half') {
            return 0.5;
        }
        if ($duration === 'hours') {
            $shift = Shift::forEmployee($employee, $from);
            $dayHours = $shift && $shift->planned_minutes > 0 ? $shift->planned_minutes / 60 : (float) HrmSetting::get('hours_per_day');

            return round(min((float) $hours, $dayHours) / $dayHours, 2);
        }

        return (float) $days;
    }
}
