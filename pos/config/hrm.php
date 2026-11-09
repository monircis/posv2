<?php

/*
| HRM defaults. Each one can be changed on the HRM settings API (PUT /api/hrm/settings),
| which saves them in app_settings under the key "hrm".
*/
return [
    // A second punch within this many minutes of the last one is refused (a double tap).
    'duplicate_punch_minutes' => 2,

    // A clock-in stays open for at most this many hours; a later punch starts a new day.
    'open_punch_hours' => 20,

    // Days off for staff with no shift: 0 = Sunday ... 6 = Saturday.
    'weekly_off' => [0],

    // Working hours in a month, to turn a monthly salary into an hourly rate (38 h x 52 / 12).
    'hours_per_month' => 164.67,

    // Hours in a working day, for hourly leave when the employee has no shift (38 h / 5).
    'hours_per_day' => 7.6,

    // Days in a month, to turn a monthly salary into a day's pay (absence deductions).
    'salary_days_per_month' => 30,
];
