<?php

namespace Database\Seeders;

use App\Models;
use Illuminate\Database\Seeder;

/**
 * Demo stock transfers. Run after DemoDataSeeder (needs products and branches).
 * The JSON has branch codes and the old screen's product numbers, so they are turned into ids here.
 * It changes no stock.
 */
class StockTransferSeeder extends Seeder
{
    /** Old product number => SKU. */
    private const SKUS = [
        1 => '6603', 2 => '6600', 3 => '6595', 4 => '6598', 5 => '6622', 6 => '6621', 7 => '6624',
        8 => '6642', 9 => '6641', 10 => '6631', 11 => '7102', 12 => '7120', 13 => '7131',
    ];

    public function run(): void
    {
        if (Models\StockTransfer::query()->exists()) {
            return;
        }

        $rows = json_decode(file_get_contents(__DIR__.'/data/stock_transfers.json'), true);
        usort($rows, fn ($a, $b) => strcmp($a['ref'], $b['ref']));

        foreach ($rows as $old) {
            $transfer = Models\StockTransfer::create([
                'ref' => $old['ref'],
                'date' => $old['date'],
                'from_branch_id' => Models\Branch::where('code', $old['from'])->value('id'),
                'to_branch_id' => Models\Branch::where('code', $old['to'])->value('id'),
                'status' => $old['status'],
                'shipping_cost' => $old['ship'],
                'notes' => $old['notes'],
                'created_by' => $old['by'],
            ]);

            $this->addLines($transfer, $old['lines']);
            $this->addLogs($transfer, $old['log'], substr($old['date'], 0, 4));
        }
    }

    private function addLines(Models\StockTransfer $transfer, array $lines): void
    {
        $value = 0;
        $loss = 0;

        foreach ($lines as $line) {
            $product = Models\Product::with('unit:id,short_name')->where('sku', self::SKUS[$line['pid']])->first();
            if (! $product) {
                continue;
            }

            $transfer->items()->create([
                'product_id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'unit' => $product->unit ? $product->unit->short_name : null,
                'qty' => $line['qty'],
                'received_qty' => $line['recv'] ?? null,
                'cost' => $product->cost,
            ]);

            $value += $line['qty'] * $product->cost;
            if (isset($line['recv'])) {
                $loss += ($line['qty'] - $line['recv']) * $product->cost;
            }
        }

        $transfer->stock_value = round($value, 2);
        $transfer->loss_value = round($loss, 2);
        $transfer->save();
    }

    /** Log rows are [text, "29/09 · 10:24", tone], newest first. */
    private function addLogs(Models\StockTransfer $transfer, array $log, string $year): void
    {
        foreach (array_reverse($log) as $row) {
            $when = null;
            if (preg_match('/(\d{2})\/(\d{2})\D+(\d{2}):(\d{2})/', $row[1], $m)) {
                $when = "$year-$m[2]-$m[1] $m[3]:$m[4]:00";
            }

            $transfer->logs()->create(['message' => $row[0], 'tone' => $row[2], 'logged_at' => $when]);

            if (str_starts_with($row[0], 'Sent')) {
                $transfer->sent_at = $when;
            }
            if (str_starts_with($row[0], 'Received')) {
                $transfer->received_at = $when;
            }
        }

        $transfer->save();
    }
}
