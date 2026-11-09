<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\Expense;
use App\Models\KitchenTicket;
use App\Models\PaymentAccount;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Purchase;
use App\Models\Sale;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * The dashboard (Home). What a user sees depends on who they are:
 *   all    - no branch, or `home.all-locations`: every branch (or one they pick) and every salesperson (or one)
 *   branch - a branch user with `reports.view` (e.g. a manager): their branch, every salesperson (or one)
 *   own    - a branch user without `reports.view` (e.g. a cashier): only the sales they made themselves
 * Each block is only sent when the user has the permission for it (accounts, stock, purchases ...).
 * Days are the shop's own days (config app.shop_timezone).
 */
#[Group('Dashboard', 'The home page numbers, per branch and per salesperson.', weight: 5)]
class DashboardController extends Controller
{
    // Sales that count. Drafts, quotations and cancelled sales are left out.
    private const COUNTED = ['paid', 'partial', 'due'];

    /**
     * Dashboard
     *
     * Filters: `branch_id` (only for "all"), `user_id` (one salesperson, not for "own"), `days` (30, 90 or 365 for the chart).
     */
    public function index(Request $request)
    {
        $request->validate([
            'branch_id' => 'nullable|integer|exists:branches,id',
            'user_id' => 'nullable|string|max:40',
            'days' => 'nullable|integer|in:30,90,365',
        ]);
        $user = $request->user();
        $tz = config('app.shop_timezone');

        // Who sees what.
        if (! $user->branch_id || $user->hasPermission('home.all-locations')) {
            $scope = 'all';
            $branchId = $request->filled('branch_id') ? (int) $request->input('branch_id') : null;
        } else {
            $scope = $user->hasPermission('reports.view') ? 'branch' : 'own';
            $branchId = (int) $user->branch_id;
        }
        $staffId = $scope === 'own' ? (string) $user->id : ($request->input('user_id') ?: null);
        $days = (int) $request->input('days', 30);

        $today = now($tz)->startOfDay();
        $todayStart = $today->copy()->utc();
        $todayEnd = $today->copy()->endOfDay()->utc();
        // The same weekday last week, to compare today with.
        $lastWeekStart = $today->copy()->subWeek()->utc();
        $lastWeekEnd = $today->copy()->subWeek()->endOfDay()->utc();

        $todaySales = $this->sales($branchId, $staffId)->whereBetween('sold_at', [$todayStart, $todayEnd])->get();
        $lastWeekTotal = (float) $this->sales($branchId, $staffId)->whereBetween('sold_at', [$lastWeekStart, $lastWeekEnd])->sum('total');
        $todayTotal = (float) $todaySales->sum('total');
        $outstanding = (float) $this->sales($branchId, $staffId)->where('due', '>', 0)->sum('due');

        $result = [
            'scope' => $scope,
            'today' => $today->toDateString(),
            'branch' => $branchId ? Branch::find($branchId, ['id', 'name']) : null,
            'branches' => $scope === 'all' ? Branch::where('active', true)->orderBy('name')->get(['id', 'name']) : [],
            'staff' => $scope === 'own' ? [] : $this->staffList($branchId),
            'user_id' => $staffId,
            'days' => $days,
            'kpis' => [
                ['label' => 'Sales today', 'value' => round($todayTotal, 2), 'type' => 'money',
                    'note' => $lastWeekTotal > 0 ? round(($todayTotal - $lastWeekTotal) / $lastWeekTotal * 100, 1) : null],
                ['label' => 'Invoices today', 'value' => $todaySales->count(), 'type' => 'int', 'note' => null],
                ['label' => 'Average sale today', 'value' => $todaySales->count() ? round($todayTotal / $todaySales->count(), 2) : 0, 'type' => 'money', 'note' => null],
                ['label' => 'Outstanding due', 'value' => round($outstanding, 2), 'type' => 'money',
                    'note' => $this->sales($branchId, $staffId)->where('due', '>', 0)->count()],
            ],
            'chart' => $this->chart($branchId, $staffId, $days, $tz),
            'recent' => $this->sales($branchId, $staffId)->with('branch:id,name')->orderByDesc('sold_at')->limit(8)
                ->get(['id', 'ref', 'sold_at', 'branch_id', 'customer_name', 'method', 'total', 'status', 'created_by']),
            'staff_sales' => $scope === 'own' ? [] : $this->staffSales($branchId, $days, $tz),
            'alerts' => $this->alerts($request, $branchId, $staffId, $tz),
            'accounts' => null,
            'low_stock' => null,
        ];

        if ($user->hasPermission('accounts.view')) {
            $accounts = PaymentAccount::where('active', true)
                ->when($branchId, fn ($q) => $q->where(fn ($q2) => $q2->where('branch_id', $branchId)->orWhereNull('branch_id')))
                ->orderBy('created_at')->get(['id', 'name', 'kind', 'bal', 'branch_id']);
            $result['accounts'] = $accounts->map(fn ($a) => ['name' => $a->name, 'kind' => $a->kind, 'bal' => $a->bal])->all();
        }
        if ($user->hasPermission('products.view')) {
            $result['low_stock'] = $this->lowStock($branchId);
        }

        return $result;
    }

    // ---------------------------------------------------------------------

    /** The sales that count, at a branch (null = all) and by one salesperson (null = all). */
    private function sales(?int $branchId, ?string $staffId)
    {
        return Sale::whereIn('status', self::COUNTED)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($staffId, fn ($q) => $q->where('user_id', $staffId));
    }

    /** Net sales per shop day (30 or 90 days) or per month (a year). */
    private function chart(?int $branchId, ?string $staffId, int $days, string $tz): array
    {
        $end = now($tz)->endOfDay();
        $start = now($tz)->startOfDay()->subDays($days - 1);
        $sales = $this->sales($branchId, $staffId)->whereBetween('sold_at', [$start->copy()->utc(), $end->copy()->utc()])->get(['sold_at', 'total']);

        $byMonth = $days > 90;
        $labels = [];
        $values = [];
        if ($byMonth) {
            for ($m = $start->copy()->startOfMonth(); $m->lte($end); $m->addMonth()) {
                $labels[$m->format('Y-m')] = $m->format('M');
                $values[$m->format('Y-m')] = 0.0;
            }
        } else {
            for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
                $labels[$d->toDateString()] = $d->format('d M');
                $values[$d->toDateString()] = 0.0;
            }
        }
        foreach ($sales as $sale) {
            $local = Carbon::parse($sale->sold_at)->setTimezone($tz);
            $key = $byMonth ? $local->format('Y-m') : $local->toDateString();
            if (isset($values[$key])) {
                $values[$key] += $sale->total;
            }
        }

        $total = array_sum($values);

        return [
            'labels' => array_values($labels),
            'values' => array_map(fn ($v) => round($v, 2), array_values($values)),
            'total' => round($total, 2),
            'average' => round($total / $days, 2),
            'by' => $byMonth ? 'month' : 'day',
        ];
    }

    /** The salespeople who have sales here, for the "Salesperson" filter. */
    private function staffList(?int $branchId): array
    {
        return Sale::whereNotNull('user_id')
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->selectRaw('user_id as id, MAX(created_by) as name')->groupBy('user_id')->orderBy('name')->get()->toArray();
    }

    /** Sales per salesperson in the chart's period. */
    private function staffSales(?int $branchId, int $days, string $tz): array
    {
        $start = now($tz)->startOfDay()->subDays($days - 1)->utc();

        return $this->sales($branchId, null)->where('sold_at', '>=', $start)
            ->selectRaw('user_id, MAX(created_by) as name, COUNT(*) as invoices, SUM(total) as total')
            ->groupBy('user_id')->orderByDesc('total')->get()
            ->map(fn ($r) => ['user_id' => $r->user_id, 'name' => $r->name, 'invoices' => (int) $r->invoices, 'total' => round((float) $r->total, 2)])
            ->all();
    }

    /** "Needs attention": only the lines the user may open. Each has a link to the page. */
    private function alerts(Request $request, ?int $branchId, ?string $staffId, string $tz): array
    {
        $user = $request->user();
        $list = [];

        if ($user->hasPermission('sell.view')) {
            $n = $this->sales($branchId, $staffId)->where('due', '>', 0)->count();
            $list[] = ['count' => $n, 'label' => 'Sales awaiting payment', 'link' => '/sell', 'tone' => $n ? 'error' : 'ok'];
        }
        if ($user->hasPermission('products.view')) {
            $n = count($this->lowStock($branchId));
            $list[] = ['count' => $n, 'label' => 'Products low or out of stock', 'link' => '/products', 'tone' => $n ? 'warn' : 'ok'];
        }
        if ($user->hasPermission('purchases.view')) {
            $n = Purchase::where('status', '!=', 'received')->when($branchId, fn ($q) => $q->where('branch_id', $branchId))->count();
            $list[] = ['count' => $n, 'label' => 'Purchase orders not yet received', 'link' => '/purchases', 'tone' => $n ? 'info' : 'ok'];
            $n = Purchase::where('due', '>', 0)->when($branchId, fn ($q) => $q->where('branch_id', $branchId))->count();
            $list[] = ['count' => $n, 'label' => 'Supplier bills to pay', 'link' => '/purchases', 'tone' => $n ? 'warn' : 'ok'];
        }
        if ($user->hasPermission('expenses.view')) {
            $n = Expense::whereNull('paid_date')->whereNotNull('due_date')->where('due_date', '<', now($tz)->toDateString())
                ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))->count();
            $list[] = ['count' => $n, 'label' => 'Expenses overdue', 'link' => '/expenses', 'tone' => $n ? 'error' : 'ok'];
        }
        if ($user->hasPermission('bookings.view')) {
            $n = Booking::where('date', now($tz)->toDateString())->whereIn('status', Booking::HOLDS_TABLE)
                ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))->count();
            $list[] = ['count' => $n, 'label' => 'Bookings today', 'link' => '/bookings', 'tone' => $n ? 'info' : 'ok'];
        }
        if ($user->hasPermission('kitchen.view')) {
            $n = KitchenTicket::whereIn('status', ['new', 'prep'])->when($branchId, fn ($q) => $q->where('branch_id', $branchId))->count();
            $list[] = ['count' => $n, 'label' => 'Kitchen orders cooking', 'link' => '/kitchen', 'tone' => $n ? 'info' : 'ok'];
        }

        return $list;
    }

    /** Products at or below their alert quantity (per branch, or added up for all branches). */
    private function lowStock(?int $branchId): array
    {
        $products = Product::with('unit:id,short_name')->where('manage_stock', true)->where('active', true)->where('type', 'single')->get();
        $stocks = ProductStock::whereIn('product_id', $products->pluck('id'))->whereNull('variant_id')
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))->get()->groupBy('product_id');
        $branchCount = $branchId ? 1 : max(1, Branch::where('active', true)->count());

        $rows = [];
        foreach ($products as $p) {
            if (! $branchId && ! isset($stocks[$p->id])) {
                continue;
            }
            if ($branchId && ! $p->branches()->where('branches.id', $branchId)->exists()) {
                continue; // not sold at this branch
            }
            $qty = isset($stocks[$p->id]) ? (float) $stocks[$p->id]->sum('qty') : 0;
            $alert = (float) $p->alert_qty * ($branchId ? 1 : $branchCount);
            if ($qty <= 0 || ($alert > 0 && $qty <= $alert)) {
                $rows[] = ['name' => $p->name, 'qty' => $qty, 'unit' => $p->unit ? $p->unit->short_name : '', 'alert' => $alert, 'out' => $qty <= 0];
            }
        }
        usort($rows, fn ($a, $b) => $a['qty'] <=> $b['qty']);

        return $rows;
    }
}
