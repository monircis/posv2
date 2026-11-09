<?php

namespace App\Models;

/** Settings > System > Backups. One row: use BackupSetting::current(). */
class BackupSetting extends SingleRowSetting
{
    protected $table = 'backup_settings';

    protected $fillable = [
        'daily_backup', 'backup_time',
    ];

    protected function casts(): array
    {
        return [
            'daily_backup' => 'boolean',
        ];
    }
}
