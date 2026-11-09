<?php

namespace App\Http\Controllers\Api\Settings;

use App\Models\NotificationSetting;

/** Settings > Notifications. */
class NotificationSettingController extends SettingPageController
{
    protected string $model = NotificationSetting::class;

    protected function rules(): array
    {
        return [
            'alert_email' => 'nullable|email|max:190',
            'low_stock' => 'required|boolean',
            'daily_summary' => 'required|boolean',
            'bills_due' => 'required|boolean',
            'booking_reminder' => 'required|boolean',
            'reminder_lead' => 'required|in:2 hours before,Day before',
            'order_ready' => 'required|boolean',
        ];
    }
}
