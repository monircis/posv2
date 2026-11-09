<?php

namespace App\Models;

/** Settings (and other JSON documents) screen. */
class AppSetting extends Record
{
    protected $table = 'app_settings';

    protected $primaryKey = 'key';

    protected $fillable = [
        'key', 'value',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }
}
