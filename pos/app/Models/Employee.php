<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * HRM: staff records. They are separate from sign-in users; user_id links one for self-service.
 * The PIN is hashed and never sent out.
 * Status: Active, Probation, Inactive, Resigned, Terminated ("On leave" is worked out from approved leave).
 */
class Employee extends Model
{
    use SoftDeletes;

    public const STATUSES = ['Active', 'Probation', 'Inactive', 'Resigned', 'Terminated'];

    // Still working here (shows on attendance, payroll, dashboard).
    public const WORKING = ['Active', 'Probation'];

    public const TYPES = ['Full-time', 'Part-time', 'Casual', 'Permanent', 'Contract', 'Temporary'];

    protected $table = 'employees';

    protected $hidden = ['pin', 'created_at', 'updated_at', 'deleted_at'];

    protected $appends = ['has_pin', 'name', 'photo_url'];

    protected $fillable = [
        'code', 'user_id', 'first', 'last', 'gender', 'marital_status', 'nationality', 'role', 'job_description',
        'dept', 'department_id', 'designation_id', 'reporting_manager_id', 'branch_id', 'overtime_policy_id',
        'type', 'basis', 'rate', 'hours', 'phone', 'email', 'address', 'city', 'photo',
        'start', 'probation_months', 'probation_end', 'confirmation_date', 'notice_date', 'exit_date', 'exit_reason',
        'status', 'al', 'pl', 'shift', 'pin', 'dob', 'emerg', 'emergency_name', 'emergency_phone', 'emergency_relation',
        'tfn', 'super_fund', 'bank_name', 'bank_account_name', 'bank_bsb', 'bank_account_number',
    ];

    protected function casts(): array
    {
        return [
            'branch_id' => 'integer',
            'department_id' => 'integer',
            'designation_id' => 'integer',
            'reporting_manager_id' => 'integer',
            'overtime_policy_id' => 'integer',
            'rate' => 'float',
            'hours' => 'float',
            'start' => 'date:Y-m-d',
            'probation_months' => 'integer',
            'probation_end' => 'date:Y-m-d',
            'confirmation_date' => 'date:Y-m-d',
            'notice_date' => 'date:Y-m-d',
            'exit_date' => 'date:Y-m-d',
            'al' => 'float',
            'pl' => 'float',
            'shift' => 'array',
            'pin' => 'hashed',
        ];
    }

    // ---- relations ----

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'reporting_manager_id');
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(EmployeeDocument::class)->orderByDesc('id');
    }

    public function educations(): HasMany
    {
        return $this->hasMany(EmployeeEducation::class)->orderByDesc('year');
    }

    public function experiences(): HasMany
    {
        return $this->hasMany(EmployeeExperience::class)->orderByDesc('date_from');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(EmployeeStatusHistory::class)->orderByDesc('date')->orderByDesc('id');
    }

    public function salaryStructures(): HasMany
    {
        return $this->hasMany(SalaryStructure::class)->orderByDesc('effective_from')->orderByDesc('id');
    }

    // ---- shown values ----

    public function getHasPinAttribute(): bool
    {
        return ! empty($this->attributes['pin']);
    }

    public function getNameAttribute(): string
    {
        return trim(($this->attributes['first'] ?? '').' '.($this->attributes['last'] ?? ''));
    }

    public function getPhotoUrlAttribute(): ?string
    {
        return empty($this->attributes['photo']) ? null : asset('storage/'.$this->attributes['photo']);
    }

    // ---- rules ----

    /** The next employee code: EMP-0001, EMP-0002 ... */
    public static function nextCode(): string
    {
        $max = 0;
        foreach (static::withTrashed()->where('code', 'like', 'EMP-%')->pluck('code') as $code) {
            $number = substr($code, 4);
            if (ctype_digit($number) && (int) $number > $max) {
                $max = (int) $number;
            }
        }

        return 'EMP-'.str_pad((string) ($max + 1), 4, '0', STR_PAD_LEFT);
    }

    public function isWorking(): bool
    {
        return in_array($this->status, self::WORKING, true);
    }

    public function onProbation(string $date): bool
    {
        return $this->probation_end && $date < $this->probation_end->toDateString();
    }

    /** Was this person employed on that day (after the start date, before the exit date)? */
    public function employedOn(string $date): bool
    {
        if ($this->start && $date < $this->start->toDateString()) {
            return false;
        }
        if ($this->exit_date && $date > $this->exit_date->toDateString()) {
            return false;
        }

        return true;
    }

    /** Changes the status and keeps a line in the status history. */
    public function changeStatus(string $to, ?string $date, ?string $note, ?string $by): void
    {
        $from = $this->status;
        if ($from === $to) {
            return;
        }
        $this->status = $to;
        $this->save();
        $this->statusHistory()->create([
            'from_status' => $from, 'to_status' => $to, 'date' => $date ?: now()->toDateString(), 'note' => $note, 'changed_by' => $by,
        ]);
    }

    /** The salary structure that applies on that day (the newest one that has started). */
    public function salaryOn(string $date): ?SalaryStructure
    {
        return SalaryStructure::where('employee_id', $this->id)->where('effective_from', '<=', $date)
            ->orderByDesc('effective_from')->orderByDesc('id')->first();
    }

    /** Basic pay for a month. From the salary structure, or from the old rate fields. */
    public function monthlyBasic(string $date): float
    {
        $salary = $this->salaryOn($date);
        if ($salary) {
            return (float) $salary->basic;
        }
        if ($this->basis === 'Salary') {
            return round($this->rate / 12, 2);
        }

        return round($this->rate * $this->hours * 52 / 12, 2);
    }

    /** Pay for one hour, for overtime and per-hour deductions. */
    public function hourlyRate(string $date): float
    {
        if ($this->salaryOn($date) || $this->basis === 'Salary') {
            return round($this->monthlyBasic($date) / HrmSetting::get('hours_per_month'), 4);
        }

        return (float) $this->rate;
    }

    /** Pay for one day, for absence deductions. */
    public function dailyRate(string $date): float
    {
        return round($this->monthlyBasic($date) / HrmSetting::get('salary_days_per_month'), 4);
    }
}
