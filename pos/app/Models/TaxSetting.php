<?php

namespace App\Models;

/** Settings > Tax > GST. One row: use TaxSetting::current(). */
class TaxSetting extends SingleRowSetting
{
    protected $table = 'tax_settings';

    protected $fillable = [
        'gst_registered', 'prices_include_tax', 'bas_period', 'tax_rounding',
    ];

    protected function casts(): array
    {
        return [
            'gst_registered' => 'boolean',
            'prices_include_tax' => 'boolean',
        ];
    }
}
