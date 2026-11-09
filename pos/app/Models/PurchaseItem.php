<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One line of a purchase. */
class PurchaseItem extends Model
{
    protected $table = 'purchase_items';

    protected $hidden = ['created_at', 'updated_at'];

    protected $fillable = [
        'purchase_id', 'product_id', 'variant_id', 'name', 'unit', 'qty', 'cost', 'discount', 'line_total', 'stock_after',
    ];

    protected function casts(): array
    {
        return [
            'purchase_id' => 'integer',
            'product_id' => 'integer',
            'variant_id' => 'integer',
            'qty' => 'float',
            'cost' => 'float',
            'discount' => 'float',
            'line_total' => 'float',
            'stock_after' => 'float',
        ];
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function returnItems(): HasMany
    {
        return $this->hasMany(PurchaseReturnItem::class);
    }
}
