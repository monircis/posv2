<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\StockSetting;
use App\Models\StockTransfer;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Stock transfers between branches.
 * Sending takes the stock out of the source branch; receiving adds what arrived to the destination.
 * A user with a branch sees transfers in or out of it, sends only from it and receives only into it.
 */
#[Group('Stock Transfers', 'Move stock between branches: draft, send, receive. Less received than sent is written off as transit loss.', weight: 11)]
class StockTransferController extends Controller
{
    /**
     * List transfers
     *
     * 25 per page, newest first. Also returns `counts` for the tabs, `value` for the current filter
     * and `transit` (how many are on the road and their value). Needs `transfers.view`.
     */
    #[QueryParameter('tab', 'draft, transit, received (with variance) or cancelled.', type: 'string')]
    #[QueryParameter('search', 'Reference or product name.', type: 'string')]
    #[QueryParameter('from_branch_id', 'Sent from this branch.', type: 'int')]
    #[QueryParameter('to_branch_id', 'Sent to this branch.', type: 'int')]
    #[QueryParameter('from', 'From this date, e.g. 2026-09-01.', type: 'string', format: 'date')]
    #[QueryParameter('to', 'Up to this date, e.g. 2026-09-30.', type: 'string', format: 'date')]
    public function index(Request $request)
    {
        $query = $this->filtered($request);
        $this->tabFilter($query, $request->input('tab'));

        $value = (float) (clone $query)->sum(DB::raw('stock_value + shipping_cost'));

        $page = $query->with('fromBranch:id,name', 'toBranch:id,name', 'items:id,stock_transfer_id,name')
            ->orderBy('date', 'desc')
            ->orderBy('id', 'desc')
            ->paginate(25);

        $counts = [];
        foreach (['all', 'draft', 'transit', 'received', 'cancelled'] as $tab) {
            $countQuery = $this->filtered($request);
            $this->tabFilter($countQuery, $tab);
            $counts[$tab] = $countQuery->count();
        }

        $onTheRoad = $this->filtered($request)->where('status', 'transit');

        return [
            'data' => $page->items(),
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'total' => $page->total(),
            'counts' => $counts,
            'value' => $value,
            'transit' => [
                'count' => (clone $onTheRoad)->count(),
                'value' => (float) (clone $onTheRoad)->sum('stock_value'),
            ],
        ];
    }

    /**
     * Form defaults
     *
     * The next reference and the source branch the form starts with (the user's own branch, if they have one).
     * Needs `transfers.create` or `transfers.edit`.
     */
    public function formData(Request $request)
    {
        return [
            'next_ref' => $this->nextRef(),
            'from_branch_id' => $request->user()->branch_id ?? StockSetting::current()->default_branch_id,
        ];
    }

    /**
     * Products to transfer
     *
     * Products that manage stock and are sold at both branches, one row per product or variant,
     * with `from_stock` and `to_stock`. Needs `transfers.create` or `transfers.edit`.
     */
    #[QueryParameter('from_branch_id', 'The branch stock leaves.', required: true, type: 'int')]
    #[QueryParameter('to_branch_id', 'The branch stock goes to.', required: true, type: 'int')]
    public function products(Request $request)
    {
        $request->validate([
            'from_branch_id' => 'required|exists:branches,id',
            'to_branch_id' => 'required|exists:branches,id',
        ]);
        $fromId = (int) $request->input('from_branch_id');
        $toId = (int) $request->input('to_branch_id');

        $products = Product::with('unit:id,short_name,allow_decimals', 'variants')
            ->where('active', true)
            ->where('manage_stock', true)
            ->whereHas('branches', function ($q) use ($fromId) {
                $q->where('branches.id', $fromId);
            })
            ->whereHas('branches', function ($q) use ($toId) {
                $q->where('branches.id', $toId);
            })
            ->orderBy('name')
            ->get();

        $stocks = ProductStock::whereIn('branch_id', [$fromId, $toId])->get();

        $rows = [];
        foreach ($products as $product) {
            if ($product->type === 'variable') {
                foreach ($product->variants as $variant) {
                    $rows[] = $this->productRow($product, $variant, $stocks, $fromId, $toId);
                }
            } else {
                $rows[] = $this->productRow($product, null, $stocks, $fromId, $toId);
            }
        }

        return $rows;
    }

    /**
     * Add transfer
     *
     * Saved as a draft, or with `send: true` sent straight away (the stock leaves the source branch).
     * The reference is given automatically. Needs `transfers.create`.
     */
    public function store(Request $request)
    {
        $data = $request->validate($this->rules());

        if (! $this->isAtBranch($request, $data['from_branch_id'])) {
            return $this->notYourBranch('send stock from');
        }
        $items = $this->buildItems($data['lines'], $data['from_branch_id'], $data['to_branch_id']);
        if (is_string($items)) {
            return response()->json(['message' => $items], 422);
        }
        if (! empty($data['send'])) {
            $problem = $this->stockProblem($items, $data['from_branch_id']);
            if ($problem) {
                return response()->json(['message' => $problem], 422);
            }
        }

        $userName = $request->user()->name;

        $transfer = DB::transaction(function () use ($data, $items, $userName) {
            $transfer = StockTransfer::create($this->fields($data, $items) + [
                'ref' => $this->nextRef(),
                'status' => 'draft',
                'created_by' => $userName,
                'user_id' => (string) request()->user()->id,
            ]);
            $transfer->items()->createMany($items);
            $transfer->addLog('Created by '.$userName);

            if (! empty($data['send'])) {
                $this->sendStock($transfer, $userName);
            }

            return $transfer;
        });

        return response()->json($this->details($transfer), 201);
    }

    /**
     * Show transfer
     *
     * With both branches, the lines and the activity list. Needs `transfers.view`
     * (a user with a branch: only transfers in or out of it).
     */
    public function show(Request $request, StockTransfer $stockTransfer)
    {
        if (! $this->isMine($request, $stockTransfer, 'transfers')) {
            return $this->notMine();
        }
        if (! $this->canSee($request, $stockTransfer)) {
            return $this->notYourBranch('see this transfer of');
        }

        return $this->details($stockTransfer);
    }

    /**
     * Update transfer
     *
     * Only a draft. Send all fields and lines. `send: true` also sends it. Needs `transfers.edit`
     * (a user with a branch: only from that branch).
     */
    public function update(Request $request, StockTransfer $stockTransfer)
    {
        if (! $this->isMine($request, $stockTransfer, 'transfers')) {
            return $this->notMine();
        }
        if (! $this->isAtBranch($request, $stockTransfer->from_branch_id)) {
            return $this->notYourBranch('change transfers from');
        }
        if ($stockTransfer->status !== 'draft') {
            return response()->json(['message' => 'Only a draft can be edited.'], 422);
        }

        $data = $request->validate($this->rules());

        if (! $this->isAtBranch($request, $data['from_branch_id'])) {
            return $this->notYourBranch('send stock from');
        }
        $items = $this->buildItems($data['lines'], $data['from_branch_id'], $data['to_branch_id']);
        if (is_string($items)) {
            return response()->json(['message' => $items], 422);
        }
        if (! empty($data['send'])) {
            $problem = $this->stockProblem($items, $data['from_branch_id']);
            if ($problem) {
                return response()->json(['message' => $problem], 422);
            }
        }

        $userName = $request->user()->name;

        DB::transaction(function () use ($stockTransfer, $data, $items, $userName) {
            $stockTransfer->update($this->fields($data, $items));
            $stockTransfer->items()->delete();
            $stockTransfer->items()->createMany($items);
            $stockTransfer->addLog('Edited by '.$userName);

            if (! empty($data['send'])) {
                $this->sendStock($stockTransfer, $userName);
            }
        });

        return $this->details($stockTransfer->fresh());
    }

    /**
     * Send transfer
     *
     * Takes the stock out of the source branch. Refused if the branch does not have enough.
     * Needs `transfers.edit` (a user with a branch: only from that branch).
     */
    public function send(Request $request, StockTransfer $stockTransfer)
    {
        if (! $this->isMine($request, $stockTransfer, 'transfers')) {
            return $this->notMine();
        }
        if (! $this->isAtBranch($request, $stockTransfer->from_branch_id)) {
            return $this->notYourBranch('send stock from');
        }

        $userName = $request->user()->name;

        // Locking the row stops the same transfer being sent twice at the same moment.
        $error = DB::transaction(function () use ($stockTransfer, $userName) {
            $transfer = StockTransfer::lockForUpdate()->find($stockTransfer->id);
            if ($transfer->status !== 'draft') {
                return $transfer->ref.' has already been sent or cancelled.';
            }
            $problem = $this->stockProblem($transfer->items->toArray(), $transfer->from_branch_id);
            if ($problem) {
                return $problem;
            }
            $this->sendStock($transfer, $userName);

            return null;
        });

        if ($error) {
            return response()->json(['message' => $error], 422);
        }

        return $this->details($stockTransfer->fresh());
    }

    /**
     * Receive transfer
     *
     * Adds what arrived to the destination branch. `lines` is optional: a line left out counts as received in full.
     * Anything short is written off as transit loss. Needs `transfers.edit` (a user with a branch: only into that branch).
     */
    public function receive(Request $request, StockTransfer $stockTransfer)
    {
        if (! $this->isMine($request, $stockTransfer, 'transfers')) {
            return $this->notMine();
        }
        if (! $this->isAtBranch($request, $stockTransfer->to_branch_id)) {
            return $this->notYourBranch('receive stock into');
        }

        $data = $request->validate([
            'lines' => 'nullable|array',
            'lines.*.id' => 'required|integer',
            'lines.*.received_qty' => 'required|numeric|min:0',
        ]);

        // What arrived, by line id.
        $arrived = [];
        foreach ($data['lines'] ?? [] as $line) {
            $arrived[$line['id']] = (float) $line['received_qty'];
        }

        $userName = $request->user()->name;

        $error = DB::transaction(function () use ($stockTransfer, $arrived, $userName) {
            $transfer = StockTransfer::lockForUpdate()->find($stockTransfer->id);
            if ($transfer->status !== 'transit') {
                return $transfer->ref.' is not in transit.';
            }

            foreach ($transfer->items as $item) {
                if (isset($arrived[$item->id]) && $arrived[$item->id] > $item->qty) {
                    return $item->name.': received cannot be more than the '.$item->qty.' sent.';
                }
            }

            $this->receiveStock($transfer, $arrived, $userName);

            return null;
        });

        if ($error) {
            return response()->json(['message' => $error], 422);
        }

        return $this->details($stockTransfer->fresh());
    }

    /**
     * Cancel transfer
     *
     * Only a draft: no stock has moved yet. Needs `transfers.edit` (a user with a branch: only from that branch).
     */
    public function cancel(Request $request, StockTransfer $stockTransfer)
    {
        if (! $this->isMine($request, $stockTransfer, 'transfers')) {
            return $this->notMine();
        }
        if (! $this->isAtBranch($request, $stockTransfer->from_branch_id)) {
            return $this->notYourBranch('cancel transfers from');
        }
        if ($stockTransfer->status !== 'draft') {
            return response()->json(['message' => 'Only a draft can be cancelled.'], 422);
        }

        $stockTransfer->status = 'cancelled';
        $stockTransfer->save();
        $stockTransfer->addLog('Cancelled by '.$request->user()->name, 'error');

        return $this->details($stockTransfer);
    }

    // ---------------------------------------------------------------------

    /** The transfer with everything the details page and the edit form need. */
    private function details(StockTransfer $transfer): array
    {
        $transfer->load('fromBranch:id,name,address', 'toBranch:id,name,address', 'items', 'logs');

        return $transfer->toArray();
    }

    /** Transfers this user may see, with the search filters. */
    private function filtered(Request $request)
    {
        $query = StockTransfer::query();
        $this->onlyMine($query, $request, 'transfers');

        $userBranch = $request->user()->branch_id;
        if ($userBranch) {
            $query->where(function ($q) use ($userBranch) {
                $q->where('from_branch_id', $userBranch)->orWhere('to_branch_id', $userBranch);
            });
        }

        $search = trim((string) $request->input('search'));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('ref', 'like', "%$search%")
                    ->orWhereHas('items', function ($i) use ($search) {
                        $i->where('name', 'like', "%$search%");
                    });
            });
        }

        if ($request->filled('from_branch_id')) {
            $query->where('from_branch_id', $request->input('from_branch_id'));
        }
        if ($request->filled('to_branch_id')) {
            $query->where('to_branch_id', $request->input('to_branch_id'));
        }
        if ($request->filled('from')) {
            $query->where('date', '>=', $request->input('from'));
        }
        if ($request->filled('to')) {
            $query->where('date', '<=', $request->input('to'));
        }

        return $query;
    }

    private function tabFilter($query, ?string $tab): void
    {
        if ($tab === 'received') {
            $query->whereIn('status', ['received', 'variance']);
        } elseif (in_array($tab, ['draft', 'transit', 'cancelled'], true)) {
            $query->where('status', $tab);
        }
    }

    /** A user with no branch works everywhere; otherwise only at their own branch. */
    private function isAtBranch(Request $request, int $branchId): bool
    {
        $userBranch = $request->user()->branch_id;

        return ! $userBranch || (int) $userBranch === $branchId;
    }

    private function canSee(Request $request, StockTransfer $transfer): bool
    {
        return $this->isAtBranch($request, $transfer->from_branch_id) || $this->isAtBranch($request, $transfer->to_branch_id);
    }

    private function notYourBranch(string $action)
    {
        return response()->json(['message' => 'You can only '.$action.' your own branch.'], 403);
    }

    private function productRow(Product $product, ?ProductVariant $variant, $stocks, int $fromId, int $toId): array
    {
        $variantId = $variant ? $variant->id : null;
        $productStocks = $stocks->where('product_id', $product->id)->where('variant_id', $variantId);

        return [
            'product_id' => $product->id,
            'variant_id' => $variantId,
            'name' => $variant ? $product->name.' · '.$variant->name : $product->name,
            'sku' => $variant ? $variant->sku : $product->sku,
            'barcode' => $variant ? $variant->barcode : $product->barcode,
            'unit' => $product->unit ? $product->unit->short_name : '',
            'allow_decimals' => $product->unit ? (bool) $product->unit->allow_decimals : true,
            'cost' => $variant ? $variant->cost : $product->cost,
            'alert_qty' => $product->alert_qty,
            'from_stock' => (float) $productStocks->where('branch_id', $fromId)->sum('qty'),
            'to_stock' => (float) $productStocks->where('branch_id', $toId)->sum('qty'),
        ];
    }

    /**
     * Turn the form lines into stock_transfer_items rows.
     * Returns the rows, or a message (string) if a line is not allowed.
     */
    private function buildItems(array $lines, int $fromId, int $toId)
    {
        $fromName = Branch::where('id', $fromId)->value('name');
        $toName = Branch::where('id', $toId)->value('name');
        $items = [];
        $seen = [];

        foreach ($lines as $line) {
            $product = Product::with('unit:id,short_name,allow_decimals')->find($line['product_id']);

            if (! $product->manage_stock) {
                return $product->name.' does not manage stock, so it cannot be transferred.';
            }
            if (! $product->branches()->where('branches.id', $fromId)->exists()) {
                return $product->name.' is not sold at '.$fromName.'.';
            }
            if (! $product->branches()->where('branches.id', $toId)->exists()) {
                return $product->name.' is not sold at '.$toName.'. Tick the branch in Edit product first.';
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
                return $name.' is on this transfer twice.';
            }
            $seen[] = $key;

            if ($product->unit && ! $product->unit->allow_decimals && floor($line['qty']) != $line['qty']) {
                return $name.': whole units only.';
            }

            $items[] = [
                'product_id' => $product->id,
                'variant_id' => $variant ? $variant->id : null,
                'name' => $name,
                'sku' => $variant ? $variant->sku : $product->sku,
                'unit' => $product->unit ? $product->unit->short_name : null,
                'qty' => $line['qty'],
                'cost' => $variant ? $variant->cost : $product->cost,
            ];
        }

        return $items;
    }

    /** The transfer columns from the form. */
    private function fields(array $data, array $items): array
    {
        return [
            'date' => $data['date'],
            'from_branch_id' => $data['from_branch_id'],
            'to_branch_id' => $data['to_branch_id'],
            'stock_value' => $this->stockValue($items),
            'shipping_cost' => $data['shipping_cost'] ?? 0,
            'notes' => $data['notes'] ?? null,
        ];
    }

    private function stockValue($items): float
    {
        $value = 0;
        foreach ($items as $item) {
            $value += $item['qty'] * $item['cost'];
        }

        return round($value, 2);
    }

    /** A message if the branch does not have enough of a line, otherwise null. */
    private function stockProblem($items, int $branchId): ?string
    {
        $branchName = Branch::where('id', $branchId)->value('name');

        foreach ($items as $item) {
            if (! $item['product_id']) {
                return $item['name'].' no longer exists. Edit the draft first.';
            }

            $onHand = (float) ProductStock::where('product_id', $item['product_id'])
                ->where('variant_id', $item['variant_id'])
                ->where('branch_id', $branchId)
                ->value('qty');

            if ($item['qty'] > $onHand) {
                return 'Only '.$onHand.' of '.$item['name'].' at '.$branchName.'. Lower the quantity first.';
            }
        }

        return null;
    }

    /** Stock leaves the source branch. Costs are taken again in case they changed while it was a draft. */
    private function sendStock(StockTransfer $transfer, string $userName): void
    {
        foreach ($transfer->items()->with('product', 'variant')->get() as $item) {
            $item->cost = $item->variant ? $item->variant->cost : $item->product->cost;
            $item->save();

            StockMovement::record($item->product_id, $item->variant_id, $transfer->from_branch_id, -$item->qty, 'Transfer out', $transfer->ref, null, $userName);
        }

        $transfer->stock_value = $this->stockValue($transfer->items()->get());
        $transfer->status = 'transit';
        $transfer->sent_at = now();
        $transfer->save();
        $transfer->addLog('Sent by '.$userName, 'info');
    }

    /** What arrived goes into the destination branch; anything short is written off. */
    private function receiveStock(StockTransfer $transfer, array $arrived, string $userName): void
    {
        $loss = 0;

        foreach ($transfer->items as $item) {
            $received = $arrived[$item->id] ?? $item->qty;
            $item->received_qty = $received;
            $item->save();

            $short = $item->qty - $received;
            $loss += $short * $item->cost;

            if ($received > 0 && $item->product_id) {
                $note = $short > 0 ? $short.' short, written off' : null;
                StockMovement::record($item->product_id, $item->variant_id, $transfer->to_branch_id, $received, 'Transfer in', $transfer->ref, $note, $userName);
            }
        }

        $transfer->loss_value = round($loss, 2);
        $transfer->status = $loss > 0 ? 'variance' : 'received';
        $transfer->received_at = now();
        $transfer->save();

        if ($loss > 0) {
            $transfer->addLog('Received by '.$userName.', '.number_format($loss, 2).' short written off', 'warn');
        } else {
            $transfer->addLog('Received in full by '.$userName, 'success');
        }
    }

    /** "TR-0418" gives "TR-0419". */
    private function nextRef(): string
    {
        $max = 0;
        foreach (StockTransfer::where('ref', 'like', 'TR-%')->pluck('ref') as $ref) {
            $number = substr($ref, 3);
            if (ctype_digit($number) && (int) $number > $max) {
                $max = (int) $number;
            }
        }

        return 'TR-'.str_pad((string) ($max + 1), 4, '0', STR_PAD_LEFT);
    }

    private function rules(): array
    {
        return [
            'date' => 'required|date',
            'from_branch_id' => 'required|exists:branches,id',
            'to_branch_id' => 'required|exists:branches,id|different:from_branch_id',
            'shipping_cost' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
            'send' => 'boolean',
            'lines' => 'required|array|min:1',
            'lines.*.product_id' => 'required|exists:products,id',
            'lines.*.variant_id' => 'nullable|integer',
            'lines.*.qty' => 'required|numeric|gt:0',
        ];
    }
}
