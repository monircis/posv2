<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One row of a variable product, e.g. "Portioned 250g". Has its own SKU, price and stock. */
class ProductVariant extends Model
{
    protected $table = 'product_variants';

    protected $hidden = ['created_at', 'updated_at'];

    protected $fillable = [
        'product_id', 'name', 'sku', 'barcode', 'cost', 'price', 'wholesale_price', 'active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'product_id' => 'integer',
            'cost' => 'float',
            'price' => 'float',
            'wholesale_price' => 'float',
            'active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(ProductStock::class, 'variant_id');
    }
}
