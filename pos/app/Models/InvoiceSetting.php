<?php

namespace App\Models;

/** Settings > Invoice & receipt. One row: use InvoiceSetting::current(). */
class InvoiceSetting extends SingleRowSetting
{
    protected $table = 'invoice_settings';

    protected $fillable = [
        'invoice_prefix', 'next_invoice_number', 'receipt_prefix', 'paper', 'show_logo',
        'show_abn', 'payment_terms', 'footer_text',
    ];

    protected function casts(): array
    {
        return [
            'next_invoice_number' => 'integer',
            'show_logo' => 'boolean',
            'show_abn' => 'boolean',
        ];
    }
}
