<?php

namespace App\Models;

/** Settings > Products. One row: use ProductSetting::current(). */
class ProductSetting extends SingleRowSetting
{
    protected $table = 'product_settings';

    protected $fillable = [
        'auto_sku', 'sku_prefix', 'default_unit', 'low_stock_alert', 'barcode_type',
        'track_expiry', 'track_batches',
    ];

    protected function casts(): array
    {
        return [
            'auto_sku' => 'boolean',
            'low_stock_alert' => 'integer',
            'track_expiry' => 'boolean',
            'track_batches' => 'boolean',
        ];
    }
}
