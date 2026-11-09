<?php

namespace App\Models;

/** Settings > Currency & formats. One row: use FormatSetting::current(). */
class FormatSetting extends SingleRowSetting
{
    protected $table = 'format_settings';

    protected $fillable = [
        'currency', 'symbol_position', 'decimal_places', 'date_format', 'time_24h',
        'week_starts_on', 'weight_unit',
    ];

    protected function casts(): array
    {
        return [
            'decimal_places' => 'integer',
            'time_24h' => 'boolean',
        ];
    }
}
