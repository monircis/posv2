<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Material;
use App\Models\ProductionOrder;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Recipe;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Manufacturing > Recipes: how one batch of a product is made. */
#[Group('Manufacturing', weight: 17)]
class RecipeController extends Controller
{
    /**
     * List recipes
     *
     * Sorted by name, with their materials, `batch_cost` and `sell_price`. Needs `mfg.view`.
     */
    public function index()
    {
        $rows = [];
        foreach (Recipe::with('items.material', 'product.taxRate')->orderBy('name')->get() as $recipe) {
            $rows[] = $recipe->toRow();
        }

        return $rows;
    }

    /**
     * Form lists
     *
     * The materials and the products a recipe can make (one row per product or variant).
     * Only products that manage stock. Needs `mfg.view`.
     */
    public function formData()
    {
        $products = [];
        foreach (Product::with('unit:id,short_name', 'taxRate:id,rate', 'variants')->where('active', true)->where('manage_stock', true)->orderBy('name')->get() as $product) {
            $unit = $product->unit ? $product->unit->short_name : 'ea';
            if ($product->type === 'variable') {
                foreach ($product->variants as $variant) {
                    $products[] = ['product_id' => $product->id, 'variant_id' => $variant->id, 'name' => $product->name.' · '.$variant->name, 'unit' => $unit, 'price' => $product->posPrice($variant)];
                }
            } else {
                $products[] = ['product_id' => $product->id, 'variant_id' => null, 'name' => $product->name, 'unit' => $unit, 'price' => $product->posPrice()];
            }
        }

        return ['materials' => Material::orderBy('name')->get(), 'products' => $products];
    }

    /**
     * Add recipe
     *
     * Send the materials in `items`. With a product, the unit comes from the product. Needs `mfg.create`.
     */
    public function store(Request $request)
    {
        $data = $request->validate($this->rules());

        $problem = $this->variantProblem($data);
        if ($problem) {
            return response()->json(['message' => $problem], 422);
        }

        $recipe = DB::transaction(function () use ($data) {
            $recipe = Recipe::create($this->fields($data));
            $recipe->items()->createMany($data['items']);

            return $recipe;
        });

        return response()->json($recipe->fresh()->toRow(), 201);
    }

    /**
     * Update recipe
     *
     * Planned orders follow the new recipe. Orders already started keep what they used. Needs `mfg.edit`.
     */
    public function update(Request $request, Recipe $recipe)
    {
        $data = $request->validate($this->rules($recipe));

        $problem = $this->variantProblem($data);
        if ($problem) {
            return response()->json(['message' => $problem], 422);
        }

        DB::transaction(function () use ($data, $recipe) {
            $recipe->update($this->fields($data));
            $recipe->items()->delete();
            $recipe->items()->createMany($data['items']);

            $fresh = $recipe->fresh();
            foreach (ProductionOrder::where('recipe_id', $recipe->id)->where('status', 'planned')->get() as $order) {
                $order->update([
                    'recipe_name' => $fresh->name,
                    'unit' => $fresh->unit,
                    'expected_qty' => $fresh->output_qty * $order->batches,
                    'material_cost' => round($fresh->batchCost() * $order->batches, 2),
                ]);
            }
        });

        return $recipe->fresh()->toRow();
    }

    /**
     * Delete recipe
     *
     * Refused (422) once it has production orders. Needs `mfg.delete`.
     */
    public function destroy(Recipe $recipe)
    {
        if ($recipe->orders()->exists()) {
            return response()->json(['message' => $recipe->name.' has production orders, so it cannot be deleted.'], 422);
        }

        $recipe->delete();

        return response()->noContent();
    }

    // ---------------------------------------------------------------------

    private function fields(array $data): array
    {
        $unit = $data['unit'] ?? 'ea';
        if (! empty($data['product_id'])) {
            $product = Product::with('unit:id,short_name')->find($data['product_id']);
            $unit = $product->unit ? $product->unit->short_name : $unit;
        }

        return [
            'name' => $data['name'],
            'product_id' => $data['product_id'] ?? null,
            'variant_id' => $data['variant_id'] ?? null,
            'unit' => $unit,
            'output_qty' => $data['output_qty'],
            'price' => $data['price'] ?? 0,
        ];
    }

    /** A variable product needs one of its own variants; a single product has none. */
    private function variantProblem(array $data): ?string
    {
        if (empty($data['product_id'])) {
            return null;
        }
        $product = Product::find($data['product_id']);
        if ($product->type !== 'variable') {
            return empty($data['variant_id']) ? null : $product->name.' has no variants.';
        }
        if (! ProductVariant::where('product_id', $product->id)->where('id', $data['variant_id'] ?? 0)->exists()) {
            return 'Pick one of the variants of '.$product->name.'.';
        }

        return null;
    }

    private function rules(?Recipe $recipe = null): array
    {
        return [
            'name' => ['required', 'string', 'max:120', Rule::unique('recipes')->ignore($recipe)],
            'product_id' => 'nullable|exists:products,id',
            'variant_id' => 'nullable|integer',
            'unit' => 'nullable|string|max:20',
            'output_qty' => 'required|numeric|gt:0',
            'price' => 'nullable|numeric|min:0',
            'items' => 'required|array|min:1',
            'items.*.material_id' => 'required|distinct|exists:materials,id',
            'items.*.qty' => 'required|numeric|gt:0',
        ];
    }
}
