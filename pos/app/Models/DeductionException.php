<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** HRM: an employee who is not deducted (for one policy, or all when deduction_policy_id is null) between two dates. */
class DeductionException extends Model
{
    protected $table = 'deduction_exceptions';

    protected $hidden = ['updated_at'];

    protected $fillable = ['employee_id', 'deduction_policy_id', 'date_from', 'date_to', 'reason', 'created_by'];

    protected function casts(): array
    {
        return ['employee_id' => 'integer', 'deduction_policy_id' => 'integer', 'date_from' => 'date:Y-m-d', 'date_to' => 'date:Y-m-d'];
    }

    /** Is this employee excused from this policy on that day? */
    public static function covers(int $employeeId, int $policyId, string $date): bool
    {
        return static::where('employee_id', $employeeId)
            ->where(fn ($q) => $q->whereNull('deduction_policy_id')->orWhere('deduction_policy_id', $policyId))
            ->where('date_from', '<=', $date)
            ->where(fn ($q) => $q->whereNull('date_to')->orWhere('date_to', '>=', $date))
            ->exists();
    }
}
