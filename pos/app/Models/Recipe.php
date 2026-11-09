<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Manufacturing: how one batch of a product is made. */
class Recipe extends Model
{
    protected $table = 'recipes';

    protected $hidden = ['created_at', 'updated_at'];

    protected $fillable = ['name', 'product_id', 'variant_id', 'unit', 'output_qty', 'price'];

    protected function casts(): array
    {
        return [
            'product_id' => 'integer',
            'variant_id' => 'integer',
            'output_qty' => 'float',
            'price' => 'float',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(RecipeItem::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(ProductionOrder::class);
    }

    /** What the materials of one batch cost today. */
    public function batchCost(): float
    {
        $cost = 0;
        foreach ($this->items as $item) {
            $cost += $item->qty * $item->material->cost;
        }

        return round($cost, 2);
    }

    /** The linked product's price, or the recipe's own price when there is no product. */
    public function sellPrice(): float
    {
        if (! $this->product) {
            return $this->price;
        }
        $variant = $this->variant_id ? ProductVariant::find($this->variant_id) : null;

        return $this->product->posPrice($variant);
    }

    /** The recipe with its materials, for the screens. */
    public function toRow(): array
    {
        $this->loadMissing('items.material', 'product.taxRate');

        $row = $this->toArray();
        $row['batch_cost'] = $this->batchCost();
        $row['sell_price'] = $this->sellPrice();
        $row['orders_count'] = $this->orders()->count();
        unset($row['product']['tax_rate']);

        return $row;
    }
}
