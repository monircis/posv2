<?php

namespace App\Models;

/** Settings > Branches & locations > Defaults. One row: use StockSetting::current(). */
class StockSetting extends SingleRowSetting
{
    protected $table = 'stock_settings';

    protected $fillable = [
        'default_branch_id', 'track_stock_per_branch',
    ];

    protected function casts(): array
    {
        return [
            'default_branch_id' => 'integer',
            'track_stock_per_branch' => 'boolean',
        ];
    }
}
