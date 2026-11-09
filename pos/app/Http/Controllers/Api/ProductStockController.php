<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\StockMovement;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;

/** Stock of one product: the movement history and setting a new quantity at a branch. */
#[Group('Products', weight: 9)]
class ProductStockController extends Controller
{
    /**
     * Stock movements
     *
     * The last 100 changes to this product's stock, newest first. Needs `products.view`.
     */
    public function movements(Product $product)
    {
        return StockMovement::with('branch:id,name', 'variant:id,name')
            ->where('product_id', $product->id)
            ->orderBy('id', 'desc')
            ->limit(100)
            ->get();
    }

    /**
     * Adjust stock
     *
     * Set the quantity on hand at one branch (a stock count). The difference is saved as an "Adjustment" movement.
     * For a variable product send `variant_id`. Needs `products.edit` or `adjust.create`.
     */
    public function adjust(Request $request, Product $product)
    {
        $data = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'variant_id' => 'nullable|integer',
            'qty' => 'required|numeric',
            'note' => 'nullable|string|max:255',
        ]);

        if (! $product->manage_stock) {
            return response()->json(['message' => $product->name.' does not manage stock.'], 422);
        }
        if (! $product->branches()->where('branches.id', $data['branch_id'])->exists()) {
            return response()->json(['message' => $product->name.' is not sold at this branch. Tick the branch in Edit product first.'], 422);
        }

        $variantId = $data['variant_id'] ?? null;
        if ($product->type === 'variable' && ! $product->variants()->where('id', $variantId)->exists()) {
            return response()->json(['message' => 'Pick one of the variants of '.$product->name.'.'], 422);
        }
        if ($product->type === 'single') {
            $variantId = null;
        }
        if ($data['qty'] < 0 && ! $product->allow_negative_stock) {
            return response()->json(['message' => $product->name.' cannot go below 0.'], 422);
        }

        $current = ProductStock::where('product_id', $product->id)->where('variant_id', $variantId)
            ->where('branch_id', $data['branch_id'])->value('qty') ?? 0;
        $change = $data['qty'] - $current;

        if ($change != 0) {
            StockMovement::record($product->id, $variantId, $data['branch_id'], $change, 'Adjustment', null, $data['note'] ?? null, $request->user()->name);
        }

        return ProductStock::with('branch:id,name')->where('product_id', $product->id)->get();
    }
}
