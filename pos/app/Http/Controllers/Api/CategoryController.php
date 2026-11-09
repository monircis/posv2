<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Products > Categories. Categories can have sub-categories at any depth (parent_id). */
#[Group('Categories', 'Product categories, any number of levels. A top category has parent_id = null; a sub-category points to its parent.', weight: 10)]
class CategoryController extends Controller
{
    /**
     * List categories
     *
     * Every category at every level, sorted by name. Build the tree with `parent_id`.
     * `children_count` = direct sub-categories, `products_count` = products in this category.
     * Needs `products.view` or `sell.view`.
     */
    public function index()
    {
        return Category::withCount('children', 'products')->orderBy('name')->get();
    }

    /**
     * Add category
     *
     * Leave `parent_id` empty for a top category, or give any category as the parent.
     * Names must be unique inside the same parent. Needs `products.create`.
     */
    public function store(Request $request)
    {
        $data = $request->validate($this->rules($request));

        $category = Category::create($data);

        return response()->json($category->fresh()->loadCount('children'), 201);
    }

    /**
     * Show category
     *
     * Needs `products.view` or `sell.view`.
     */
    public function show(Category $category)
    {
        return $category->loadCount('children');
    }

    /**
     * Update category
     *
     * Send all fields. A category cannot be moved under itself or under one of its own
     * sub-categories (422). Needs `products.edit`.
     */
    public function update(Request $request, Category $category)
    {
        $data = $request->validate($this->rules($request, $category));

        if (! empty($data['parent_id']) && $this->isSelfOrBelow($data['parent_id'], $category)) {
            return response()->json(['message' => 'A category cannot be moved under itself or one of its sub-categories.'], 422);
        }

        $category->update($data);

        return $category->fresh()->loadCount('children');
    }

    /**
     * Delete category
     *
     * Refused (422) while it has sub-categories or products use it. Needs `products.delete`.
     */
    public function destroy(Category $category)
    {
        if ($category->children()->exists()) {
            return response()->json(['message' => 'Delete or move the sub-categories of '.$category->name.' first.'], 422);
        }
        if ($category->products()->exists()) {
            return response()->json(['message' => 'Products still use '.$category->name.'. Change their category first.'], 422);
        }

        $category->delete();

        return response()->noContent();
    }

    /**
     * True when $parentId is the category itself or somewhere below it.
     * Walks up from the new parent to the top; if we meet the category, it would be a loop.
     */
    private function isSelfOrBelow(int $parentId, Category $category): bool
    {
        $current = Category::find($parentId);

        while ($current) {
            if ($current->id === $category->id) {
                return true;
            }
            $current = $current->parent;
        }

        return false;
    }

    private function rules(Request $request, ?Category $category = null): array
    {
        return [
            // Unique inside the same parent: "Mince" can be under Beef and under Lamb.
            'name' => [
                'required', 'string', 'max:120',
                Rule::unique('categories')->ignore($category)->where('parent_id', $request->input('parent_id')),
            ],
            'parent_id' => 'nullable|integer|exists:categories,id',
            'description' => 'nullable|string|max:255',
            'active' => 'boolean',
        ];
    }
}
