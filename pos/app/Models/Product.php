<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * Products screen and POS.
 * type "single": one SKU, price and stock. type "variable": each variant has its own SKU, price and stock.
 */
class Product extends Model
{
    protected $table = 'products';

    protected $hidden = ['updated_at'];

    protected $fillable = [
        'name', 'sku', 'type', 'category_id', 'brand_id', 'unit_id', 'barcode', 'barcode_type', 'image', 'description',
        'cost', 'price', 'wholesale_price', 'tax_rate_id', 'price_includes_tax',
        'manage_stock', 'alert_qty', 'allow_negative_stock',
        'available_at_pos', 'pos_favourite', 'send_to_kitchen', 'prep_minutes', 'active',
    ];

    // Sent with every product so the frontend can show the photo.
    protected $appends = ['image_url'];

    protected function casts(): array
    {
        return [
            'category_id' => 'integer',
            'brand_id' => 'integer',
            'unit_id' => 'integer',
            'tax_rate_id' => 'integer',
            'cost' => 'float',
            'price' => 'float',
            'wholesale_price' => 'float',
            'price_includes_tax' => 'boolean',
            'manage_stock' => 'boolean',
            'alert_qty' => 'float',
            'allow_negative_stock' => 'boolean',
            'available_at_pos' => 'boolean',
            'pos_favourite' => 'boolean',
            'send_to_kitchen' => 'boolean',
            'prep_minutes' => 'integer',
            'active' => 'boolean',
            'created_at' => 'date:Y-m-d',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('sort_order')->orderBy('id');
    }

    /** The branches that sell this product (table branch_product). */
    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class);
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(ProductStock::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /** The price the POS charges: it always includes tax. */
    public function posPrice(?ProductVariant $variant = null): float
    {
        $price = (float) ($variant ? $variant->price : $this->price);

        if (! $this->price_includes_tax && $this->taxRate) {
            $price = round($price * (1 + $this->taxRate->rate / 100), 2);
        }

        return $price;
    }

    /** The price sold at today at this branch: the POS price after today's price rule (Settings > Price rules). */
    public function salePrice(?ProductVariant $variant, ?int $branchId): float
    {
        $price = $this->posPrice($variant);
        $rule = PriceRule::forProduct($this->id, $branchId);

        return $rule ? $rule->apply($price) : $price;
    }

    /** Full address of the photo, or null. */
    public function getImageUrlAttribute()
    {
        if (! $this->image) {
            return null;
        }

        return Storage::disk('public')->url($this->image);
    }
}
