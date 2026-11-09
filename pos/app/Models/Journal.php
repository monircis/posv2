<?php

namespace App\Models;

/** Accounting > Journals screen. */
class Journal extends Record
{
    protected $table = 'journals';

    protected $primaryKey = 'no';

    protected $fillable = [
        'no', 'date', 'src', 'desc', 'ref', 'status',
        'lines',
    ];

    protected function casts(): array
    {
        return [
            'lines' => 'array',
        ];
    }
}
