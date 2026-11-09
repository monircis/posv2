<?php

namespace App\Models;

/**
 * HRM settings: the defaults in config/hrm.php, changed by what is saved in app_settings under "hrm".
 * Example: HrmSetting::get('duplicate_punch_minutes')
 */
class HrmSetting
{
    private static ?array $values = null;

    public static function all(): array
    {
        if (self::$values === null) {
            $saved = AppSetting::find('hrm')?->value ?? [];
            self::$values = array_merge(config('hrm'), array_intersect_key($saved, config('hrm')));
        }

        return self::$values;
    }

    public static function get(string $key)
    {
        return self::all()[$key];
    }

    public static function save(array $values): array
    {
        AppSetting::updateOrCreate(['key' => 'hrm'], ['value' => array_merge(self::all(), $values)]);
        self::$values = null;

        return self::all();
    }
}
