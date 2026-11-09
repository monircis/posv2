<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Products > Brands. */
#[Group('Brands', 'Product brands.', weight: 11)]
class BrandController extends Controller
{
    /**
     * List brands
     *
     * Sorted by name. `products_count` = products of this brand. Needs `products.view` or `sell.view`.
     */
    public function index()
    {
        return Brand::withCount('products')->orderBy('name')->get();
    }

    /**
     * Add brand
     *
     * Names must be unique. Needs `products.create`.
     */
    public function store(Request $request)
    {
        $data = $request->validate($this->rules());

        $brand = Brand::create($data);

        return response()->json($brand->fresh(), 201);
    }

    /**
     * Show brand
     *
     * Needs `products.view` or `sell.view`.
     */
    public function show(Brand $brand)
    {
        return $brand;
    }

    /**
     * Update brand
     *
     * Send all fields. Needs `products.edit`.
     */
    public function update(Request $request, Brand $brand)
    {
        $data = $request->validate($this->rules($brand));

        $brand->update($data);

        return $brand->fresh();
    }

    /**
     * Delete brand
     *
     * Refused (422) while products use it. Needs `products.delete`.
     */
    public function destroy(Brand $brand)
    {
        if ($brand->products()->exists()) {
            return response()->json(['message' => 'Products still use '.$brand->name.'. Change their brand first.'], 422);
        }

        $brand->delete();

        return response()->noContent();
    }

    private function rules(?Brand $brand = null): array
    {
        return [
            'name' => ['required', 'string', 'max:120', Rule::unique('brands')->ignore($brand)],
            'description' => 'nullable|string|max:255',
            'active' => 'boolean',
        ];
    }
}
