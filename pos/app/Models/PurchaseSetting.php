<?php

namespace App\Models;

/** Settings > Purchases. One row: use PurchaseSetting::current(). */
class PurchaseSetting extends SingleRowSetting
{
    protected $table = 'purchase_settings';

    protected $fillable = [
        'po_prefix', 'approval_over', 'default_supplier_terms', 'allow_partial_receiving',
        'cost_method',
    ];

    protected function casts(): array
    {
        return [
            'approval_over' => 'integer',
            'allow_partial_receiving' => 'boolean',
        ];
    }
}
