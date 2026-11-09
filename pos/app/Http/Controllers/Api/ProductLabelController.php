<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Dedoc\Scramble\Attributes\Group;

/** Products > Print labels: barcode labels with the product name and price. */
#[Group('Products', weight: 9)]
class ProductLabelController extends Controller
{
    /**
     * Products for labels
     *
     * One row per single product and one per variant of a variable product, active ones only.
     * `code` is what the barcode holds: the barcode, or the SKU when the product has no barcode.
     * `price` is the shelf price including tax (price rules are not in it). Needs `products.view`.
     */
    public function index()
    {
        $products = Product::with('unit:id,short_name', 'taxRate:id,rate', 'category:id,name', 'variants')
            ->where('active', true)
            ->orderBy('name')
            ->get();

        $rows = [];
        foreach ($products as $product) {
            if ($product->type === 'variable') {
                foreach ($product->variants as $variant) {
                    if ($variant->active) {
                        $rows[] = $this->row($product, $variant);
                    }
                }
            } else {
                $rows[] = $this->row($product, null);
            }
        }

        return $rows;
    }

    private function row(Product $product, $variant): array
    {
        $barcode = $variant ? $variant->barcode : $product->barcode;
        $sku = $variant ? $variant->sku : $product->sku;

        return [
            'key' => $variant ? 'v'.$variant->id : 'p'.$product->id,
            'product_id' => $product->id,
            'variant_id' => $variant ? $variant->id : null,
            'name' => $variant ? $product->name.' · '.$variant->name : $product->name,
            'sku' => $sku,
            'barcode' => $barcode,
            'barcode_type' => $product->barcode_type, // EAN-13, Code 128 or QR
            'code' => $barcode ?: $sku,
            'price' => $product->posPrice($variant),
            'unit' => $product->unit ? $product->unit->short_name : 'ea',
            'category' => $product->category ? $product->category->name : null,
        ];
    }
}
