<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One line of a sale. Name, price and tax are copied, so old sales stay right when a product changes. */
class SaleItem extends Model
{
    protected $table = 'sale_items';

    protected $hidden = ['created_at', 'updated_at'];

    protected $fillable = [
        'sale_id', 'product_id', 'variant_id', 'name', 'sku', 'unit', 'qty', 'price', 'tax_rate',
        'discount_percent', 'total', 'note', 'stock_taken',
    ];

    protected function casts(): array
    {
        return [
            'product_id' => 'integer',
            'variant_id' => 'integer',
            'qty' => 'float',
            'price' => 'float',
            'tax_rate' => 'float',
            'discount_percent' => 'float',
            'total' => 'float',
            'stock_taken' => 'boolean',
        ];
    }
}
