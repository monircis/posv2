<?php

namespace App\Http\Controllers\Api\Settings;

use App\Models\SecuritySetting;

/** Settings > Users & security. */
class SecuritySettingController extends SettingPageController
{
    protected string $model = SecuritySetting::class;

    protected function rules(): array
    {
        return [
            'session_timeout' => 'required|in:15 minutes,30 minutes,2 hours,Never',
            'two_step_admins' => 'required|boolean',
            'pos_pin_sign_in' => 'required|boolean',
        ];
    }
}
