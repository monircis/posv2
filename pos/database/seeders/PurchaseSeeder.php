<?php

namespace Database\Seeders;

use App\Models;
use Illuminate\Database\Seeder;

/**
 * Demo purchases and returns with real lines. Run after DemoDataSeeder (needs products and contacts).
 * It adds no stock: the demo stock already counts these purchases.
 * Purchases that already have lines are left as they are.
 */
class PurchaseSeeder extends Seeder
{
    public function run(): void
    {
        if (! Models\Purchase::query()->exists()) {
            $this->seedHeaders();
        }

        foreach (Models\Purchase::orderBy('date')->get() as $purchase) {
            if (! $purchase->items()->exists()) {
                $this->addLines($purchase);
            }
        }

        foreach (Models\PurchaseReturn::orderBy('date')->get() as $return) {
            if (! $return->items()->exists() && $return->purchase_id) {
                $this->addReturnLine($return);
            }
        }
    }

    /** Fresh install: the purchases and returns from the JSON files (found by name). */
    private function seedHeaders(): void
    {
        $firstBranch = Models\Branch::orderBy('id')->value('id');

        $rows = json_decode(file_get_contents(__DIR__.'/data/purchases.json'), true);
        foreach ($rows as $old) {
            $supplier = Models\Contact::firstOrCreate(['name' => $old['supplier']], ['type' => 'Supplier']);
            $branchName = $old['location'] === 'Warehouse' ? 'Cold store' : $old['location'];
            $paid = $old['payment_status'] === 'paid' ? $old['total'] : ($old['payment_status'] === 'partial' ? $old['total'] - $old['due'] : 0);

            Models\Purchase::create([
                'ref' => $old['ref'],
                'date' => $old['date'],
                'supplier_id' => $supplier->id,
                'branch_id' => Models\Branch::where('name', $branchName)->value('id') ?? $firstBranch,
                'status' => $old['status'],
                'payment_status' => $old['payment_status'],
                'tax_rate' => 10,
                'total' => $old['total'],
                'paid' => $paid,
                'due' => $old['total'] - $paid,
                'received_at' => $old['status'] === 'received' ? $old['date'] : null,
            ]);
        }

        $rows = json_decode(file_get_contents(__DIR__.'/data/purchase_returns.json'), true);
        foreach ($rows as $old) {
            $purchase = Models\Purchase::where('ref', $old['purchase_ref'])->first();
            $subtotal = round($old['credit'] / 1.1, 2);

            Models\PurchaseReturn::create([
                'ref' => $old['ref'],
                'date' => $old['date'],
                'purchase_id' => $purchase ? $purchase->id : null,
                'purchase_ref' => $old['purchase_ref'],
                'supplier_id' => Models\Contact::firstOrCreate(['name' => $old['supplier']], ['type' => 'Supplier'])->id,
                'branch_id' => $purchase ? $purchase->branch_id : $firstBranch,
                'settlement' => $old['settlement'],
                'status' => $old['status'],
                'subtotal' => $subtotal,
                'tax' => round($old['credit'] - $subtotal, 2),
                'credit' => $old['credit'],
                'notes' => $old['reason'],
            ]);
        }
    }

    /** Three lines from the products of the branch, about the size of the old total. Totals are then worked out from the lines. */
    private function addLines(Models\Purchase $purchase): void
    {
        $products = Models\Product::with('unit:id,short_name')
            ->where('type', 'single')
            ->where('manage_stock', true)
            ->where('cost', '>', 0)
            ->whereHas('branches', function ($q) use ($purchase) {
                $q->where('branches.id', $purchase->branch_id);
            })
            ->orderBy('id')
            ->skip($purchase->id % 10)
            ->limit(3)
            ->get();

        if ($products->isEmpty()) {
            return;
        }

        $share = ($purchase->total / 1.1) / $products->count();
        $subtotal = 0;

        foreach ($products as $product) {
            $qty = max(1, round($share / $product->cost));
            $lineTotal = round($qty * $product->cost, 2);
            $subtotal += $lineTotal;

            $purchase->items()->create([
                'product_id' => $product->id,
                'name' => $product->name,
                'unit' => $product->unit ? $product->unit->short_name : null,
                'qty' => $qty,
                'cost' => $product->cost,
                'line_total' => $lineTotal,
            ]);
        }

        // Keep the status: paid stays fully paid, partial keeps what was paid.
        $paidBefore = $purchase->paid;
        $wasFullyPaid = $purchase->payment_status === 'paid';

        $purchase->subtotal = round($subtotal, 2);
        $purchase->tax = round($subtotal * $purchase->tax_rate / 100, 2);
        $purchase->total = round($purchase->subtotal + $purchase->tax, 2);
        $purchase->paid = $wasFullyPaid ? $purchase->total : min($paidBefore, $purchase->total);
        $purchase->updateDue();

        if ($purchase->paid > 0 && ! $purchase->payments()->exists()) {
            $purchase->payments()->create([
                'payment_account_id' => Models\PaymentAccount::where('id', 'cba')->value('id'),
                'account_name' => 'CBA Business',
                'amount' => $purchase->paid,
                'date' => $purchase->date->format('Y-m-d'),
            ]);
        }
    }

    /** One line against the purchase, about the size of the old credit. */
    private function addReturnLine(Models\PurchaseReturn $return): void
    {
        $item = Models\PurchaseItem::where('purchase_id', $return->purchase_id)->orderBy('id')->first();
        if (! $item || $item->cost <= 0) {
            return;
        }

        $qty = min($item->qty, max(1, round($return->subtotal / $item->cost)));
        $credit = round($qty * $item->cost, 2);

        $return->items()->create([
            'purchase_item_id' => $item->id,
            'name' => $item->name,
            'qty' => $qty,
            'cost' => $item->cost,
            'reason' => $return->notes ?: 'Quality',
            'credit' => $credit,
        ]);

        $return->subtotal = $credit;
        $return->tax = round($credit * 0.1, 2);
        $return->credit = round($return->subtotal + $return->tax, 2);
        $return->save();
    }
}
