<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One line of a stock adjustment: how much stock changed, and why. */
class StockAdjustmentItem extends Model
{
    protected $table = 'stock_adjustment_items';

    protected $hidden = ['created_at', 'updated_at'];

    protected $fillable = [
        'stock_adjustment_id', 'product_id', 'variant_id', 'name', 'sku', 'unit',
        'before_qty', 'change_qty', 'cost', 'reason',
    ];

    protected function casts(): array
    {
        return [
            'stock_adjustment_id' => 'integer',
            'product_id' => 'integer',
            'variant_id' => 'integer',
            'before_qty' => 'float',
            'change_qty' => 'float',
            'cost' => 'float',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
