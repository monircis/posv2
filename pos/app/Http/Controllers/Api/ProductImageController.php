<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/** The product photo. Saved in storage/app/public/products. */
#[Group('Products', weight: 9)]
class ProductImageController extends Controller
{
    /**
     * Upload product photo
     *
     * Send the file in the "image" form field (PNG, JPG or WebP, up to 5 MB). The old photo is deleted.
     * Needs `products.edit` or `products.create`.
     */
    public function store(Request $request, Product $product)
    {
        $request->validate([
            'image' => 'required|image|mimes:png,jpg,jpeg,webp|max:5120', // 5 MB
        ]);

        if ($product->image) {
            Storage::disk('public')->delete($product->image);
        }

        $path = $request->file('image')->store('products', 'public');
        $product->update(['image' => $path]);

        return $product->fresh();
    }

    /**
     * Remove product photo
     *
     * Needs `products.edit`.
     */
    public function destroy(Product $product)
    {
        if ($product->image) {
            Storage::disk('public')->delete($product->image);
        }
        $product->update(['image' => null]);

        return $product->fresh();
    }
}
