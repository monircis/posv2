<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * HRM: one employee on one (shift) day. A night shift's day is the day it started.
 * The punches (attendance_punches) are the facts; recalculate() works out everything else from them:
 * worked, break, late, early and extra minutes, and the status.
 * Old columns: `in` and `out` are minutes after midnight of `date` (out can be over 1440 the next day), `absent`.
 *
 * Status: present, late, half_day, missing_punch (clocked in, never out), absent, leave, weekend, holiday.
 */
class Attendance extends Model
{
    public const PUNCH_TYPES = ['in', 'out', 'break_start', 'break_end'];

    protected $table = 'attendances';

    protected $hidden = ['created_at', 'updated_at'];

    protected $fillable = [
        'employee_id', 'branch_id', 'shift_id', 'date', 'day_type', 'status', 'in', 'out', 'absent',
        'first_in_at', 'last_out_at', 'worked_minutes', 'break_minutes', 'late_minutes', 'early_minutes', 'extra_minutes',
        'remote', 'corrected', 'note',
    ];

    protected function casts(): array
    {
        return [
            'employee_id' => 'integer',
            'branch_id' => 'integer',
            'shift_id' => 'integer',
            'date' => 'date:Y-m-d',
            'in' => 'integer',
            'out' => 'integer',
            'absent' => 'boolean',
            'first_in_at' => 'datetime:Y-m-d H:i',
            'last_out_at' => 'datetime:Y-m-d H:i',
            'worked_minutes' => 'integer',
            'break_minutes' => 'integer',
            'late_minutes' => 'integer',
            'early_minutes' => 'integer',
            'extra_minutes' => 'integer',
            'remote' => 'boolean',
            'corrected' => 'boolean',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class)->withTrashed();
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class)->withTrashed();
    }

    public function punches(): HasMany
    {
        return $this->hasMany(AttendancePunch::class)->orderBy('punched_at')->orderBy('id');
    }

    public function breaks(): HasMany
    {
        return $this->hasMany(AttendanceBreak::class)->orderBy('start_at');
    }

    /**
     * The shop's clock time now. All HRM times (punches, shifts) are saved as the shop's own clock time,
     * so this is "now" as the shop's wall clock shows it.
     */
    public static function shopNow(): Carbon
    {
        return Carbon::parse(now(config('app.shop_timezone'))->format('Y-m-d H:i:s'));
    }

    // ---- punching ----

    /**
     * Records one punch: in, out, break_start or break_end.
     * Returns the attendance, or a message when the punch is not allowed.
     */
    public static function punch(Employee $employee, string $type, Carbon $at, string $source, bool $remote, ?string $by): Attendance|string
    {
        return DB::transaction(function () use ($employee, $type, $at, $source, $remote, $by) {
            // One punch at a time per employee.
            Employee::whereKey($employee->id)->lockForUpdate()->first();

            // The punches just before and just after this time (a punch can be entered for an earlier day).
            $last = AttendancePunch::where('employee_id', $employee->id)->where('punched_at', '<=', $at)->orderByDesc('punched_at')->orderByDesc('id')->first();
            $next = AttendancePunch::where('employee_id', $employee->id)->where('punched_at', '>', $at)->orderBy('punched_at')->orderBy('id')->first();
            $gap = (int) HrmSetting::get('duplicate_punch_minutes');
            foreach ([$last, $next] as $near) {
                if ($near && $gap > 0 && abs($near->punched_at->diffInMinutes($at, false)) < $gap) {
                    return 'A punch was already recorded at '.$near->punched_at->format('H:i').'. Wait '.$gap.' minutes between punches.';
                }
            }

            // An open clock-in (not clocked out yet, and not too old) belongs to the same day: this is how night shifts work.
            $openHours = (int) HrmSetting::get('open_punch_hours');
            $open = $last && $last->type !== 'out' && $last->punched_at->diffInHours($at) < $openHours;

            // A punch in the middle of a day that already has later punches would mix them up: fix such a day by hand.
            if ($next && $next->type !== 'in' && $at->diffInHours($next->punched_at) < $openHours) {
                return 'That day already has later punches ('.$next->punched_at->format('d/m H:i').'). Use the manual correction to change it.';
            }
            $date = $open ? Attendance::find($last->attendance_id)->date->toDateString() : $at->toDateString();
            $state = $open ? ['in' => 'in', 'break_end' => 'in', 'break_start' => 'break'][$last->type] : 'out';

            $allowed = ['out' => ['in'], 'in' => ['out', 'break_start'], 'break' => ['break_end', 'out']][$state];
            if (! in_array($type, $allowed, true)) {
                return [
                    'in' => $employee->first.' has already clocked in.',
                    'out' => $employee->first.' has not clocked in.',
                    'break_start' => $state === 'break' ? $employee->first.' is already on a break.' : $employee->first.' has not clocked in.',
                    'break_end' => $employee->first.' is not on a break.',
                ][$type];
            }

            $row = static::firstOrCreate(['employee_id' => $employee->id, 'date' => $date], ['branch_id' => $employee->branch_id]);

            // Clocking out during a break ends the break first.
            if ($type === 'out' && $state === 'break') {
                $row->punches()->create(['employee_id' => $employee->id, 'type' => 'break_end', 'punched_at' => $at, 'source' => $source, 'remote' => $remote, 'created_by' => $by]);
            }
            $row->punches()->create(['employee_id' => $employee->id, 'type' => $type, 'punched_at' => $at, 'source' => $source, 'remote' => $remote, 'created_by' => $by]);

            $row->recalculate();

            return $row;
        });
    }

    /** Puts a whole day's punches in place (manual correction or an approved correction request). */
    public function replacePunches(Carbon $in, ?Carbon $out, ?int $breakMinutes, string $source, ?string $by): void
    {
        DB::transaction(function () use ($in, $out, $breakMinutes, $source, $by) {
            $oldBreaks = $this->breaks()->get();
            $this->punches()->delete();
            $base = ['employee_id' => $this->employee_id, 'source' => $source, 'created_by' => $by];
            $this->punches()->create($base + ['type' => 'in', 'punched_at' => $in]);

            if ($breakMinutes === null) {
                // Keep the breaks that were there (when they are inside the new times).
                foreach ($oldBreaks as $break) {
                    if ($break->end_at && $break->start_at->gte($in) && (! $out || $break->end_at->lte($out))) {
                        $this->punches()->create($base + ['type' => 'break_start', 'punched_at' => $break->start_at]);
                        $this->punches()->create($base + ['type' => 'break_end', 'punched_at' => $break->end_at]);
                    }
                }
            } elseif ($breakMinutes > 0 && $out) {
                // One break in the middle of the day.
                $middle = $in->copy()->addMinutes(intdiv((int) $in->diffInMinutes($out) - $breakMinutes, 2));
                $this->punches()->create($base + ['type' => 'break_start', 'punched_at' => $middle]);
                $this->punches()->create($base + ['type' => 'break_end', 'punched_at' => $middle->copy()->addMinutes($breakMinutes)]);
            }

            if ($out) {
                $this->punches()->create($base + ['type' => 'out', 'punched_at' => $out]);
            }
            $this->corrected = true;
            $this->recalculate();
        });
    }

    // ---- working it out ----

    /** Works out the totals and the status from the punches, the shift, holidays and days off. */
    public function recalculate(): void
    {
        $employee = $this->employee;
        $date = $this->date->toDateString();
        $punches = $this->punches()->get();

        // Pairs: in -> out is work, break_start -> break_end is a break.
        $worked = 0;
        $breakMinutes = 0;
        $inAt = null;
        $breakAt = null;
        $breaks = [];
        foreach ($punches as $punch) {
            if ($punch->type === 'in') {
                $inAt = $punch->punched_at;
            } elseif ($punch->type === 'out' && $inAt) {
                $worked += (int) $inAt->diffInMinutes($punch->punched_at);
                $inAt = null;
            } elseif ($punch->type === 'break_start') {
                $breakAt = $punch->punched_at;
            } elseif ($punch->type === 'break_end' && $breakAt) {
                $minutes = (int) $breakAt->diffInMinutes($punch->punched_at);
                $breakMinutes += $minutes;
                $breaks[] = ['start_at' => $breakAt, 'end_at' => $punch->punched_at, 'minutes' => $minutes];
                $breakAt = null;
            }
        }
        if ($breakAt) {
            $breaks[] = ['start_at' => $breakAt, 'end_at' => null, 'minutes' => 0];
        }
        $this->breaks()->delete();
        foreach ($breaks as $break) {
            $this->breaks()->create($break);
        }

        $firstIn = $punches->firstWhere('type', 'in')?->punched_at;
        $lastPunch = $punches->last();
        $lastOut = $lastPunch && $lastPunch->type === 'out' ? $lastPunch->punched_at : null;
        $worked = max(0, $worked - $breakMinutes);

        $shift = Shift::forEmployee($employee, $date);
        $holiday = Holiday::onDay($employee->branch_id, $date);
        $dayType = $holiday ? 'holiday' : (Shift::isOffFor($employee, $date) ? 'weekend' : 'workday');

        $late = 0;
        $early = 0;
        $extra = 0;
        if ($dayType !== 'workday') {
            $extra = $worked; // all of a day off is extra time
        } elseif ($shift && ! $shift->flexible) {
            $start = $shift->startOn($date);
            $end = $shift->endOn($date);
            if ($firstIn && $firstIn->gt($start->copy()->addMinutes($shift->late_grace_minutes))) {
                $late = (int) $start->diffInMinutes($firstIn);
            }
            if ($lastOut && $lastOut->lt($end->copy()->subMinutes($shift->early_grace_minutes))) {
                $early = (int) $lastOut->diffInMinutes($end);
            }
            $extra = max(0, $worked - $shift->planned_minutes);
        } elseif ($shift) {
            $extra = max(0, $worked - $shift->required_minutes);
        }

        $status = $this->status;
        if ($firstIn) {
            $isPast = $date < self::shopNow()->toDateString();
            if (! $lastOut && ($isPast || ($shift && self::shopNow()->gt($shift->endOn($date)->addHours(4))))) {
                $status = 'missing_punch';
            } elseif ($lastOut && $shift && $shift->half_day_minutes > 0 && $dayType === 'workday' && $worked < $shift->half_day_minutes) {
                $status = 'half_day';
            } elseif ($late > 0) {
                $status = 'late';
            } else {
                $status = 'present';
            }
        }

        $midnight = Carbon::parse($date);
        $this->fill([
            'branch_id' => $this->branch_id ?: $employee->branch_id,
            'shift_id' => $shift?->id,
            'day_type' => $dayType,
            'status' => $status,
            'first_in_at' => $firstIn,
            'last_out_at' => $lastOut,
            'in' => $firstIn ? (int) $midnight->diffInMinutes($firstIn) : null,
            'out' => $lastOut ? (int) $midnight->diffInMinutes($lastOut) : null,
            'absent' => $firstIn ? false : $this->absent,
            'worked_minutes' => $worked,
            'break_minutes' => $breakMinutes,
            'late_minutes' => $late,
            'early_minutes' => $early,
            'extra_minutes' => $lastOut ? $extra : 0,
            'remote' => (bool) $punches->firstWhere('remote', true),
        ]);
        $this->save();

        OvertimeRequest::syncAuto($this);
    }

    /**
     * One row per day from..to for an employee: the attendance row (or null) and the day's status.
     *
     * @return array<int, array{date: string, status: string, row: ?Attendance}>
     */
    public static function daysFor(Employee $employee, string $from, string $to): array
    {
        $rows = static::where('employee_id', $employee->id)->whereBetween('date', [$from, $to])->get()->keyBy(fn ($r) => $r->date->toDateString());
        $leaves = LeaveRequest::where('employee_id', $employee->id)->where('status', 'Approved')
            ->where('date_from', '<=', $to)->where('date_to', '>=', $from)->get();

        $days = [];
        for ($day = Carbon::parse($from); $day->lte(Carbon::parse($to)); $day->addDay()) {
            $date = $day->toDateString();
            $row = $rows[$date] ?? null;
            $leave = $leaves->first(fn ($l) => $l->date_from->toDateString() <= $date && $l->date_to->toDateString() >= $date);
            $days[] = ['date' => $date, 'status' => self::statusFor($employee, $date, $row, $leave), 'row' => $row];
        }

        return $days;
    }

    /** Totals for an employee from..to: days per status, worked, late, early, break and extra minutes. */
    public static function summaryFor(Employee $employee, string $from, string $to): array
    {
        $sum = ['present' => 0, 'late' => 0, 'half_day' => 0, 'missing_punch' => 0, 'absent' => 0, 'leave' => 0, 'weekend' => 0, 'holiday' => 0,
            'worked_minutes' => 0, 'late_minutes' => 0, 'early_minutes' => 0, 'break_minutes' => 0, 'extra_minutes' => 0, 'remote_days' => 0];
        foreach (self::daysFor($employee, $from, $to) as $day) {
            if (isset($sum[$day['status']])) {
                $sum[$day['status']]++;
            }
            if ($day['row']) {
                foreach (['worked_minutes', 'late_minutes', 'early_minutes', 'break_minutes', 'extra_minutes'] as $field) {
                    $sum[$field] += $day['row']->$field;
                }
                $sum['remote_days'] += $day['row']->remote ? 1 : 0;
            }
        }
        // Days at work (late and half days count as attended).
        $sum['attended'] = $sum['present'] + $sum['late'] + $sum['half_day'] + $sum['missing_punch'];
        $workdays = $sum['attended'] + $sum['absent'] + $sum['leave'];
        $sum['attendance_percent'] = $workdays ? round($sum['attended'] / $workdays * 100, 1) : null;

        return $sum;
    }

    /**
     * The status of an employee on a day, also for days with no attendance row:
     * leave, holiday, weekend (day off), absent, or none (not employed yet / in the future).
     */
    public static function statusFor(Employee $employee, string $date, ?Attendance $row, ?LeaveRequest $leave = null): string
    {
        if ($row && $row->first_in_at) {
            return $row->status;
        }
        if ($leave) {
            return 'leave';
        }
        if ($row && in_array($row->status, ['leave', 'absent'], true)) {
            return $row->status;
        }
        if (! $employee->employedOn($date) || $date > self::shopNow()->toDateString()) {
            return 'none';
        }
        if (Holiday::onDay($employee->branch_id, $date)) {
            return 'holiday';
        }
        if (Shift::isOffFor($employee, $date)) {
            return 'weekend';
        }
        if ($date === self::shopNow()->toDateString()) {
            return 'not_in_yet';
        }

        return 'absent';
    }
}
