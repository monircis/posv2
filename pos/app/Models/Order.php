<?php

namespace App\Models;

/** Orders screen. */
class Order extends Record
{
    protected $table = 'orders';

    protected $primaryKey = 'no';

    protected $fillable = [
        'no', 'ch', 'cust', 'where', 'at', 'status',
        'pay', 'paid', 'paid_amt', 'items', 'disc', 'fee',
        'note', 'method', 'driver', 'times', 'log',
    ];

    protected function casts(): array
    {
        return [
            'at' => 'integer',
            'paid' => 'float',
            'paid_amt' => 'float',
            'items' => 'array',
            'disc' => 'float',
            'fee' => 'float',
            'times' => 'array',
            'log' => 'array',
        ];
    }
}
