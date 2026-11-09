<?php

namespace App\Http\Controllers\Api\Hrm;

use App\Models\DeductionPolicy;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * HRM > Deduction policies.
 * type: late, early_leave, absence, half_day, missing_punch, unpaid_leave.
 * method: fixed, percent (of the monthly basic), per_minute, per_hour, day_salary (value x a day's pay).
 * grace_minutes (late/early), free_times (first N a month free), monthly_limit (most a month), needs_approval.
 */
#[Group('HRM setup', weight: 19)]
class DeductionPolicyController extends HrmSetupController
{
    protected string $model = DeductionPolicy::class;

    protected function rules(Request $request, ?Model $record): array
    {
        return [
            'name' => 'required|string|max:80',
            'branch_id' => 'nullable|exists:branches,id',
            'type' => 'required|in:'.implode(',', DeductionPolicy::TYPES),
            'method' => 'required|in:'.implode(',', DeductionPolicy::METHODS),
            'value' => 'required|numeric|min:0',
            'grace_minutes' => 'integer|min:0|max:600',
            'free_times' => 'integer|min:0|max:31',
            'monthly_limit' => 'nullable|numeric|min:0',
            'needs_approval' => 'boolean',
            'active' => 'boolean',
        ];
    }

    protected function problem(array $data, ?Model $record): ?string
    {
        if ($data['method'] === 'percent' && $data['value'] > 100) {
            return 'A percent cannot be more than 100.';
        }

        return null;
    }
}
