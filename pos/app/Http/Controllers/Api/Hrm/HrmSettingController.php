<?php

namespace App\Http\Controllers\Api\Hrm;

use App\Models\HrmSetting;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;

/** HRM > Settings: duplicate-punch interval, default days off, hours and days used to turn a salary into rates. */
#[Group('HRM setup', weight: 19)]
class HrmSettingController extends HrmController
{
    /** HRM settings. Needs `hrm.view`. */
    public function show()
    {
        return HrmSetting::all();
    }

    /** Change HRM settings (send only the ones to change). Needs `hrm.admin`. */
    public function update(Request $request)
    {
        $data = $request->validate([
            'duplicate_punch_minutes' => 'integer|min:0|max:120',
            'open_punch_hours' => 'integer|min:4|max:36',
            'weekly_off' => 'array',
            'weekly_off.*' => 'integer|between:0,6',
            'hours_per_month' => 'numeric|min:1|max:400',
            'hours_per_day' => 'numeric|min:1|max:24',
            'salary_days_per_month' => 'numeric|min:1|max:31',
        ]);

        return HrmSetting::save($data);
    }
}
