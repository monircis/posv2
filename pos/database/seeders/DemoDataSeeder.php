<?php

namespace Database\Seeders;

use App\Models;
use Illuminate\Database\Seeder;

/**
 * The sample records the Vue screens were designed with, so a fresh install
 * looks like the prototype. The JSON files in seeders/data were exported from
 * the frontend's demo data.
 */
class DemoDataSeeder extends Seeder
{
    private const FILES = [
        'customer_groups' => Models\CustomerGroup::class, // before contacts: contacts point to a group
        'contacts' => Models\Contact::class,
        'payment_accounts' => Models\PaymentAccount::class,
        'ledger_accounts' => Models\LedgerAccount::class,
        'journals' => Models\Journal::class,
        'orders' => Models\Order::class,
        'app_settings' => Models\AppSetting::class,
    ];

    public function run(): void
    {
        foreach (self::FILES as $file => $model) {
            if ($model::query()->exists()) {
                continue; // already seeded
            }
            $rows = json_decode(file_get_contents(__DIR__."/data/$file.json"), true);
            // One second apart, so lists ordered by "added" keep the prototype's order.
            $time = now()->subSeconds(count($rows));
            foreach ($rows as $row) {
                // The JSON has the group name: a customer group, or a supplier pay term ("30-day terms").
                if ($model === Models\Contact::class && isset($row['group'])) {
                    $row['customer_group_id'] = Models\CustomerGroup::where('name', $row['group'])->value('id');
                    if (! $row['customer_group_id'] && preg_match('/(\d+)-day/', $row['group'], $m)) {
                        $row['pay_term'] = $m[1].' days';
                    }
                }
                // A payment account's branch is by branch code in the JSON ("loc").
                if ($model === Models\PaymentAccount::class && ! empty($row['loc'])) {
                    $row['branch_id'] = Models\Branch::where('code', $row['loc'])->value('id');
                }
                // The JSON has some old columns: keep only what the model has now.
                $record = new $model;
                foreach ($record->getFillable() as $column) {
                    if (array_key_exists($column, $row)) {
                        $record->$column = $row[$column];
                    }
                }
                $record->created_at = $record->updated_at = $time = $time->copy()->addSecond();
                $record->save();
            }
        }

        $this->seedProducts();
    }

    /**
     * products.json still has the old columns (category and unit by name, stock per branch code
     * in product_stocks.json), so they are turned into ids here. Needs ProductListsSeeder first.
     */
    private function seedProducts(): void
    {
        if (Models\Product::query()->exists()) {
            return;
        }

        $products = json_decode(file_get_contents(__DIR__.'/data/products.json'), true);
        $stocks = json_decode(file_get_contents(__DIR__.'/data/product_stocks.json'), true);
        $gst = Models\TaxRate::where('code', 'GST')->value('id');

        foreach ($products as $old) {
            $brandId = $old['description'] ? Models\Brand::whereRaw('lower(name) = ?', [strtolower($old['description'])])->value('id') : null;

            $product = Models\Product::create([
                'name' => $old['name'],
                'sku' => $old['sku'],
                'category_id' => Models\Category::where('name', $old['category'])->whereNull('parent_id')->value('id'),
                'brand_id' => $brandId,
                'unit_id' => Models\Unit::where('short_name', $old['unit'])->value('id'),
                'description' => $brandId ? null : $old['description'],
                'cost' => $old['cost'],
                'price' => $old['price'],
                'tax_rate_id' => $gst,
                'manage_stock' => $old['stock'] !== null,
                'alert_qty' => $old['alert'] ?? 0,
                'allow_negative_stock' => $old['stock'] === null,
                'pos_favourite' => $old['pos_favourite'] ?? false,
                'send_to_kitchen' => $old['kitchen'] ?? false,
            ]);
            $product->branches()->sync(Models\Branch::pluck('id')); // sold at every branch

            foreach ($stocks as $stock) {
                $branchId = Models\Branch::where('code', $stock['location'])->value('id');
                if ($stock['sku'] === $old['sku'] && $branchId) {
                    Models\StockMovement::record($product->id, null, $branchId, $stock['qty'], 'Opening stock');
                }
            }
        }
    }
}
