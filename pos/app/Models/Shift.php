<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * HRM: a work shift, e.g. "Morning" 09:00 to 17:00. When end_time is not after start_time
 * the shift ends the next day (a night shift, e.g. 22:00 to 06:00).
 * Flexible shifts only check the total (required_minutes), not the start and end.
 */
class Shift extends Model
{
    use SoftDeletes;

    protected $table = 'shifts';

    protected $hidden = ['created_at', 'updated_at', 'deleted_at'];

    protected $appends = ['crosses_midnight', 'planned_minutes'];

    protected $fillable = [
        'branch_id', 'name', 'code', 'start_time', 'end_time', 'break_minutes', 'late_grace_minutes', 'early_grace_minutes',
        'half_day_minutes', 'flexible', 'required_minutes', 'weekly_off', 'active',
    ];

    protected function casts(): array
    {
        return [
            'branch_id' => 'integer',
            'break_minutes' => 'integer',
            'late_grace_minutes' => 'integer',
            'early_grace_minutes' => 'integer',
            'half_day_minutes' => 'integer',
            'flexible' => 'boolean',
            'required_minutes' => 'integer',
            'weekly_off' => 'array',
            'active' => 'boolean',
        ];
    }

    public function getCrossesMidnightAttribute(): bool
    {
        return isset($this->attributes['end_time']) && $this->attributes['end_time'] <= $this->attributes['start_time'];
    }

    /** Minutes of work the shift asks for (without the break). */
    public function getPlannedMinutesAttribute(): int
    {
        if (! empty($this->attributes['flexible'])) {
            return (int) $this->attributes['required_minutes'];
        }
        $start = self::minutes($this->attributes['start_time'] ?? '00:00');
        $end = self::minutes($this->attributes['end_time'] ?? '00:00');
        if ($end <= $start) {
            $end += 1440;
        }

        return max(0, $end - $start - (int) ($this->attributes['break_minutes'] ?? 0));
    }

    /** Start and end of this shift on a day (the end can be the next day). */
    public function startOn(string $date): Carbon
    {
        return Carbon::parse($date.' '.$this->start_time);
    }

    public function endOn(string $date): Carbon
    {
        $end = Carbon::parse($date.' '.$this->end_time);

        return $this->crosses_midnight ? $end->addDay() : $end;
    }

    public function isDayOff(string $date): bool
    {
        return in_array((int) Carbon::parse($date)->dayOfWeek, $this->weekly_off ?? [], true);
    }

    /** "19:30" -> 1170 */
    public static function minutes(string $time): int
    {
        [$h, $m] = explode(':', $time);

        return (int) $h * 60 + (int) $m;
    }

    // ---- which shift an employee works ----

    private static array $cache = [];

    /** The shift an employee works on a day, or null. */
    public static function forEmployee(Employee $employee, string $date): ?Shift
    {
        $key = $employee->id.'|'.$date;
        if (! array_key_exists($key, self::$cache)) {
            self::$cache[$key] = ShiftAssignment::findFor($employee, $date)?->shiftOn($date);
        }

        return self::$cache[$key];
    }

    /** Is that day a day off for this employee: their shift's days off, or the default days off. */
    public static function isOffFor(Employee $employee, string $date): bool
    {
        $shift = self::forEmployee($employee, $date);
        if ($shift) {
            return $shift->isDayOff($date);
        }

        return in_array((int) Carbon::parse($date)->dayOfWeek, HrmSetting::get('weekly_off'), true);
    }

    public static function forgetCache(): void
    {
        self::$cache = [];
    }
}
