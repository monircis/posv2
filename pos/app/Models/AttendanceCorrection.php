<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * HRM: a request to fix a day's attendance (regularization): "I came in at 09:00 and left at 17:30".
 * pending -> approved (the day's punches are replaced) or rejected; the employee can cancel while pending.
 */
class AttendanceCorrection extends Model
{
    protected $table = 'attendance_corrections';

    protected $hidden = ['updated_at'];

    protected $fillable = ['ref', 'employee_id', 'branch_id', 'date', 'in_at', 'out_at', 'break_minutes', 'reason', 'status', 'user_id', 'created_by'];

    protected function casts(): array
    {
        return [
            'employee_id' => 'integer',
            'branch_id' => 'integer',
            'date' => 'date:Y-m-d',
            'in_at' => 'datetime:Y-m-d H:i',
            'out_at' => 'datetime:Y-m-d H:i',
            'break_minutes' => 'integer',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class)->withTrashed();
    }

    public static function nextRef(): string
    {
        $last = static::orderByDesc('id')->value('ref');

        return 'AC-'.($last ? (int) substr($last, 3) + 1 : 1001);
    }

    /** Puts the asked-for times in place. */
    public function apply(?string $by): void
    {
        $row = Attendance::firstOrCreate(['employee_id' => $this->employee_id, 'date' => $this->date->toDateString()], ['branch_id' => $this->branch_id]);
        $row->replacePunches($this->in_at, $this->out_at, $this->break_minutes, 'correction', $by);
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
        $this->apply($user->name);
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
            'title' => 'Attendance fix for '.$this->date->format('d/m/Y').': '.$this->in_at->format('H:i').' to '.($this->out_at?->format('H:i') ?? '?'),
            'note' => $this->reason,
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
