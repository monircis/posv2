<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A material an order used. Name and cost are copied, so old orders stay right. */
class ProductionOrderItem extends Model
{
    protected $table = 'production_order_items';

    protected $hidden = ['created_at', 'updated_at'];

    protected $fillable = ['production_order_id', 'material_id', 'name', 'unit', 'qty', 'cost'];

    protected function casts(): array
    {
        return [
            'material_id' => 'integer',
            'qty' => 'float',
            'cost' => 'float',
        ];
    }
}
