<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One change to stock: + in, - out, with the quantity after it.
 * Write it with StockMovement::record(), which also updates product_stocks.
 */
class StockMovement extends Model
{
    protected $table = 'stock_movements';

    protected $hidden = ['updated_at'];

    protected $fillable = [
        'product_id', 'variant_id', 'branch_id', 'change', 'balance', 'kind', 'ref', 'note', 'user_name',
    ];

    protected function casts(): array
    {
        return [
            'product_id' => 'integer',
            'variant_id' => 'integer',
            'branch_id' => 'integer',
            'change' => 'float',
            'balance' => 'float',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    /**
     * Change the stock of a product (or variant) at a branch and log it.
     * Example: StockMovement::record($product->id, null, $branchId, -2.5, 'Sale', '2026-5014', null, 'Sara');
     */
    public static function record(int $productId, ?int $variantId, int $branchId, float $change, string $kind, ?string $ref = null, ?string $note = null, ?string $userName = null): self
    {
        $stock = ProductStock::firstOrCreate(
            ['product_id' => $productId, 'variant_id' => $variantId, 'branch_id' => $branchId],
            ['qty' => 0]
        );
        $stock->qty = $stock->qty + $change;
        $stock->save();

        return self::create([
            'product_id' => $productId,
            'variant_id' => $variantId,
            'branch_id' => $branchId,
            'change' => $change,
            'balance' => $stock->qty,
            'kind' => $kind,
            'ref' => $ref,
            'note' => $note,
            'user_name' => $userName,
        ]);
    }
}
