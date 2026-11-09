<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * HRM: a leave request. Status: Pending, Approved, Declined, Cancelled.
 * It goes through the "leave" approval workflow. When it is approved:
 * - the days come off the balance (when the policy keeps a balance)
 * - the days get an attendance row with status "leave" (days where the employee clocked in are left alone)
 */
class LeaveRequest extends Model
{
    protected $table = 'leave_requests';

    protected $hidden = ['updated_at'];

    protected $fillable = [
        'ref', 'employee_id', 'leave_policy_id', 'branch_id', 'type', 'date_from', 'date_to', 'duration', 'hours', 'days',
        'status', 'note', 'attachment', 'decided_by', 'user_id',
    ];

    protected function casts(): array
    {
        return [
            'employee_id' => 'integer',
            'leave_policy_id' => 'integer',
            'branch_id' => 'integer',
            'date_from' => 'date:Y-m-d',
            'date_to' => 'date:Y-m-d',
            'hours' => 'float',
            'days' => 'float',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class)->withTrashed();
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(LeavePolicy::class, 'leave_policy_id')->withTrashed();
    }

    /** "LV-121" gives "LV-122". */
    public static function nextRef(): string
    {
        $max = 0;
        foreach (static::where('ref', 'like', 'LV-%')->pluck('ref') as $ref) {
            $number = substr($ref, 3);
            if (ctype_digit($number) && (int) $number > $max) {
                $max = (int) $number;
            }
        }

        return 'LV-'.($max + 1);
    }

    public function balance(): ?LeaveBalance
    {
        $policy = $this->policy;
        if (! $policy || ! $policy->track_balance) {
            return null;
        }

        return LeaveBalance::for($this->employee, $policy, (int) $this->date_from->format('Y'));
    }

    /** Approved leave of an employee that covers a day, or null. */
    public static function approvedOn(int $employeeId, string $date): ?LeaveRequest
    {
        return static::where('employee_id', $employeeId)->where('status', 'Approved')
            ->where('date_from', '<=', $date)->where('date_to', '>=', $date)->first();
    }

    /** Marks the leave days in attendance (or takes the marks away again). */
    public function markAttendance(bool $on): void
    {
        for ($day = Carbon::parse($this->date_from); $day->lte($this->date_to); $day->addDay()) {
            $row = Attendance::where('employee_id', $this->employee_id)->whereDate('date', $day->toDateString())->first();
            if ($on && (! $row || ! $row->first_in_at)) {
                $row = $row ?: new Attendance(['employee_id' => $this->employee_id, 'date' => $day->toDateString(), 'branch_id' => $this->branch_id]);
                $row->status = 'leave';
                $row->absent = false;
                $row->note = $this->ref.' '.$this->type.' leave';
                $row->save();
            } elseif (! $on && $row && $row->status === 'leave') {
                $row->delete();
            }
        }
    }

    /** Takes an approved request back: the days go back on the balance and the attendance marks go. */
    public function cancelApproved(string $by): void
    {
        $balance = $this->balance();
        if ($balance) {
            $balance->used = round(max(0, $balance->used - $this->days), 2);
            $balance->save();
        }
        $this->markAttendance(false);
        $this->update(['status' => 'Cancelled', 'decided_by' => $by]);
    }

    // ---- approval ----

    public function approvalEmployee(): ?Employee
    {
        return $this->employee;
    }

    public function approvalCheck(): ?string
    {
        if ($this->status !== 'Pending') {
            return $this->ref.' has already been decided.';
        }
        $balance = $this->balance();
        if ($balance && $balance->available < $this->days) {
            return $this->employee->first.' only has '.$balance->available.' days of '.strtolower($this->type).' leave.';
        }

        return null;
    }

    public function approvalApproved(AuthUser $user): void
    {
        $balance = $this->balance();
        if ($balance) {
            $balance->used = round($balance->used + $this->days, 2);
            $balance->save();
        }
        $this->update(['status' => 'Approved', 'decided_by' => $user->name]);
        $this->markAttendance(true);
    }

    public function approvalRejected(AuthUser $user): void
    {
        $this->update(['status' => 'Declined', 'decided_by' => $user->name]);
    }

    public function approvalSummary(): array
    {
        return [
            'ref' => $this->ref,
            'employee' => $this->employee?->name,
            'title' => $this->type.' leave, '.$this->days.' day'.($this->days == 1 ? '' : 's').': '.$this->date_from->format('d/m/Y').($this->date_to->ne($this->date_from) ? ' to '.$this->date_to->format('d/m/Y') : ''),
            'note' => $this->note,
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
