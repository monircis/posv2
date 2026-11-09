<?php

namespace App\Http\Controllers\Api\Reports;

use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\ProductStock;
use Illuminate\Http\Request;

/** Stock reports: Stock on hand and Manufacturing. */
class StockReportController extends ReportController
{
    /**
     * Stock on hand
     *
     * Quantity now, value at cost and at selling price, and Low / Out of stock against the alert quantity.
     * Only products that manage stock. Filters: branch_id (all branches adds them up), category_id.
     * Dates are not used: this is the stock right now.
     */
    public function stock(Request $request)
    {
        [, , $branchId] = $this->filters($request);
        $categoryIds = $this->categoryIds($request->integer('category_id') ?: null);

        $products = Product::with('unit:id,short_name', 'category:id,name,parent_id', 'category.parent:id,name', 'variants')
            ->where('manage_stock', true)->where('active', true)
            ->when($categoryIds, fn ($q) => $q->whereIn('category_id', $categoryIds))
            ->orderBy('name')->get();

        $stocks = ProductStock::whereIn('product_id', $products->pluck('id'))
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))->get();
        // The alert quantity is per branch: for all branches it is times the number of branches.
        $branchCount = $branchId ? 1 : max(1, Branch::where('active', true)->count());

        $rows = [];
        foreach ($products as $product) {
            $lines = $product->type === 'variable' ? $product->variants : [null];
            foreach ($lines as $variant) {
                $qty = $stocks->where('product_id', $product->id)->where('variant_id', $variant ? $variant->id : null)->sum('qty');
                $cost = $variant ? (float) $variant->cost : (float) $product->cost;
                $price = $variant ? (float) $variant->price : (float) $product->price;
                $alert = (float) $product->alert_qty * $branchCount;
                $status = $qty <= 0 ? ['Out of stock', 'error'] : ($alert > 0 && $qty < $alert ? ['Low', 'warn'] : ['OK', 'success']);
                $category = $product->category ? ($product->category->parent ? $product->category->parent->name : $product->category->name) : '';
                $rows[] = ['c' => [
                    $product->name.($variant ? ' · '.$variant->name : ''), $category, $qty,
                    $product->unit ? $product->unit->short_name : '', $qty * $cost, $qty * $price, $status,
                ]];
            }
        }

        return [
            'kpis' => [
                $this->kpi('Value at cost', array_sum(array_map(fn ($r) => max(0, $r['c'][4]), $rows)), 'money'),
                $this->kpi('Value at selling price', array_sum(array_map(fn ($r) => max(0, $r['c'][5]), $rows)), 'money'),
                $this->kpi('Low stock', count(array_filter($rows, fn ($r) => $r['c'][6][0] === 'Low')), 'int', false),
                $this->kpi('Out of stock', count(array_filter($rows, fn ($r) => $r['c'][6][0] === 'Out of stock')), 'int', false),
            ],
            'chart' => ['type' => 'rank', 'title' => 'Value at cost', 'items' => array_map(fn ($r) => [$r['c'][0], $this->r2(max(0, $r['c'][4]))], $rows)],
            'table' => [
                'title' => 'Products',
                'cols' => [
                    $this->col('Product', 'text', '1.7fr'), $this->col('Category', 'text', '0.8fr'), $this->col('On hand', 'qty', '0.7fr'),
                    $this->col('Unit', 'text', '0.4fr'), $this->col('At cost', 'money', '1fr', true), $this->col('At selling price', 'money', '1fr', true),
                    $this->col('Status', 'badge', '0.9fr'),
                ],
                'rows' => $rows,
            ],
        ];
    }

    /**
     * Manufacturing
     *
     * Production orders dated in the period: output, material cost, cost per unit and yield (actual / expected).
     */
    public function manufacturing(Request $request)
    {
        [$from, $to, $branchId] = $this->filters($request);
        $orders = ProductionOrder::whereBetween('date', [$from, $to])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->orderBy('date')->get();

        $names = ['planned' => ['Planned', 'neutral'], 'progress' => ['In progress', 'info'], 'done' => ['Done', 'success'], 'cancelled' => ['Cancelled', 'neutral']];
        $rows = [];
        foreach ($orders as $o) {
            $out = $o->actual_qty;
            $rows[] = ['c' => [
                $o->ref, $o->recipe_name, $o->expected_qty, $out, $o->unit, $o->material_cost,
                $out ? $o->material_cost / $out : null,
                $out && $o->expected_qty ? $out / $o->expected_qty * 100 : null,
                $names[$o->status] ?? [ucfirst($o->status), 'neutral'],
            ]];
        }

        $done = $orders->where('status', 'done');
        $yields = $done->filter(fn ($o) => $o->expected_qty > 0 && $o->actual_qty)->map(fn ($o) => $o->actual_qty / $o->expected_qty * 100);

        return [
            'kpis' => [
                $this->kpi('Production orders', $orders->count(), 'int'),
                $this->kpi('Output (kg)', $done->where('unit', 'kg')->sum('actual_qty'), 'int'),
                $this->kpi('Material cost', $orders->sum('material_cost'), 'money', false),
                $this->kpi('Average yield', $yields->count() ? $yields->avg() : 0, 'pct'),
            ],
            'chart' => ['type' => 'rank', 'title' => 'Material cost by order', 'items' => array_map(fn ($r) => [$r['c'][0].' · '.$r['c'][1], $this->r2($r['c'][5])], $rows)],
            'table' => [
                'title' => 'Production orders',
                'cols' => [
                    $this->col('Order', 'text', '0.8fr'), $this->col('Product', 'text', '1.5fr'), $this->col('Expected', 'qty', '0.7fr', true),
                    $this->col('Made', 'qty', '0.7fr', true), $this->col('Unit', 'text', '0.4fr'), $this->col('Materials', 'money', '1fr', true),
                    $this->col('Cost / unit', 'money', '0.8fr'), $this->col('Yield', 'pct', '0.6fr'), $this->col('Status', 'badge', '0.9fr'),
                ],
                'rows' => $rows,
            ],
        ];
    }
}
