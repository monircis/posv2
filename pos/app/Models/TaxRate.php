<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Settings > Tax > Tax rates. */
class TaxRate extends Model
{
    protected $table = 'tax_rates';

    protected $fillable = [
        'name', 'rate', 'code', 'active',
    ];

    protected $hidden = ['created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'rate' => 'float',
            'active' => 'boolean',
        ];
    }
}
