<?php

namespace App\Http\Controllers\Api\Reports;

use App\Models\Expense;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\PurchasePayment;
use App\Models\SaleItem;
use App\Models\SalePayment;
use Illuminate\Http\Request;

/**
 * Sales reports: Sales, Customers, Product sales, Payments.
 * Only real sales count (see ReportController::COUNTED).
 */
class SalesReportController extends ReportController
{
    /**
     * Sales
     *
     * Invoices, discounts, net total, GST, paid and due, per day (or week for long ranges).
     * Filters: from, to, branch_id, contact_id (one customer).
     */
    public function sales(Request $request)
    {
        [$from, $to, $branchId] = $this->filters($request);
        $sales = $this->salesIn($from, $to, $branchId, $request->input('contact_id'));
        $periods = $this->periods($from, $to);

        $rows = [];
        foreach ($periods as $p) {
            $rows[] = ['c' => [$p['label'], 0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0]];
        }
        foreach ($sales as $sale) {
            $i = $this->periodOf($this->shopDay($sale->sold_at), $periods);
            $rows[$i]['c'][1] += 1;
            $rows[$i]['c'][2] += $sale->subtotal;
            $rows[$i]['c'][3] -= $sale->group_discount + $sale->discount + $sale->coin_discount;
            $rows[$i]['c'][4] += $sale->total;
            $rows[$i]['c'][5] += $sale->gst;
            $rows[$i]['c'][6] += $sale->paid;
            $rows[$i]['c'][7] += $sale->due;
        }

        $net = $sales->sum('total');
        $count = $sales->count();

        return [
            'kpis' => [
                $this->kpi('Net sales', $net, 'money'),
                $this->kpi('Invoices', $count, 'int'),
                $this->kpi('Average sale', $count ? $net / $count : 0, 'money'),
                $this->kpi('Outstanding', $sales->sum('due'), 'money', false),
            ],
            'chart' => ['type' => 'time', 'labels' => array_column($periods, 'label'), 'series' => [['name' => 'Net sales', 'values' => array_map(fn ($r) => $this->r2($r['c'][4]), $rows)]]],
            'table' => [
                'title' => 'By period',
                'cols' => [
                    $this->col('Period', 'text', '0.9fr'), $this->col('Invoices', 'int', '0.7fr', true), $this->col('Items', 'money', '1fr', true),
                    $this->col('Discounts', 'money', '1fr', true), $this->col('Net', 'money', '1fr', true), $this->col('GST', 'money', '0.8fr', true),
                    $this->col('Paid', 'money', '1fr', true), $this->col('Due', 'money', '0.8fr', true),
                ],
                'rows' => $rows,
            ],
        ];
    }

    /**
     * Customers
     *
     * Sales, paid and due per customer in the period, plus what each customer owes now. Walk-in sales are one line.
     */
    public function customers(Request $request)
    {
        [$from, $to, $branchId] = $this->filters($request);
        $sales = $this->salesIn($from, $to, $branchId, $request->input('contact_id'))->load('contact.customerGroup');

        $byCustomer = [];
        foreach ($sales as $sale) {
            $key = $sale->contact_id ?: 0;
            if (! isset($byCustomer[$key])) {
                $type = $sale->contact ? ($sale->contact->customerGroup ? $sale->contact->customerGroup->name : 'Account') : 'Walk-in';
                $byCustomer[$key] = ['name' => $sale->contact ? $sale->contact->name : 'Walk-in customers', 'type' => $type, 'n' => 0, 'sales' => 0.0, 'paid' => 0.0, 'due' => 0.0, 'last' => '', 'owes' => $sale->contact ? (float) $sale->contact->balance : null];
            }
            $c = &$byCustomer[$key];
            $c['n']++;
            $c['sales'] += $sale->total;
            $c['paid'] += $sale->paid;
            $c['due'] += $sale->due;
            $day = $this->shopDay($sale->sold_at);
            if ($day > $c['last']) {
                $c['last'] = $day;
            }
            unset($c);
        }

        $rows = [];
        foreach ($byCustomer as $c) {
            $rows[] = ['c' => [$c['name'], $c['type'], $c['n'], $c['sales'], $c['paid'], $c['due'], $c['owes'], $c['last'] ? date('d/m/Y', strtotime($c['last'])) : '']];
        }
        usort($rows, fn ($a, $b) => $b['c'][3] <=> $a['c'][3]);

        $total = array_sum(array_map(fn ($r) => $r['c'][3], $rows));
        $walkIn = isset($byCustomer[0]) ? $byCustomer[0]['sales'] : 0;

        return [
            'kpis' => [
                $this->kpi('Customers with sales', count(array_filter(array_keys($byCustomer))), 'int'),
                $this->kpi('Sales', $total, 'money'),
                $this->kpi('Due from these sales', array_sum(array_map(fn ($r) => $r['c'][5], $rows)), 'money', false),
                $this->kpi('Walk-in share', $total ? $walkIn / $total * 100 : 0, 'pct'),
            ],
            'chart' => ['type' => 'rank', 'title' => 'Sales by customer', 'items' => array_map(fn ($r) => [$r['c'][0], $this->r2($r['c'][3])], $rows)],
            'table' => [
                'title' => 'Customers',
                'cols' => [
                    $this->col('Customer', 'text', '1.8fr'), $this->col('Group', 'text', '0.9fr'), $this->col('Invoices', 'int', '0.7fr', true),
                    $this->col('Sales', 'money', '1fr', true), $this->col('Paid', 'money', '1fr', true), $this->col('Due', 'money', '0.9fr', true),
                    $this->col('Owes now', 'money', '0.9fr'), $this->col('Last sale', 'text', '0.9fr'),
                ],
                'rows' => $rows,
            ],
        ];
    }

    /**
     * Product sales
     *
     * Quantity, revenue, cost and margin per product. Cost is the product's (or variant's) cost price now.
     * Filters: from, to, branch_id, category_id (a top category and its sub-categories).
     */
    public function products(Request $request)
    {
        [$from, $to, $branchId] = $this->filters($request);
        $saleIds = $this->salesIn($from, $to, $branchId, null)->pluck('id');
        $categoryIds = $this->categoryIds($request->integer('category_id') ?: null);

        $items = SaleItem::whereIn('sale_id', $saleIds)->get();
        $products = Product::with('category:id,name,parent_id', 'category.parent:id,name')->whereIn('id', $items->pluck('product_id'))->get()->keyBy('id');
        $variants = ProductVariant::whereIn('id', $items->pluck('variant_id')->filter())->get()->keyBy('id');

        $byProduct = [];
        foreach ($items as $item) {
            $product = $products[$item->product_id] ?? null;
            if ($categoryIds && (! $product || ! in_array($product->category_id, $categoryIds))) {
                continue;
            }
            $key = $item->product_id.'-'.($item->variant_id ?: 0);
            if (! isset($byProduct[$key])) {
                $variant = $item->variant_id ? ($variants[$item->variant_id] ?? null) : null;
                $category = $product && $product->category ? ($product->category->parent ? $product->category->parent->name : $product->category->name) : '';
                $byProduct[$key] = ['name' => $item->name, 'cat' => $category, 'qty' => 0.0, 'unit' => $item->unit, 'rev' => 0.0, 'unitCost' => $variant ? (float) $variant->cost : ($product ? (float) $product->cost : 0)];
            }
            $byProduct[$key]['qty'] += $item->qty;
            $byProduct[$key]['rev'] += $item->total;
        }

        $rows = [];
        foreach ($byProduct as $p) {
            $cost = $p['qty'] * $p['unitCost'];
            $rows[] = ['c' => [$p['name'], $p['cat'], $p['qty'], $p['unit'], $p['rev'], $cost, $p['rev'] ? ($p['rev'] - $cost) / $p['rev'] * 100 : 0]];
        }
        usort($rows, fn ($a, $b) => $b['c'][4] <=> $a['c'][4]);

        $rev = array_sum(array_map(fn ($r) => $r['c'][4], $rows));
        $cost = array_sum(array_map(fn ($r) => $r['c'][5], $rows));

        return [
            'kpis' => [
                $this->kpi('Products sold', count($rows), 'int'),
                $this->kpi('Revenue', $rev, 'money'),
                $this->kpi('Gross margin', $rev ? ($rev - $cost) / $rev * 100 : 0, 'pct'),
                $this->kpi('Top product', $rows ? $rows[0]['c'][0] : '—', 'text'),
            ],
            'chart' => ['type' => 'rank', 'title' => 'Revenue by product', 'items' => array_map(fn ($r) => [$r['c'][0], $this->r2($r['c'][4])], $rows)],
            'table' => [
                'title' => 'Products',
                'cols' => [
                    $this->col('Product', 'text', '1.7fr'), $this->col('Category', 'text', '0.8fr'), $this->col('Qty sold', 'qty', '0.8fr'),
                    $this->col('Unit', 'text', '0.4fr'), $this->col('Revenue', 'money', '1fr', true), $this->col('Cost', 'money', '1fr', true),
                    $this->col('Margin', 'pct', '0.7fr'),
                ],
                'rows' => $rows,
            ],
        ];
    }

    /**
     * Payments
     *
     * Money in (sale payments) and money out (supplier payments and paid expenses), per payment account.
     */
    public function payments(Request $request)
    {
        [$from, $to, $branchId] = $this->filters($request);
        [$start, $end] = $this->utcRange($from, $to);

        $accounts = [];
        $add = function (string $name, float $in, float $out) use (&$accounts) {
            if (! isset($accounts[$name])) {
                $accounts[$name] = ['n' => 0, 'in' => 0.0, 'out' => 0.0];
            }
            $accounts[$name]['n']++;
            $accounts[$name]['in'] += $in;
            $accounts[$name]['out'] += $out;
        };

        // In: payments taken on sales (cash and card), on sales that were not cancelled.
        $salePayments = SalePayment::whereBetween('created_at', [$start, $end])
            ->whereHas('sale', function ($q) use ($branchId) {
                $q->whereIn('status', self::COUNTED);
                if ($branchId) {
                    $q->where('branch_id', $branchId);
                }
            })->get();
        foreach ($salePayments as $p) {
            $add($p->account_name ?: ucfirst($p->method), (float) $p->amount, 0);
        }

        // Out: paying suppliers.
        $purchasePayments = PurchasePayment::whereBetween('date', [$from, $to])
            ->whereHas('purchase', function ($q) use ($branchId) {
                if ($branchId) {
                    $q->where('branch_id', $branchId);
                }
            })->get();
        foreach ($purchasePayments as $p) {
            $add($p->account_name ?: 'Supplier payments', 0, (float) $p->amount);
        }

        // Out: expenses paid.
        $expenses = Expense::with('paymentAccount:id,name')->whereNotNull('paid_date')->whereBetween('paid_date', [$from, $to])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))->get();
        foreach ($expenses as $e) {
            $add($e->paymentAccount ? $e->paymentAccount->name : 'Expenses', 0, (float) $e->amount);
        }

        $rows = [];
        foreach ($accounts as $name => $a) {
            $rows[] = ['c' => [$name, $a['n'], $a['in'], -$a['out'], $a['in'] - $a['out']]];
        }
        usort($rows, fn ($a, $b) => $b['c'][2] <=> $a['c'][2]);

        $in = $salePayments->sum('amount');
        $out = $purchasePayments->sum('amount') + $expenses->sum('amount');
        $card = $salePayments->where('method', 'card')->sum('amount');

        return [
            'kpis' => [
                $this->kpi('Received', $in, 'money'),
                $this->kpi('Paid out', $out, 'money', false),
                $this->kpi('Net cash flow', $in - $out, 'money'),
                $this->kpi('Card share', $in ? $card / $in * 100 : 0, 'pct'),
            ],
            'chart' => ['type' => 'rank', 'title' => 'Received by account', 'items' => array_values(array_map(fn ($r) => [$r['c'][0], $this->r2($r['c'][2])], array_filter($rows, fn ($r) => $r['c'][2] > 0)))],
            'table' => [
                'title' => 'By payment account',
                'cols' => [
                    $this->col('Account', 'text', '1.6fr'), $this->col('Payments', 'int', '1fr', true), $this->col('Received', 'money', '1fr', true),
                    $this->col('Paid out', 'money', '1fr', true), $this->col('Net', 'money', '1fr', true),
                ],
                'rows' => $rows,
            ],
        ];
    }
}
