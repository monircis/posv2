<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * HRM: who works a shift, from date_from (to date_to). target_type is branch, department or employee.
 * The most specific one wins: employee, then department, then branch; then the one that started last.
 * With rotation_shift_ids the shift changes every rotation_days days, in that order, counted from date_from.
 * Old assignments stay, so they are the shift history.
 */
class ShiftAssignment extends Model
{
    public const TARGETS = ['employee' => 3, 'department' => 2, 'branch' => 1];

    protected $table = 'shift_assignments';

    protected $hidden = ['updated_at'];

    protected $fillable = ['shift_id', 'target_type', 'target_id', 'date_from', 'date_to', 'rotation_shift_ids', 'rotation_days', 'note', 'created_by'];

    protected function casts(): array
    {
        return [
            'shift_id' => 'integer',
            'target_id' => 'integer',
            'date_from' => 'date:Y-m-d',
            'date_to' => 'date:Y-m-d',
            'rotation_shift_ids' => 'array',
            'rotation_days' => 'integer',
        ];
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class)->withTrashed();
    }

    /** The assignment that applies to an employee on a day. */
    public static function findFor(Employee $employee, string $date): ?ShiftAssignment
    {
        $rows = static::where('date_from', '<=', $date)
            ->where(fn ($q) => $q->whereNull('date_to')->orWhere('date_to', '>=', $date))
            ->where(function ($q) use ($employee) {
                $q->where(fn ($q) => $q->where('target_type', 'employee')->where('target_id', $employee->id));
                if ($employee->department_id) {
                    $q->orWhere(fn ($q) => $q->where('target_type', 'department')->where('target_id', $employee->department_id));
                }
                if ($employee->branch_id) {
                    $q->orWhere(fn ($q) => $q->where('target_type', 'branch')->where('target_id', $employee->branch_id));
                }
            })
            ->get();

        // Most specific first, then the newest start, then the newest row.
        return $rows->sortByDesc(fn ($a) => self::TARGETS[$a->target_type].'|'.$a->date_from->toDateString().'|'.str_pad((string) $a->id, 10, '0', STR_PAD_LEFT))->first();
    }

    /** The shift for that day (for a rotation: the one whose turn it is). */
    public function shiftOn(string $date): ?Shift
    {
        $ids = $this->rotation_shift_ids;
        if (! $ids) {
            return Shift::withTrashed()->find($this->shift_id);
        }
        $days = (int) $this->date_from->diffInDays(Carbon::parse($date));
        $turn = intdiv($days, max(1, $this->rotation_days)) % count($ids);

        return Shift::withTrashed()->find($ids[$turn]);
    }
}
