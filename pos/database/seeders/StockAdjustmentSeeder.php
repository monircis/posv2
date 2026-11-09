<?php

namespace Database\Seeders;

use App\Models;
use Illuminate\Database\Seeder;

/**
 * Demo stock adjustments. Run after DemoDataSeeder (needs products and branches).
 * The JSON has branch codes and the old screen's product numbers, so they are turned into ids here.
 * It changes no stock.
 */
class StockAdjustmentSeeder extends Seeder
{
    /** Old product number => SKU. */
    private const SKUS = [
        1 => '6603', 2 => '6600', 3 => '6595', 4 => '6598', 5 => '6622', 6 => '6621', 7 => '6624',
        8 => '6642', 9 => '6641', 10 => '6631', 11 => '7102', 12 => '7120', 13 => '7131',
    ];

    public function run(): void
    {
        if (Models\StockAdjustment::query()->exists()) {
            return;
        }

        $rows = json_decode(file_get_contents(__DIR__.'/data/stock_adjustments.json'), true);
        usort($rows, fn ($a, $b) => strcmp($a['ref'], $b['ref']));

        foreach ($rows as $old) {
            $adjustment = Models\StockAdjustment::create([
                'ref' => $old['ref'],
                'date' => $old['date'],
                'branch_id' => Models\Branch::where('code', $old['loc'])->value('id'),
                'type' => $old['type'],
                'notes' => $old['notes'] ?: null,
                'created_by' => $old['by'],
                'approved_by' => $old['approved'] === '—' ? null : $old['approved'],
            ]);

            $increase = 0;
            $decrease = 0;

            foreach ($old['lines'] as $line) {
                $product = Models\Product::with('unit:id,short_name')->where('sku', self::SKUS[$line['pid']])->first();
                if (! $product) {
                    continue;
                }

                $adjustment->items()->create([
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'unit' => $product->unit ? $product->unit->short_name : null,
                    'before_qty' => $line['before'],
                    'change_qty' => $line['delta'],
                    'cost' => $product->cost,
                    'reason' => $line['reason'],
                ]);

                if ($line['delta'] > 0) {
                    $increase += $line['delta'] * $product->cost;
                } else {
                    $decrease -= $line['delta'] * $product->cost;
                }
            }

            $adjustment->increase_value = round($increase, 2);
            $adjustment->decrease_value = round($decrease, 2);
            $adjustment->save();
        }
    }
}
