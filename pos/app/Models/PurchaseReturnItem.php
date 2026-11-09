<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One line of a purchase return: how much of a purchase line went back, and why. */
class PurchaseReturnItem extends Model
{
    protected $table = 'purchase_return_items';

    protected $hidden = ['created_at', 'updated_at'];

    protected $fillable = [
        'purchase_return_id', 'purchase_item_id', 'name', 'qty', 'cost', 'reason', 'credit',
    ];

    protected function casts(): array
    {
        return [
            'purchase_return_id' => 'integer',
            'purchase_item_id' => 'integer',
            'qty' => 'float',
            'cost' => 'float',
            'credit' => 'float',
        ];
    }
}
