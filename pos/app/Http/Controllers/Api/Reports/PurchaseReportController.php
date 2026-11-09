<?php

namespace App\Http\Controllers\Api\Reports;

use App\Models\Purchase;
use App\Models\PurchaseReturn;
use Illuminate\Http\Request;

/** Purchasing reports: Purchases and Suppliers. Purchases count by their date; returns by the return date. */
class PurchaseReportController extends ReportController
{
    /**
     * Purchases
     *
     * Purchase orders per day (or week): ordered, received, returned, net, paid and due.
     * Filters: from, to, branch_id, contact_id (one supplier).
     */
    public function purchases(Request $request)
    {
        [$from, $to, $branchId] = $this->filters($request);
        $supplierId = $request->input('contact_id');
        $purchases = $this->purchasesIn($from, $to, $branchId, $supplierId);
        $returns = $this->returnsIn($from, $to, $branchId, $supplierId);
        $periods = $this->periods($from, $to);

        $rows = [];
        foreach ($periods as $p) {
            $rows[] = ['c' => [$p['label'], 0, 0.0, 0.0, 0.0, 0.0, 0.0]];
        }
        foreach ($purchases as $po) {
            $i = $this->periodOf($po->date->toDateString(), $periods);
            $rows[$i]['c'][1] += 1;
            $rows[$i]['c'][2] += $po->total;
            if ($po->status === 'received') {
                $rows[$i]['c'][3] += $po->total;
            }
            $rows[$i]['c'][5] += $po->paid;
            $rows[$i]['c'][6] += $po->due;
        }
        foreach ($returns as $ret) {
            $i = $this->periodOf($ret->date->toDateString(), $periods);
            $rows[$i]['c'][4] -= $ret->credit;
        }
        // Net = received less returns.
        foreach ($rows as $k => $r) {
            $rows[$k]['c'] = [$r['c'][0], $r['c'][1], $r['c'][2], $r['c'][3], $r['c'][4], $r['c'][3] + $r['c'][4], $r['c'][5], $r['c'][6]];
        }

        return [
            'kpis' => [
                $this->kpi('Purchases', $purchases->sum('total'), 'money', false),
                $this->kpi('Purchase orders', $purchases->count(), 'int'),
                $this->kpi('Paid', $purchases->sum('paid'), 'money'),
                $this->kpi('Owed to suppliers', $purchases->sum('due'), 'money', false),
            ],
            'chart' => ['type' => 'time', 'labels' => array_column($periods, 'label'), 'series' => [['name' => 'Purchases', 'values' => array_map(fn ($r) => $this->r2($r['c'][2]), $rows)]]],
            'table' => [
                'title' => 'By period',
                'cols' => [
                    $this->col('Period', 'text', '0.9fr'), $this->col('POs', 'int', '0.6fr', true), $this->col('Ordered', 'money', '1fr', true),
                    $this->col('Received', 'money', '1fr', true), $this->col('Returns', 'money', '1fr', true), $this->col('Net', 'money', '1fr', true),
                    $this->col('Paid', 'money', '1fr', true), $this->col('Due', 'money', '0.9fr', true),
                ],
                'rows' => $rows,
            ],
        ];
    }

    /**
     * Suppliers
     *
     * Spend, paid, owed and returns per supplier in the period, and the last order date.
     */
    public function suppliers(Request $request)
    {
        [$from, $to, $branchId] = $this->filters($request);
        $supplierId = $request->input('contact_id');
        $purchases = $this->purchasesIn($from, $to, $branchId, $supplierId)->load('supplier:id,name');
        $returns = $this->returnsIn($from, $to, $branchId, $supplierId);

        $bySupplier = [];
        foreach ($purchases as $po) {
            $key = $po->supplier_id;
            if (! isset($bySupplier[$key])) {
                $bySupplier[$key] = ['name' => $po->supplier ? $po->supplier->name : 'Unknown', 'n' => 0, 'spend' => 0.0, 'paid' => 0.0, 'due' => 0.0, 'ret' => 0.0, 'last' => ''];
            }
            $s = &$bySupplier[$key];
            $s['n']++;
            $s['spend'] += $po->total;
            $s['paid'] += $po->paid;
            $s['due'] += $po->due;
            if ($po->date->toDateString() > $s['last']) {
                $s['last'] = $po->date->toDateString();
            }
            unset($s);
        }
        foreach ($returns as $ret) {
            if (isset($bySupplier[$ret->supplier_id])) {
                $bySupplier[$ret->supplier_id]['ret'] += $ret->credit;
            }
        }

        $rows = [];
        foreach ($bySupplier as $s) {
            $rows[] = ['c' => [$s['name'], $s['n'], $s['spend'], $s['paid'], $s['due'], -$s['ret'], $s['last'] ? date('d/m/Y', strtotime($s['last'])) : '']];
        }
        usort($rows, fn ($a, $b) => $b['c'][2] <=> $a['c'][2]);

        return [
            'kpis' => [
                $this->kpi('Suppliers used', count($rows), 'int'),
                $this->kpi('Spend', $purchases->sum('total'), 'money', false),
                $this->kpi('Owed', $purchases->sum('due'), 'money', false),
                $this->kpi('Largest supplier', $rows ? $rows[0]['c'][0] : '—', 'text'),
            ],
            'chart' => ['type' => 'rank', 'title' => 'Spend by supplier', 'items' => array_map(fn ($r) => [$r['c'][0], $this->r2($r['c'][2])], $rows)],
            'table' => [
                'title' => 'Suppliers',
                'cols' => [
                    $this->col('Supplier', 'text', '1.8fr'), $this->col('POs', 'int', '0.6fr', true), $this->col('Spend', 'money', '1fr', true),
                    $this->col('Paid', 'money', '1fr', true), $this->col('Owed', 'money', '1fr', true), $this->col('Returns', 'money', '1fr', true),
                    $this->col('Last order', 'text', '0.9fr'),
                ],
                'rows' => $rows,
            ],
        ];
    }

    // ---------------------------------------------------------------------

    protected function purchasesIn(string $from, string $to, ?int $branchId, $supplierId)
    {
        return Purchase::whereBetween('date', [$from, $to])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($supplierId, fn ($q) => $q->where('supplier_id', $supplierId))
            ->orderBy('date')->get();
    }

    protected function returnsIn(string $from, string $to, ?int $branchId, $supplierId)
    {
        return PurchaseReturn::whereBetween('date', [$from, $to])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($supplierId, fn ($q) => $q->where('supplier_id', $supplierId))
            ->get();
    }
}
