<?php

namespace App\Http\Controllers\Api\Settings;

use App\Models\BackupSetting;

/** Settings > System > Backups. */
class BackupSettingController extends SettingPageController
{
    protected string $model = BackupSetting::class;

    protected function rules(): array
    {
        return [
            'daily_backup' => 'required|boolean',
            'backup_time' => 'required|in:01:00,03:00,05:00',
        ];
    }
}
