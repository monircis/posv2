<?php

namespace App\Models;

/** Settings > Notifications. One row: use NotificationSetting::current(). */
class NotificationSetting extends SingleRowSetting
{
    protected $table = 'notification_settings';

    protected $fillable = [
        'alert_email', 'low_stock', 'daily_summary', 'bills_due', 'booking_reminder',
        'reminder_lead', 'order_ready',
    ];

    protected function casts(): array
    {
        return [
            'low_stock' => 'boolean',
            'daily_summary' => 'boolean',
            'bills_due' => 'boolean',
            'booking_reminder' => 'boolean',
            'order_ready' => 'boolean',
        ];
    }
}
