<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Settings > Payment methods > Methods. */
class PaymentMethod extends Model
{
    protected $table = 'payment_methods';

    protected $fillable = [
        'name', 'pays_into', 'active',
    ];

    protected $hidden = ['created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }
}
