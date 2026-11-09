<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\ProductVariant;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentItem;
use App\Models\StockMovement;
use App\Models\StockSetting;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Stock adjustments: stock added or removed at a branch, or corrected by a count.
 * Saving changes the stock straight away. A mistake is undone with a reversal, never by editing.
 * A user with a branch only sees and adjusts stock at that branch.
 */
#[Group('Stock Adjustments', 'Add or remove stock at a branch, or correct it with a count. Saving changes the stock straight away.', weight: 12)]
class StockAdjustmentController extends Controller
{
    /**
     * List adjustments
     *
     * 25 per page, newest first. Also returns `counts` for the tabs, `net` (value for the current filter),
     * `month` (this month's totals and top reasons) and `reasons` for the filter. Needs `adjust.view`.
     */
    #[QueryParameter('tab', 'dec, inc or count.', type: 'string')]
    #[QueryParameter('search', 'Reference or product name.', type: 'string')]
    #[QueryParameter('branch_id', 'Only this branch.', type: 'int')]
    #[QueryParameter('reason', 'Has a line with this reason.', type: 'string')]
    #[QueryParameter('from', 'From this date, e.g. 2026-09-01.', type: 'string', format: 'date')]
    #[QueryParameter('to', 'Up to this date, e.g. 2026-09-30.', type: 'string', format: 'date')]
    public function index(Request $request)
    {
        $query = $this->filtered($request);
        if (in_array($request->input('tab'), ['dec', 'inc', 'count'], true)) {
            $query->where('type', $request->input('tab'));
        }

        $net = (float) (clone $query)->sum(DB::raw('increase_value - decrease_value'));

        $page = $query->with('branch:id,name', 'items:id,stock_adjustment_id,name,reason')
            ->orderBy('date', 'desc')
            ->orderBy('id', 'desc')
            ->paginate(25);

        $counts = ['all' => $this->filtered($request)->count()];
        foreach (['dec', 'inc', 'count'] as $type) {
            $counts[$type] = $this->filtered($request)->where('type', $type)->count();
        }

        return [
            'data' => $page->items(),
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'total' => $page->total(),
            'counts' => $counts,
            'net' => $net,
            'month' => $this->monthTotals($request),
            'reasons' => StockAdjustment::REASONS,
        ];
    }

    /**
     * Form defaults
     *
     * The next reference, the branch the form starts with, the reasons and the approval limit.
     * Needs `adjust.create`.
     */
    public function formData(Request $request)
    {
        return [
            'next_ref' => $this->nextRef(),
            'branch_id' => $request->user()->branch_id ?? StockSetting::current()->default_branch_id,
            'reasons' => StockAdjustment::REASONS,
            'approval_limit' => StockAdjustment::APPROVAL_LIMIT,
        ];
    }

    /**
     * Products to adjust
     *
     * Products that manage stock and are sold at the branch, one row per product or variant,
     * with `on_hand`. Needs `adjust.create`.
     */
    #[QueryParameter('branch_id', 'The branch being adjusted.', required: true, type: 'int')]
    public function products(Request $request)
    {
        $request->validate(['branch_id' => 'required|exists:branches,id']);
        $branchId = (int) $request->input('branch_id');

        $products = Product::with('unit:id,short_name,allow_decimals', 'variants')
            ->where('active', true)
            ->where('manage_stock', true)
            ->whereHas('branches', function ($q) use ($branchId) {
                $q->where('branches.id', $branchId);
            })
            ->orderBy('name')
            ->get();

        $stocks = ProductStock::where('branch_id', $branchId)->get();

        $rows = [];
        foreach ($products as $product) {
            if ($product->type === 'variable') {
                foreach ($product->variants as $variant) {
                    $rows[] = $this->productRow($product, $variant, $stocks);
                }
            } else {
                $rows[] = $this->productRow($product, null, $stocks);
            }
        }

        return $rows;
    }

    /**
     * Post adjustment
     *
     * Changes the stock straight away. `qty` is the amount to remove (dec), the amount to add (inc)
     * or the quantity counted (count: lines that match the system are left out).
     * Removing more than the approval limit also needs `adjust.edit`. Needs `adjust.create`.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'date' => 'required|date',
            'branch_id' => 'required|exists:branches,id',
            'type' => 'required|in:dec,inc,count',
            'notes' => 'nullable|string|max:1000',
            'lines' => 'required|array|min:1',
            'lines.*.product_id' => 'required|exists:products,id',
            'lines.*.variant_id' => 'nullable|integer',
            'lines.*.qty' => 'required|numeric|min:0',
            'lines.*.reason' => 'nullable|string|max:120',
        ]);

        $user = $request->user();
        if (! $this->isAtBranch($request, $data['branch_id'])) {
            return $this->notYourBranch();
        }

        $result = DB::transaction(function () use ($data, $user) {
            $items = $this->buildItems($data);
            if (is_string($items)) {
                return $items;
            }

            $values = $this->values($items);
            if ($values['decrease_value'] > StockAdjustment::APPROVAL_LIMIT && ! $user->hasPermission('adjust.edit')) {
                return 'Removing more than '.StockAdjustment::APPROVAL_LIMIT.' of stock needs a manager. Ask someone who can edit adjustments to post this.';
            }

            return $this->saveAdjustment([
                'date' => $data['date'],
                'branch_id' => $data['branch_id'],
                'type' => $data['type'],
                'notes' => $data['notes'] ?? null,
                'approved_by' => $values['decrease_value'] > StockAdjustment::APPROVAL_LIMIT ? $user->name : null,
            ], $items, $user->name);
        });

        if (is_string($result)) {
            return response()->json(['message' => $result], 422);
        }

        return response()->json($this->details($result), 201);
    }

    /**
     * Show adjustment
     *
     * With the branch, the lines and any reversal. Needs `adjust.view`
     * (a user with a branch: only that branch).
     */
    public function show(Request $request, StockAdjustment $stockAdjustment)
    {
        if (! $this->isMine($request, $stockAdjustment, 'adjust')) {
            return $this->notMine();
        }
        if (! $this->isAtBranch($request, $stockAdjustment->branch_id)) {
            return $this->notYourBranch();
        }

        return $this->details($stockAdjustment);
    }

    /**
     * Reverse adjustment
     *
     * Posts a new adjustment with every line flipped, which puts the stock back. The original stays
     * in the history, marked as reversed. Needs `adjust.edit` (a user with a branch: only that branch).
     */
    public function reverse(Request $request, StockAdjustment $stockAdjustment)
    {
        if (! $this->isMine($request, $stockAdjustment, 'adjust')) {
            return $this->notMine();
        }
        if (! $this->isAtBranch($request, $stockAdjustment->branch_id)) {
            return $this->notYourBranch();
        }

        $userName = $request->user()->name;

        // Locking the row stops the same adjustment being reversed twice at the same moment.
        $result = DB::transaction(function () use ($stockAdjustment, $userName) {
            $original = StockAdjustment::lockForUpdate()->find($stockAdjustment->id);

            if ($original->reverses_id) {
                return $original->ref.' is itself a reversal, so it cannot be reversed.';
            }
            if ($original->reversedBy()->exists()) {
                return $original->ref.' has already been reversed.';
            }

            $items = [];
            foreach ($original->items()->with('product')->get() as $item) {
                if (! $item->product) {
                    return $item->name.' no longer exists, so this cannot be reversed.';
                }

                $onHand = $this->onHand($item->product_id, $item->variant_id, $original->branch_id);
                if ($onHand - $item->change_qty < 0 && ! $item->product->allow_negative_stock) {
                    return 'Only '.$onHand.' of '.$item->name.' left, so this cannot be reversed.';
                }

                $items[] = [
                    'product_id' => $item->product_id,
                    'variant_id' => $item->variant_id,
                    'name' => $item->name,
                    'sku' => $item->sku,
                    'unit' => $item->unit,
                    'before_qty' => $onHand,
                    'change_qty' => -$item->change_qty,
                    'cost' => $item->cost,
                    'reason' => 'Reversal of '.$original->ref,
                ];
            }

            $values = $this->values($items);

            return $this->saveAdjustment([
                'date' => now()->toDateString(),
                'branch_id' => $original->branch_id,
                'type' => $values['increase_value'] >= $values['decrease_value'] ? 'inc' : 'dec',
                'notes' => 'Reverses '.$original->ref.'.',
                'approved_by' => $values['decrease_value'] > StockAdjustment::APPROVAL_LIMIT ? $userName : null,
                'reverses_id' => $original->id,
            ], $items, $userName);
        });

        if (is_string($result)) {
            return response()->json(['message' => $result], 422);
        }

        return response()->json($this->details($result), 201);
    }

    // ---------------------------------------------------------------------

    /** The adjustment with everything the details page needs. */
    private function details(StockAdjustment $adjustment): array
    {
        $adjustment->load('branch:id,name,address', 'items', 'reverses:id,ref', 'reversedBy:id,ref,reverses_id');

        return $adjustment->toArray();
    }

    /** Adjustments this user may see, with the search filters. */
    private function filtered(Request $request)
    {
        $query = $this->visible($request);

        $search = trim((string) $request->input('search'));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('ref', 'like', "%$search%")
                    ->orWhereHas('items', function ($i) use ($search) {
                        $i->where('name', 'like', "%$search%");
                    });
            });
        }

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->input('branch_id'));
        }
        if ($request->filled('reason')) {
            $reason = $request->input('reason');
            $query->whereHas('items', function ($i) use ($reason) {
                $i->where('reason', $reason);
            });
        }
        if ($request->filled('from')) {
            $query->where('date', '>=', $request->input('from'));
        }
        if ($request->filled('to')) {
            $query->where('date', '<=', $request->input('to'));
        }

        return $query;
    }

    /** Adjustments at the user's own branch, or all of them for a user with no branch. */
    private function visible(Request $request)
    {
        $query = StockAdjustment::query();
        $this->onlyMine($query, $request, 'adjust');

        $userBranch = $request->user()->branch_id;
        if ($userBranch) {
            $query->where('branch_id', $userBranch);
        }

        return $query;
    }

    /** This month's increase and decrease, and the three biggest decrease reasons. */
    private function monthTotals(Request $request): array
    {
        $adjustments = $this->visible($request)
            ->with('items')
            ->where('date', '>=', now()->startOfMonth()->toDateString())
            ->where('date', '<=', now()->endOfMonth()->toDateString())
            ->get();

        $increase = 0;
        $decrease = 0;
        $byReason = [];

        foreach ($adjustments as $adjustment) {
            $increase += $adjustment->increase_value;
            $decrease += $adjustment->decrease_value;

            foreach ($adjustment->items as $item) {
                if ($item->change_qty < 0 && in_array($item->reason, StockAdjustment::REASONS['dec'], true)) {
                    $byReason[$item->reason] = ($byReason[$item->reason] ?? 0) - $item->change_qty * $item->cost;
                }
            }
        }

        arsort($byReason);

        $topReasons = [];
        foreach (array_slice($byReason, 0, 3) as $name => $value) {
            $topReasons[] = ['name' => $name, 'value' => round($value, 2)];
        }

        return ['increase' => round($increase, 2), 'decrease' => round($decrease, 2), 'top_reasons' => $topReasons];
    }

    /** A user with no branch works everywhere; otherwise only at their own branch. */
    private function isAtBranch(Request $request, int $branchId): bool
    {
        $userBranch = $request->user()->branch_id;

        return ! $userBranch || (int) $userBranch === $branchId;
    }

    private function notYourBranch()
    {
        return response()->json(['message' => 'You can only work with stock adjustments of your own branch.'], 403);
    }

    private function productRow(Product $product, ?ProductVariant $variant, $stocks): array
    {
        $variantId = $variant ? $variant->id : null;

        return [
            'product_id' => $product->id,
            'variant_id' => $variantId,
            'name' => $variant ? $product->name.' · '.$variant->name : $product->name,
            'sku' => $variant ? $variant->sku : $product->sku,
            'barcode' => $variant ? $variant->barcode : $product->barcode,
            'unit' => $product->unit ? $product->unit->short_name : '',
            'allow_decimals' => $product->unit ? (bool) $product->unit->allow_decimals : true,
            'allow_negative_stock' => (bool) $product->allow_negative_stock,
            'cost' => $variant ? $variant->cost : $product->cost,
            'on_hand' => (float) $stocks->where('product_id', $product->id)->where('variant_id', $variantId)->sum('qty'),
        ];
    }

    /** On hand now. Locks the row, so two adjustments at the same moment cannot both use the same quantity. */
    private function onHand(int $productId, ?int $variantId, int $branchId): float
    {
        return (float) ProductStock::where('product_id', $productId)
            ->where('variant_id', $variantId)
            ->where('branch_id', $branchId)
            ->lockForUpdate()
            ->value('qty');
    }

    /**
     * Turn the form lines into stock_adjustment_items rows.
     * Returns the rows, or a message (string) if a line is not allowed.
     */
    private function buildItems(array $data)
    {
        $items = [];
        $seen = [];

        foreach ($data['lines'] as $line) {
            $product = Product::with('unit:id,short_name,allow_decimals')->find($line['product_id']);

            if (! $product->manage_stock) {
                return $product->name.' does not manage stock, so it cannot be adjusted.';
            }
            if (! $product->branches()->where('branches.id', $data['branch_id'])->exists()) {
                return $product->name.' is not sold at this branch. Tick the branch in Edit product first.';
            }

            $variant = null;
            if ($product->type === 'variable') {
                $variant = ProductVariant::where('product_id', $product->id)->where('id', $line['variant_id'] ?? 0)->first();
                if (! $variant) {
                    return 'Pick one of the variants of '.$product->name.'.';
                }
            }

            $name = $variant ? $product->name.' · '.$variant->name : $product->name;

            $key = $product->id.'-'.($variant ? $variant->id : 0);
            if (in_array($key, $seen, true)) {
                return $name.' is on this adjustment twice.';
            }
            $seen[] = $key;

            $qty = (float) $line['qty'];
            if ($product->unit && ! $product->unit->allow_decimals && floor($qty) != $qty) {
                return $name.': whole units only.';
            }
            if ($data['type'] !== 'count' && $qty <= 0) {
                return $name.': enter a quantity.';
            }

            $onHand = $this->onHand($product->id, $variant ? $variant->id : null, $data['branch_id']);

            if ($data['type'] === 'count') {
                $change = round($qty - $onHand, 3);
            } elseif ($data['type'] === 'inc') {
                $change = $qty;
            } else {
                $change = -$qty;
            }

            if ($change == 0) {
                continue; // a count that matches the system
            }
            if ($onHand + $change < 0 && ! $product->allow_negative_stock) {
                return $name.': cannot remove more than the '.$onHand.' on hand.';
            }

            $reason = $line['reason'] ?? '';
            $allowed = StockAdjustment::REASONS[$change > 0 ? 'inc' : 'dec'];
            if (! in_array($reason, $allowed, true)) {
                return $name.': pick a reason.';
            }

            $items[] = [
                'product_id' => $product->id,
                'variant_id' => $variant ? $variant->id : null,
                'name' => $name,
                'sku' => $variant ? $variant->sku : $product->sku,
                'unit' => $product->unit ? $product->unit->short_name : null,
                'before_qty' => $onHand,
                'change_qty' => $change,
                'cost' => $variant ? $variant->cost : $product->cost,
                'reason' => $reason,
            ];
        }

        if (! $items) {
            return 'The counts match the system, so there is nothing to post.';
        }

        return $items;
    }

    private function values(array $items): array
    {
        $increase = 0;
        $decrease = 0;

        foreach ($items as $item) {
            if ($item['change_qty'] > 0) {
                $increase += $item['change_qty'] * $item['cost'];
            } else {
                $decrease -= $item['change_qty'] * $item['cost'];
            }
        }

        return ['increase_value' => round($increase, 2), 'decrease_value' => round($decrease, 2)];
    }

    /** Save the adjustment and its lines, and change the stock. */
    private function saveAdjustment(array $fields, array $items, string $userName): StockAdjustment
    {
        $adjustment = StockAdjustment::create($fields + $this->values($items) + [
            'ref' => $this->nextRef(),
            'created_by' => $userName,
            'user_id' => (string) request()->user()->id,
        ]);
        $adjustment->items()->createMany($items);

        foreach ($items as $item) {
            StockMovement::record($item['product_id'], $item['variant_id'], $adjustment->branch_id, $item['change_qty'], 'Adjustment', $adjustment->ref, $item['reason'], $userName);
        }

        return $adjustment;
    }

    /** "SA-0231" gives "SA-0232". */
    private function nextRef(): string
    {
        $max = 0;
        foreach (StockAdjustment::where('ref', 'like', 'SA-%')->pluck('ref') as $ref) {
            $number = substr($ref, 3);
            if (ctype_digit($number) && (int) $number > $max) {
                $max = (int) $number;
            }
        }

        return 'SA-'.str_pad((string) ($max + 1), 4, '0', STR_PAD_LEFT);
    }
}
