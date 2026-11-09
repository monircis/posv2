<?php

namespace App\Http\Controllers\Api\Hrm;

use App\Models\LeavePolicy;
use App\Models\LeaveRequest;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * HRM > Leave policies: each leave type (Annual, Sick, Maternity ...) and its rules.
 * accrual: yearly (all days on 1 January), monthly (1/12 a month) or none.
 * gender / employment_types / branch_id / department_id: who can take it (null = everyone).
 */
#[Group('HRM setup', weight: 19)]
class LeavePolicyController extends HrmSetupController
{
    protected string $model = LeavePolicy::class;

    protected function rules(Request $request, ?Model $record): array
    {
        return [
            'name' => 'required|string|max:60',
            'code' => ['required', 'string', 'max:20', Rule::unique('leave_policies')->ignore($record?->id)],
            'paid' => 'boolean',
            'track_balance' => 'boolean',
            'days_per_year' => 'numeric|min:0|max:366',
            'accrual' => 'in:yearly,monthly,none',
            'carry_forward' => 'boolean',
            'max_carry_days' => 'numeric|min:0|max:366',
            'max_balance' => 'nullable|numeric|min:0|max:999',
            'encashable' => 'boolean',
            'allow_half_day' => 'boolean',
            'allow_hourly' => 'boolean',
            'exclude_weekends' => 'boolean',
            'exclude_holidays' => 'boolean',
            'probation_allowed' => 'boolean',
            'attachment_after_days' => 'nullable|numeric|min:0',
            'gender' => 'nullable|in:Male,Female,Other',
            'employment_types' => 'nullable|array',
            'employment_types.*' => 'string',
            'branch_id' => 'nullable|exists:branches,id',
            'department_id' => 'nullable|exists:departments,id',
            'active' => 'boolean',
        ];
    }

    protected function deleteProblem(Model $record): ?string
    {
        return LeaveRequest::where('leave_policy_id', $record->id)->where('status', 'Pending')->exists()
            ? 'Some requests for this leave are still waiting. Decide them first, or switch the policy off.' : null;
    }
}
