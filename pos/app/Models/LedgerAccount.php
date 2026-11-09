<?php

namespace App\Models;

/** Accounting > Chart of accounts screen. */
class LedgerAccount extends Record
{
    protected $table = 'ledger_accounts';

    protected $primaryKey = 'code';

    protected $fillable = [
        'code', 'name', 'type', 'open', 'tax', 'tag',
    ];

    protected function casts(): array
    {
        return [
            'open' => 'float',
        ];
    }
}
