<?php

namespace App\Http\Controllers\Api\Reports;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Contact;
use App\Models\Sale;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Reports screen. This controller gives the lists for the filters (GET /api/reports) and has the
 * helpers every report uses. Each report is one method in SalesReportController, PurchaseReportController,
 * StockReportController or FinanceReportController, and all answer in the same shape:
 *
 *   kpis   [{label, value, type, good}]        type: money, int, pct or text; good: true when higher is better
 *   chart  {type: 'time', labels, series: [{name, values}]}  or  {type: 'rank', title, items: [[name, value]]}
 *   table  {title, cols: [{h, t, w, sum}], rows: [{c: [...], bold}]}
 *
 * Branches: a user with a branch only sees that branch, unless they have `reports.all-locations`.
 * Dates are the shop's own days (config app.shop_timezone).
 */
#[Group('Reports', 'Sales, purchasing, stock and finance reports, per branch.', weight: 70)]
class ReportController extends Controller
{
    // Sales that count in reports. Drafts, quotations and cancelled sales are left out.
    public const COUNTED = ['paid', 'partial', 'due'];

    /**
     * Report filters
     *
     * The lists for the filters: branches (empty when the user can only see their own), customers,
     * suppliers and top categories, and today's date in the shop's time zone.
     */
    public function index(Request $request)
    {
        $own = $this->ownBranchOnly($request);

        return [
            'today' => now(config('app.shop_timezone'))->toDateString(),
            'branch' => $own ? Branch::find($request->user()->branch_id, ['id', 'name']) : null,
            'branches' => $own ? [] : Branch::where('active', true)->orderBy('name')->get(['id', 'name']),
            'customers' => Contact::whereIn('type', ['Customer', 'Both'])->orderBy('name')->get(['id', 'name']),
            'suppliers' => Contact::whereIn('type', ['Supplier', 'Both'])->orderBy('name')->get(['id', 'name']),
            'categories' => Category::whereNull('parent_id')->orderBy('name')->get(['id', 'name']),
        ];
    }

    // ---- helpers for the reports ------------------------------------------------

    /** True when this user may only see their own branch. */
    protected function ownBranchOnly(Request $request): bool
    {
        $user = $request->user();

        return $user->branch_id && ! $user->hasPermission('reports.all-locations');
    }

    /**
     * The filters every report takes: from and to (dates, default this month) and branch_id
     * (null = all branches). A user who can only see their own branch always gets it.
     */
    protected function filters(Request $request): array
    {
        $request->validate([
            'from' => 'nullable|date_format:Y-m-d',
            'to' => 'nullable|date_format:Y-m-d|after_or_equal:from',
            'branch_id' => 'nullable|integer|exists:branches,id',
        ]);
        $today = now(config('app.shop_timezone'));
        $from = $request->input('from', $today->copy()->startOfMonth()->toDateString());
        $to = $request->input('to', $today->toDateString());

        $branchId = $request->filled('branch_id') ? (int) $request->input('branch_id') : null;
        if ($this->ownBranchOnly($request)) {
            $branchId = (int) $request->user()->branch_id;
        }

        return [$from, $to, $branchId];
    }

    /** The start and end of the shop days $from..$to, in UTC, for date-time columns (sold_at, created_at). */
    protected function utcRange(string $from, string $to): array
    {
        $tz = config('app.shop_timezone');

        return [
            Carbon::parse($from, $tz)->startOfDay()->utc(),
            Carbon::parse($to, $tz)->endOfDay()->utc(),
        ];
    }

    /** The shop day of a date-time, e.g. a sale's sold_at -> "2026-11-05". */
    protected function shopDay($dateTime): string
    {
        return Carbon::parse($dateTime)->setTimezone(config('app.shop_timezone'))->toDateString();
    }

    /** The chart periods: one per day for up to 31 days, else one per week. [['label', 'from', 'to'], ...] */
    protected function periods(string $from, string $to): array
    {
        $start = Carbon::parse($from);
        $end = Carbon::parse($to);
        $days = $start->diffInDays($end) + 1;
        $step = $days <= 31 ? 1 : 7;

        $periods = [];
        for ($d = $start->copy(); $d->lte($end); $d->addDays($step)) {
            $last = $d->copy()->addDays($step - 1);
            if ($last->gt($end)) {
                $last = $end->copy();
            }
            $periods[] = ['label' => $d->format('d/m'), 'from' => $d->toDateString(), 'to' => $last->toDateString()];
        }

        return $periods;
    }

    /** Which period a day falls in (index into periods()). */
    protected function periodOf(string $day, array $periods): int
    {
        foreach ($periods as $i => $p) {
            if ($day >= $p['from'] && $day <= $p['to']) {
                return $i;
            }
        }

        return count($periods) - 1;
    }

    protected function kpi(string $label, $value, string $type, bool $good = true): array
    {
        return ['label' => $label, 'value' => is_float($value) ? round($value, 2) : $value, 'type' => $type, 'good' => $good];
    }

    /** A table column: heading, type (text, money, int, qty, pct, badge), width, and whether to total it. */
    protected function col(string $heading, string $type, string $width = '1fr', bool $sum = false): array
    {
        return ['h' => $heading, 't' => $type, 'w' => $width, 'sum' => $sum];
    }

    /** All category ids under a top category (itself and its sub-categories), for the category filter. */
    protected function categoryIds(?int $categoryId): ?array
    {
        if (! $categoryId) {
            return null;
        }
        $ids = [$categoryId];
        $children = Category::where('parent_id', $categoryId)->pluck('id')->all();
        while ($children) {
            $ids = array_merge($ids, $children);
            $children = Category::whereIn('parent_id', $children)->pluck('id')->all();
        }

        return $ids;
    }

    /** The sales that count (paid, partial, due) sold in the shop days $from..$to. */
    protected function salesIn(string $from, string $to, ?int $branchId, $contactId)
    {
        [$start, $end] = $this->utcRange($from, $to);

        return Sale::whereIn('status', self::COUNTED)
            ->whereBetween('sold_at', [$start, $end])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($contactId, fn ($q) => $q->where('contact_id', $contactId))
            ->orderBy('sold_at')
            ->get();
    }

    protected function r2(float $n): float
    {
        return round($n, 2);
    }
}
