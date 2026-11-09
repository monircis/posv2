<?php

namespace App\Models;

/** Settings > Sales & POS. One row: use PosSetting::current(). */
class PosSetting extends SingleRowSetting
{
    protected $table = 'pos_settings';

    protected $fillable = [
        'default_customer', 'allow_below_stock', 'max_discount_percent', 'refund_needs_pin',
        'use_scale', 'search_keypad', 'require_register', 'print_receipt', 'clear_held_sales_after',
    ];

    protected function casts(): array
    {
        return [
            'allow_below_stock' => 'boolean',
            'max_discount_percent' => 'integer',
            'refund_needs_pin' => 'boolean',
            'use_scale' => 'boolean',
            'search_keypad' => 'boolean',
            'require_register' => 'boolean',
        ];
    }
}
