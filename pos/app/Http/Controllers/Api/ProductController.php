<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductSetting;
use App\Models\ProductStock;
use App\Models\ProductVariant;
use App\Models\SaleItem;
use App\Models\StockMovement;
use App\Models\StockSetting;
use App\Models\TaxRate;
use App\Models\TaxSetting;
use App\Models\Unit;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/** Products: single products and variable products (with variants), stock per branch. */
#[Group('Products', 'Single products have one SKU, price and stock. Variable products have variants, each with its own SKU, price and stock.', weight: 9)]
class ProductController extends Controller
{
    /**
     * List products
     *
     * 25 per page. Filters: `search` (name, SKU or barcode), `category_id` (includes its sub-categories),
     * `branch_id` (products sold at that branch; stock is then only that branch's), `stock` (in, low, out).
     * Also returns `counts` for the stock tabs and a `summary`.
     * Needs `products.view` or `sell.view`.
     */
    public function index(Request $request)
    {
        $branchId = $request->filled('branch_id') ? (int) $request->input('branch_id') : null;

        $query = $this->filtered($request);
        $this->stockFilter($query, $request->input('stock'), $branchId);

        $page = $query->with('category:id,name', 'brand:id,name', 'unit:id,name,short_name', 'taxRate:id,name,rate')
            ->withSum(['stocks as stock_qty' => function ($q) use ($branchId) {
                if ($branchId) {
                    $q->where('branch_id', $branchId);
                }
            }], 'qty')
            ->withCount('branches')
            ->withMin('variants as price_min', 'price')
            ->withMax('variants as price_max', 'price')
            ->orderBy('name')
            ->paginate(25);

        // Tab counts use the same search and category, but each its own stock filter.
        $counts = [];
        foreach (['all', 'in', 'low', 'out'] as $tab) {
            $countQuery = $this->filtered($request);
            $this->stockFilter($countQuery, $tab, $branchId);
            $counts[$tab] = $countQuery->count();
        }

        return [
            'data' => $page->items(),
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'total' => $page->total(),
            'counts' => $counts,
            'summary' => [
                'products' => Product::count(),
                'categories' => Category::count(),
                'stock_value' => $this->stockValue(),
            ],
        ];
    }

    /**
     * Form defaults and choices
     *
     * What the add-product form starts with (from Settings > Products, Tax and Stock) and the tax rates to pick from.
     * Needs `products.view`.
     */
    public function formData()
    {
        $productSettings = ProductSetting::current();
        $taxSettings = TaxSetting::current();

        return [
            'defaults' => [
                'auto_sku' => $productSettings->auto_sku,
                'next_sku' => $this->nextSku(),
                'unit_id' => Unit::where('short_name', $productSettings->default_unit)->value('id'),
                'alert_qty' => $productSettings->low_stock_alert,
                'barcode_type' => $productSettings->barcode_type,
                'price_includes_tax' => $taxSettings->prices_include_tax,
                'tax_rate_id' => $taxSettings->gst_registered ? TaxRate::where('active', true)->where('rate', '>', 0)->orderBy('id')->value('id') : null,
                'branch_id' => StockSetting::current()->default_branch_id,
            ],
            'tax_rates' => TaxRate::where('active', true)->orderBy('id')->get(['id', 'name', 'rate']),
        ];
    }

    /**
     * Add product
     *
     * Leave `sku` empty to make one automatically (Settings > Products). A variable product needs at least one row in `variants`.
     * `branch_ids` = the branches that sell the product (at least one). Opening stock is per branch:
     * `opening_stock` (single) or `variants.*.opening_stock`, e.g. `{"1": 20, "2": 5}` (branch id => quantity).
     * Needs `products.create`.
     */
    public function store(Request $request)
    {
        $data = $request->validate($this->rules());

        if (empty($data['sku'])) {
            $data['sku'] = $this->nextSku();
            if (! $data['sku']) {
                return response()->json(['message' => 'Enter a SKU (automatic SKUs are off in Settings > Products).'], 422);
            }
        }
        $error = $this->checkVariantSkus($data);
        if ($error) {
            return response()->json(['message' => $error], 422);
        }

        $userName = $request->user()->name;

        $product = DB::transaction(function () use ($data, $userName) {
            $product = Product::create($this->productFields($data));
            $product->branches()->sync($data['branch_ids']);

            if ($product->type === 'variable') {
                $this->saveVariants($product, $data['variants'], $data['branch_ids'], $userName);
            } elseif ($product->manage_stock) {
                $this->openingStock($product->id, null, $data['opening_stock'] ?? [], $data['branch_ids'], $userName);
            }

            return $product;
        });

        return response()->json($this->details($product), 201);
    }

    /**
     * Show product
     *
     * With category, brand, unit, tax rate, variants (each with `stock_qty`) and `stocks` per branch.
     * Needs `products.view` or `sell.view`.
     */
    public function show(Product $product)
    {
        return $this->details($product);
    }

    /**
     * Update product
     *
     * Send all fields, and every variant you want to keep (with its `id`). Variants left out are deleted,
     * but only when they have no stock. The type cannot change while the product has stock.
     * A branch can only be removed from `branch_ids` when the product has no stock there. Needs `products.edit`.
     */
    public function update(Request $request, Product $product)
    {
        $data = $request->validate($this->rules($product));

        if (empty($data['sku'])) {
            $data['sku'] = $product->sku;
        }
        if ($data['type'] !== $product->type && $this->hasStock($product)) {
            return response()->json(['message' => 'Set the stock to 0 before changing between single and variable.'], 422);
        }
        $error = $this->checkVariantSkus($data, $product);
        if ($error) {
            return response()->json(['message' => $error], 422);
        }

        // Variants that are no longer in the list must have no stock.
        $keepIds = [];
        foreach ($data['variants'] ?? [] as $row) {
            if (! empty($row['id'])) {
                $keepIds[] = $row['id'];
            }
        }
        $removed = $data['type'] === 'variable' ? $product->variants()->whereNotIn('id', $keepIds)->get() : $product->variants;
        foreach ($removed as $variant) {
            if (ProductStock::where('variant_id', $variant->id)->where('qty', '!=', 0)->exists()) {
                return response()->json(['message' => 'The variant '.$variant->name.' still has stock, so it cannot be removed.'], 422);
            }
        }

        // Branches that are no longer ticked must have no stock of this product.
        $removedBranches = $product->branches()->whereNotIn('branches.id', $data['branch_ids'])->get();
        foreach ($removedBranches as $branch) {
            if (ProductStock::where('product_id', $product->id)->where('branch_id', $branch->id)->where('qty', '!=', 0)->exists()) {
                return response()->json(['message' => $branch->name.' still has stock of '.$product->name.'. Adjust it to 0 there first.'], 422);
            }
        }

        $userName = $request->user()->name;

        DB::transaction(function () use ($product, $data, $removed, $userName) {
            $product->update($this->productFields($data));
            $product->branches()->sync($data['branch_ids']);

            foreach ($removed as $variant) {
                $variant->delete();
            }
            if ($product->type === 'variable') {
                $this->saveVariants($product, $data['variants'], $data['branch_ids'], $userName);
            }
        });

        return $this->details($product->fresh());
    }

    /**
     * Delete product
     *
     * Refused (422) while it has stock or appears in sales (set it inactive instead). Needs `products.delete`.
     */
    public function destroy(Product $product)
    {
        if ($this->hasStock($product)) {
            return response()->json(['message' => $product->name.' still has stock. Adjust it to 0 first.'], 422);
        }

        if (SaleItem::where('product_id', $product->id)->exists()) {
            return response()->json(['message' => $product->name.' appears in sales, so it cannot be deleted. Set it inactive instead.'], 422);
        }

        if ($product->image) {
            Storage::disk('public')->delete($product->image);
        }
        $product->delete(); // variants, stock rows and movements are deleted with it

        return response()->noContent();
    }

    // ---------------------------------------------------------------------

    /** The product with everything the details page and the form need. */
    private function details(Product $product): array
    {
        $product->load('category:id,name,parent_id', 'brand:id,name', 'unit:id,name,short_name,allow_decimals', 'taxRate:id,name,rate', 'variants', 'branches:id,name');

        $stocks = ProductStock::with('branch:id,name')->where('product_id', $product->id)->get();

        $row = $product->toArray();
        $row['stock_qty'] = $stocks->sum('qty');
        $row['stocks'] = $stocks;
        $row['branch_ids'] = $product->branches->pluck('id');
        foreach ($row['variants'] as $i => $variant) {
            $row['variants'][$i]['stock_qty'] = $stocks->where('variant_id', $variant['id'])->sum('qty');
        }

        return $row;
    }

    /** Products matching `search` and `category_id` (sub-categories included). */
    private function filtered(Request $request)
    {
        $query = Product::query();

        $search = trim((string) $request->input('search'));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%$search%")
                    ->orWhere('sku', 'like', "%$search%")
                    ->orWhere('barcode', $search)
                    ->orWhereHas('variants', function ($v) use ($search) {
                        $v->where('sku', 'like', "%$search%")->orWhere('barcode', $search);
                    });
            });
        }

        if ($request->filled('category_id')) {
            $query->whereIn('category_id', $this->categoryAndBelow((int) $request->input('category_id')));
        }

        if ($request->filled('branch_id')) {
            $query->whereHas('branches', function ($q) use ($request) {
                $q->where('branches.id', $request->input('branch_id'));
            });
        }

        return $query;
    }

    /**
     * in = above the alert quantity, low = at or below it, out = 0 or less. Only products that manage stock.
     * With a branch, only the stock at that branch counts.
     */
    private function stockFilter($query, ?string $stock, ?int $branchId = null): void
    {
        $sql = '(select coalesce(sum(qty), 0) from product_stocks where product_stocks.product_id = products.id'
            .($branchId ? ' and product_stocks.branch_id = '.$branchId : '').')';
        if ($stock === 'in') {
            $query->where('manage_stock', true)->whereRaw("$sql > alert_qty");
        } elseif ($stock === 'low') {
            $query->where('manage_stock', true)->whereRaw("$sql > 0 and $sql <= alert_qty");
        } elseif ($stock === 'out') {
            $query->where('manage_stock', true)->whereRaw("$sql <= 0");
        }
    }

    /** The category id and the ids of every category below it. */
    private function categoryAndBelow(int $id): array
    {
        $ids = [$id];
        $before = 0;
        while ($before !== count($ids)) {
            $before = count($ids);
            $children = Category::whereIn('parent_id', $ids)->pluck('id')->all();
            $ids = array_values(array_unique(array_merge($ids, $children)));
        }

        return $ids;
    }

    /** Stock value at cost: quantity x cost (the variant's cost for variants). */
    private function stockValue(): float
    {
        return (float) DB::table('product_stocks')
            ->join('products', 'products.id', '=', 'product_stocks.product_id')
            ->leftJoin('product_variants', 'product_variants.id', '=', 'product_stocks.variant_id')
            ->where('product_stocks.qty', '>', 0)
            ->selectRaw('sum(product_stocks.qty * coalesce(product_variants.cost, products.cost)) as total')
            ->value('total');
    }

    private function hasStock(Product $product): bool
    {
        return ProductStock::where('product_id', $product->id)->where('qty', '!=', 0)->exists();
    }

    /**
     * Next automatic SKU: the SKU prefix from Settings > Products plus the next free number.
     * Prefix "66" with 6600 ... 6645 in use gives "6646". Null when automatic SKUs are off.
     */
    private function nextSku(): ?string
    {
        $settings = ProductSetting::current();
        if (! $settings->auto_sku) {
            return null;
        }

        $prefix = (string) $settings->sku_prefix;
        $max = 0;
        $width = 2;
        foreach (Product::where('sku', 'like', $prefix.'%')->pluck('sku') as $sku) {
            $rest = substr($sku, strlen($prefix));
            if ($rest !== '' && ctype_digit($rest) && (int) $rest >= $max) {
                $max = (int) $rest;
                $width = strlen($rest);
            }
        }

        $sku = $prefix.str_pad((string) ($max + 1), $width, '0', STR_PAD_LEFT);
        while (Product::where('sku', $sku)->exists() || ProductVariant::where('sku', $sku)->exists()) {
            $max++;
            $sku = $prefix.str_pad((string) ($max + 1), $width, '0', STR_PAD_LEFT);
        }

        return $sku;
    }

    /** Variant SKUs must be unique: inside the form, against other variants and against product SKUs. */
    private function checkVariantSkus(array $data, ?Product $product = null): ?string
    {
        if (($data['type'] ?? 'single') !== 'variable') {
            return null;
        }

        $seen = [];
        foreach ($data['variants'] as $row) {
            $sku = $row['sku'] ?? '';
            if ($sku === '') {
                continue;
            }
            if (in_array($sku, $seen, true) || $sku === $data['sku']) {
                return 'The SKU '.$sku.' is used twice.';
            }
            $seen[] = $sku;

            $takenByVariant = ProductVariant::where('sku', $sku)->where('id', '!=', $row['id'] ?? 0)->exists();
            $takenByProduct = Product::where('sku', $sku)->where('id', '!=', $product?->id ?? 0)->exists();
            if ($takenByVariant || $takenByProduct) {
                return 'The SKU '.$sku.' is already used by another product.';
            }
        }

        return null;
    }

    /** Create or update the variants of a variable product, in the order given. */
    private function saveVariants(Product $product, array $rows, array $branchIds, string $userName): void
    {
        foreach ($rows as $i => $row) {
            $fields = [
                'name' => $row['name'],
                'barcode' => $row['barcode'] ?? null,
                'cost' => $row['cost'] ?? 0,
                'price' => $row['price'] ?? 0,
                'wholesale_price' => $row['wholesale_price'] ?? null,
                'active' => $row['active'] ?? true,
                'sort_order' => $i,
            ];

            $variant = ! empty($row['id']) ? $product->variants()->where('id', $row['id'])->first() : null;

            if ($variant) {
                if (! empty($row['sku'])) {
                    $fields['sku'] = $row['sku'];
                }
                $variant->update($fields);
                continue;
            }

            // New variant: SKU is the product SKU plus a number (6595-1, 6595-2 ...) when left empty.
            $fields['sku'] = ! empty($row['sku']) ? $row['sku'] : $this->nextVariantSku($product);
            $variant = $product->variants()->create($fields);

            if ($product->manage_stock) {
                $this->openingStock($product->id, $variant->id, $row['opening_stock'] ?? [], $branchIds, $userName);
            }
        }
    }

    /**
     * Opening stock per branch: $quantities is [branch id => quantity].
     * Only branches that sell the product and quantities above 0 are saved.
     */
    private function openingStock(int $productId, ?int $variantId, array $quantities, array $branchIds, string $userName): void
    {
        foreach ($quantities as $branchId => $qty) {
            if (in_array((int) $branchId, $branchIds) && $qty > 0) {
                StockMovement::record($productId, $variantId, (int) $branchId, $qty, 'Opening stock', null, null, $userName);
            }
        }
    }

    private function nextVariantSku(Product $product): string
    {
        $n = 1;
        while (ProductVariant::where('sku', $product->sku.'-'.$n)->exists()) {
            $n++;
        }

        return $product->sku.'-'.$n;
    }

    /** The product columns from the validated form (not variants or opening stock). */
    private function productFields(array $data): array
    {
        $fields = $data;
        unset($fields['variants'], $fields['opening_stock'], $fields['branch_ids']);
        $fields['alert_qty'] = $fields['alert_qty'] ?? 0;

        // A variable product is priced per variant.
        if ($fields['type'] === 'variable') {
            $fields['cost'] = 0;
            $fields['price'] = 0;
            $fields['wholesale_price'] = null;
        }

        return $fields;
    }

    private function rules(?Product $product = null): array
    {
        return [
            'name' => 'required|string|max:190',
            'sku' => ['nullable', 'string', 'max:40', Rule::unique('products')->ignore($product)],
            'type' => 'required|in:single,variable',
            'category_id' => 'nullable|exists:categories,id',
            'brand_id' => 'nullable|exists:brands,id',
            'unit_id' => 'required|exists:units,id',
            'barcode' => 'nullable|string|max:60',
            'barcode_type' => 'required|in:EAN-13,Code 128,QR',
            'description' => 'nullable|string|max:255',
            // Pricing
            'cost' => 'required_if:type,single|nullable|numeric|min:0',
            'price' => 'required_if:type,single|nullable|numeric|min:0',
            'wholesale_price' => 'nullable|numeric|min:0',
            'tax_rate_id' => 'nullable|exists:tax_rates,id',
            'price_includes_tax' => 'boolean',
            // Stock
            'manage_stock' => 'boolean',
            'alert_qty' => 'nullable|numeric|min:0',
            'allow_negative_stock' => 'boolean',
            'opening_stock' => 'nullable|array',                // new single products only: branch id => quantity
            'opening_stock.*' => 'nullable|numeric|min:0',
            // Branches that sell the product
            'branch_ids' => 'required|array|min:1',
            'branch_ids.*' => 'integer|exists:branches,id',
            // POS and kitchen
            'available_at_pos' => 'boolean',
            'pos_favourite' => 'boolean',
            'send_to_kitchen' => 'boolean',
            'prep_minutes' => 'nullable|integer|min:0|max:600',
            'active' => 'boolean',
            // Variants (variable products)
            'variants' => 'required_if:type,variable|array',
            'variants.*.id' => 'nullable|integer',
            'variants.*.name' => 'required|string|max:120',
            'variants.*.sku' => 'nullable|string|max:40',
            'variants.*.barcode' => 'nullable|string|max:60',
            'variants.*.cost' => 'required|numeric|min:0',
            'variants.*.price' => 'required|numeric|min:0',
            'variants.*.wholesale_price' => 'nullable|numeric|min:0',
            'variants.*.active' => 'boolean',
            'variants.*.opening_stock' => 'nullable|array',     // new variants only: branch id => quantity
            'variants.*.opening_stock.*' => 'nullable|numeric|min:0',
        ];
    }
}
