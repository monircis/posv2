<?php

namespace App\Models;

/** Settings > Payment methods > Rules. One row: use PaymentSetting::current(). */
class PaymentSetting extends SingleRowSetting
{
    protected $table = 'payment_settings';

    protected $fillable = [
        'card_surcharge', 'round_cash_5c', 'allow_split_payments',
    ];

    protected function casts(): array
    {
        return [
            'card_surcharge' => 'float',
            'round_cash_5c' => 'boolean',
            'allow_split_payments' => 'boolean',
        ];
    }
}
