<?php

namespace Database\Seeders;

use App\Models;
use Illuminate\Database\Seeder;

/**
 * Demo materials, recipes and production orders. Run after DemoDataSeeder (needs branches and products).
 * The JSON has text keys, so they are turned into ids here. No product stock changes.
 */
class ManufacturingSeeder extends Seeder
{
    /** Old recipe id => SKU of the product it makes. */
    private const PRODUCTS = ['saus' => '6602'];

    public function run(): void
    {
        if (Models\Material::query()->exists()) {
            return;
        }

        $branch = Models\Branch::where('code', 'cold')->value('id') ?? Models\StockSetting::current()->default_branch_id;

        $materials = [];
        foreach ($this->rows('materials') as $old) {
            $material = Models\Material::create(['name' => $old['name'], 'unit' => $old['unit'], 'cost' => $old['cost']]);
            $materials[$old['code']] = $material;
            if ($old['stock'] > 0) {
                Models\Material::record($material->id, $branch, (float) $old['stock'], 'Opening stock');
            }
        }

        $recipes = [];
        foreach ($this->rows('recipes') as $old) {
            $sku = self::PRODUCTS[$old['id']] ?? null;
            $recipe = Models\Recipe::create([
                'name' => $old['name'],
                'product_id' => $sku ? Models\Product::where('sku', $sku)->value('id') : null,
                'unit' => $old['unit'],
                'output_qty' => $old['out'],
                'price' => $old['price'],
            ]);
            foreach ($old['lines'] as $line) {
                $recipe->items()->create(['material_id' => $materials[$line[0]]->id, 'qty' => $line[1]]);
            }
            $recipes[$old['id']] = $recipe->load('items.material');
        }

        $orders = $this->rows('production_orders');
        usort($orders, fn ($a, $b) => strcmp($a['ref'], $b['ref']));

        foreach ($orders as $old) {
            $recipe = $recipes[$old['rec']];
            $date = '2026-'.implode('-', array_reverse(explode('/', $old['date'])));

            $order = Models\ProductionOrder::create([
                'ref' => $old['ref'],
                'recipe_id' => $recipe->id,
                'branch_id' => Models\Branch::where('name', $old['loc'])->value('id') ?? $branch,
                'recipe_name' => $recipe->name,
                'unit' => $recipe->unit,
                'batches' => $old['b'],
                'date' => $date,
                'status' => $old['status'],
                'expected_qty' => $recipe->output_qty * $old['b'],
                'actual_qty' => $old['actual'],
                'material_cost' => round($recipe->batchCost() * $old['b'], 2),
                'materials_taken' => false,
                'finished_at' => $old['status'] === 'done' ? $date.' 17:00:00' : null,
            ]);

            if (in_array($old['status'], ['progress', 'done'], true)) {
                foreach ($recipe->items as $item) {
                    $order->items()->create(['material_id' => $item->material_id, 'name' => $item->material->name, 'unit' => $item->material->unit, 'qty' => $item->qty * $old['b'], 'cost' => $item->material->cost]);
                }
            }

            foreach (array_reverse($old['log']) as $log) {
                $order->logs()->create(['what' => $log[0], 'kind' => $log[2]]);
            }
        }
    }

    private function rows(string $file): array
    {
        return json_decode(file_get_contents(__DIR__."/data/$file.json"), true);
    }
}
