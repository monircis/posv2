<?php

namespace App\Http\Controllers\Api\Hrm;

use App\Models\OvertimePolicy;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * HRM > Overtime policies. Rates multiply the hourly rate (1.5 = time and a half).
 * Minute limits of 0 mean no limit. auto_calculate makes overtime requests from the attendance.
 */
#[Group('HRM setup', weight: 19)]
class OvertimePolicyController extends HrmSetupController
{
    protected string $model = OvertimePolicy::class;

    protected function rules(Request $request, ?Model $record): array
    {
        return [
            'name' => 'required|string|max:80',
            'branch_id' => 'nullable|exists:branches,id',
            'min_minutes' => 'integer|min:0|max:1440',
            'max_daily_minutes' => 'integer|min:0|max:1440',
            'max_monthly_minutes' => 'integer|min:0|max:44640',
            'regular_rate' => 'required|numeric|min:0|max:10',
            'weekend_rate' => 'required|numeric|min:0|max:10',
            'holiday_rate' => 'required|numeric|min:0|max:10',
            'auto_calculate' => 'boolean',
            'active' => 'boolean',
        ];
    }
}
