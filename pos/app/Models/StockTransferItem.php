<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One line of a stock transfer: how much was sent, and how much arrived. */
class StockTransferItem extends Model
{
    protected $table = 'stock_transfer_items';

    protected $hidden = ['created_at', 'updated_at'];

    protected $fillable = [
        'stock_transfer_id', 'product_id', 'variant_id', 'name', 'sku', 'unit', 'qty', 'received_qty', 'cost',
    ];

    protected function casts(): array
    {
        return [
            'stock_transfer_id' => 'integer',
            'product_id' => 'integer',
            'variant_id' => 'integer',
            'qty' => 'float',
            'received_qty' => 'float',
            'cost' => 'float',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }
}
