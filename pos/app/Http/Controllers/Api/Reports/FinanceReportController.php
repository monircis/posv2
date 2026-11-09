<?php

namespace App\Http\Controllers\Api\Reports;

use App\Models\Expense;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Purchase;
use App\Models\PurchaseReturn;
use App\Models\SaleItem;
use Illuminate\Http\Request;

/**
 * Finance reports: Profit & loss, Expenses and GST / BAS.
 * Profit & loss is without GST. Sales are the ones that count (paid, partial, due).
 */
class FinanceReportController extends ReportController
{
    /**
     * Profit & loss
     *
     * Net sales (without GST) less cost of goods sold (quantity sold x cost price now) less expenses (without GST).
     */
    public function profitLoss(Request $request)
    {
        [$from, $to, $branchId] = $this->filters($request);
        $periods = $this->periods($from, $to);
        $sales = $this->salesIn($from, $to, $branchId, null);
        $cogsBySale = $this->costOfGoods($sales->pluck('id')->all());
        $expenses = $this->expensesIn($from, $to, $branchId);

        $netSales = 0.0;
        $cogs = 0.0;
        $profit = array_fill(0, count($periods), 0.0);
        foreach ($sales as $sale) {
            $net = $sale->total - $sale->gst;
            $cost = $cogsBySale[$sale->id] ?? 0;
            $netSales += $net;
            $cogs += $cost;
            $profit[$this->periodOf($this->shopDay($sale->sold_at), $periods)] += $net - $cost;
        }

        $byCategory = [];
        foreach ($expenses as $e) {
            $name = $e->category ? $e->category->name : 'Other';
            $exGst = $e->amount - $e->gst_amount;
            $byCategory[$name] = ($byCategory[$name] ?? 0) + $exGst;
            $profit[$this->periodOf($e->date->toDateString(), $periods)] -= $exGst;
        }
        arsort($byCategory);
        $totalExpenses = array_sum($byCategory);

        $pct = fn ($v) => $netSales ? $v / $netSales * 100 : 0;
        $rows = [
            ['c' => ['Net sales (without GST)', $netSales, 100.0], 'bold' => 1],
            ['c' => ['Cost of goods sold', -$cogs, -$pct($cogs)]],
            ['c' => ['Gross profit', $netSales - $cogs, $pct($netSales - $cogs)], 'bold' => 1],
        ];
        foreach ($byCategory as $name => $amount) {
            $rows[] = ['c' => ['   '.$name, -$amount, -$pct($amount)]];
        }
        $rows[] = ['c' => ['Total expenses', -$totalExpenses, -$pct($totalExpenses)], 'bold' => 1];
        $rows[] = ['c' => ['Net profit', $netSales - $cogs - $totalExpenses, $pct($netSales - $cogs - $totalExpenses)], 'bold' => 2];

        return [
            'kpis' => [
                $this->kpi('Net sales', $netSales, 'money'),
                $this->kpi('Gross profit', $netSales - $cogs, 'money'),
                $this->kpi('Gross margin', $pct($netSales - $cogs), 'pct'),
                $this->kpi('Net profit', $netSales - $cogs - $totalExpenses, 'money'),
            ],
            'chart' => ['type' => 'time', 'labels' => array_column($periods, 'label'), 'series' => [['name' => 'Net profit', 'values' => array_map(fn ($v) => $this->r2($v), $profit)]]],
            'table' => [
                'title' => 'Statement',
                'cols' => [$this->col('Line', 'text', '2.4fr'), $this->col('Amount', 'money'), $this->col('% of sales', 'pct', '0.7fr')],
                'rows' => $rows,
                'no_totals' => true,
                'no_sort' => true,
            ],
        ];
    }

    /**
     * Expenses
     *
     * Expenses dated in the period, by category: entries, amount, GST you can claim and share of the total.
     */
    public function expenses(Request $request)
    {
        [$from, $to, $branchId] = $this->filters($request);
        $expenses = $this->expensesIn($from, $to, $branchId);

        $byCategory = [];
        foreach ($expenses as $e) {
            $name = $e->category ? $e->category->name : 'Other';
            if (! isset($byCategory[$name])) {
                $byCategory[$name] = ['n' => 0, 'amount' => 0.0, 'gst' => 0.0, 'unpaid' => 0.0];
            }
            $byCategory[$name]['n']++;
            $byCategory[$name]['amount'] += $e->amount;
            $byCategory[$name]['gst'] += $e->gst_amount;
            if (! $e->paid_date) {
                $byCategory[$name]['unpaid'] += $e->amount;
            }
        }
        $total = $expenses->sum('amount');

        $rows = [];
        foreach ($byCategory as $name => $c) {
            $rows[] = ['c' => [$name, $c['n'], $c['amount'], $c['gst'], $c['unpaid'], $total ? $c['amount'] / $total * 100 : 0]];
        }
        usort($rows, fn ($a, $b) => $b['c'][2] <=> $a['c'][2]);

        return [
            'kpis' => [
                $this->kpi('Total expenses', $total, 'money', false),
                $this->kpi('Entries', $expenses->count(), 'int'),
                $this->kpi('Largest category', $rows ? $rows[0]['c'][0] : '—', 'text'),
                $this->kpi('GST claimable', $expenses->sum('gst_amount'), 'money'),
            ],
            'chart' => ['type' => 'rank', 'title' => 'By category', 'items' => array_map(fn ($r) => [$r['c'][0], $this->r2($r['c'][2])], $rows)],
            'table' => [
                'title' => 'Categories',
                'cols' => [
                    $this->col('Category', 'text', '1.7fr'), $this->col('Entries', 'int', '0.6fr', true), $this->col('Amount', 'money', '1fr', true),
                    $this->col('GST', 'money', '1fr', true), $this->col('Not paid yet', 'money', '1fr', true), $this->col('Share', 'pct', '0.7fr'),
                ],
                'rows' => $rows,
            ],
        ];
    }

    /**
     * GST / BAS
     *
     * G1 total sales (with GST), 1A GST on sales, G11 purchases and expenses (with GST),
     * 1B GST on purchases and expenses (less GST on supplier returns), and the net GST payable.
     */
    public function gst(Request $request)
    {
        [$from, $to, $branchId] = $this->filters($request);
        $periods = $this->periods($from, $to);
        $sales = $this->salesIn($from, $to, $branchId, null);
        $purchases = Purchase::where('status', 'received')->whereBetween('date', [$from, $to])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))->get();
        $returns = PurchaseReturn::whereBetween('date', [$from, $to])->when($branchId, fn ($q) => $q->where('branch_id', $branchId))->get();
        $expenses = $this->expensesIn($from, $to, $branchId);

        $collected = array_fill(0, count($periods), 0.0);
        $paid = array_fill(0, count($periods), 0.0);
        foreach ($sales as $sale) {
            $collected[$this->periodOf($this->shopDay($sale->sold_at), $periods)] += $sale->gst;
        }
        foreach ($purchases as $po) {
            $paid[$this->periodOf($po->date->toDateString(), $periods)] += $po->tax;
        }
        foreach ($returns as $ret) {
            $paid[$this->periodOf($ret->date->toDateString(), $periods)] -= $ret->tax;
        }
        foreach ($expenses as $e) {
            $paid[$this->periodOf($e->date->toDateString(), $periods)] += $e->gst_amount;
        }

        $g1 = $sales->sum('total');
        $a1 = $sales->sum('gst');
        $g11 = $purchases->sum('total') - $returns->sum('credit') + $expenses->sum('amount');
        $b1 = $purchases->sum('tax') - $returns->sum('tax') + $expenses->sum('gst_amount');

        return [
            'kpis' => [
                $this->kpi('GST collected', $a1, 'money'),
                $this->kpi('GST credits', $b1, 'money'),
                $this->kpi('Net GST payable', $a1 - $b1, 'money', false),
                $this->kpi('Period', date('d/m/Y', strtotime($from)).' – '.date('d/m/Y', strtotime($to)), 'text'),
            ],
            'chart' => ['type' => 'time', 'labels' => array_column($periods, 'label'), 'series' => [
                ['name' => 'GST collected', 'values' => array_map(fn ($v) => $this->r2($v), $collected)],
                ['name' => 'GST paid', 'values' => array_map(fn ($v) => $this->r2($v), $paid)],
            ]],
            'table' => [
                'title' => 'BAS summary',
                'cols' => [$this->col('Label', 'text', '0.5fr'), $this->col('Description', 'text', '2fr'), $this->col('Amount', 'money')],
                'rows' => [
                    ['c' => ['G1', 'Total sales (with GST)', $g1]],
                    ['c' => ['1A', 'GST on sales', $a1], 'bold' => 1],
                    ['c' => ['G11', 'Purchases and expenses (with GST)', $g11]],
                    ['c' => ['1B', 'GST on purchases and expenses', -$b1], 'bold' => 1],
                    ['c' => ['', $a1 - $b1 >= 0 ? 'Net GST payable' : 'Net GST refund', $a1 - $b1], 'bold' => 2],
                ],
                'no_totals' => true,
                'no_sort' => true,
            ],
        ];
    }

    // ---------------------------------------------------------------------

    /** Expenses dated in the period (deleted ones are left out). */
    protected function expensesIn(string $from, string $to, ?int $branchId)
    {
        return Expense::with('category:id,name')->whereBetween('date', [$from, $to])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))->get();
    }

    /** Cost of the goods in each sale: quantity x cost price now. [sale_id => cost] */
    protected function costOfGoods(array $saleIds): array
    {
        $items = SaleItem::whereIn('sale_id', $saleIds)->get();
        $productCost = Product::whereIn('id', $items->pluck('product_id'))->pluck('cost', 'id');
        $variantCost = ProductVariant::whereIn('id', $items->pluck('variant_id')->filter())->pluck('cost', 'id');

        $bySale = [];
        foreach ($items as $item) {
            $unitCost = $item->variant_id ? ($variantCost[$item->variant_id] ?? 0) : ($productCost[$item->product_id] ?? 0);
            $bySale[$item->sale_id] = ($bySale[$item->sale_id] ?? 0) + $item->qty * (float) $unitCost;
        }

        return $bySale;
    }
}
