<?php

namespace Database\Seeders;

use App\Models;
use Illuminate\Database\Seeder;

/**
 * Demo sales. Run after DemoDataSeeder (needs branches and contacts).
 * They have no lines. No stock or balance changes here.
 */
class SaleSeeder extends Seeder
{
    public function run(): void
    {
        if (Models\Sale::query()->exists()) {
            return;
        }

        $rows = json_decode(file_get_contents(__DIR__.'/data/sales.json'), true);
        usort($rows, fn ($a, $b) => strcmp($a['inv'], $b['inv']));

        foreach ($rows as $old) {
            $parts = preg_split('/[\/ ]/', $old['sold_at']);
            $status = $old['status'] === 'void' ? 'cancelled' : $old['status'];
            $paid = in_array($status, ['paid', 'partial'], true) ? round($old['total'] - $old['due'], 2) : 0;
            $method = $old['method'] === '—' ? null : $old['method'];

            $sale = Models\Sale::create([
                'ref' => $old['inv'],
                'sold_at' => '2026-'.$parts[1].'-'.$parts[0].' '.$parts[2].':00',
                'branch_id' => Models\StockSetting::current()->default_branch_id ?? Models\Branch::orderBy('id')->value('id'),
                'contact_id' => $old['customer'] === 'Walk in' ? null : Models\Contact::where('name', $old['customer'])->value('id'),
                'customer_name' => $old['customer'],
                'channel' => $old['channel'],
                'item_count' => $old['item_count'],
                'subtotal' => $old['total'],
                'total' => $old['total'],
                'paid' => $paid,
                'due' => $old['due'],
                'method' => $method,
                'status' => $status,
                'created_by' => $old['served_by'],
            ]);

            if ($paid > 0) {
                $sale->payments()->create([
                    'method' => strtolower((string) $method),
                    'amount' => $paid,
                    'user_name' => $old['served_by'],
                ]);
            }
        }
    }
}
