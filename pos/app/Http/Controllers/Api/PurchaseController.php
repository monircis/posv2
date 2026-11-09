<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Contact;
use App\Models\PaymentAccount;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\ProductVariant;
use App\Models\Purchase;
use App\Models\PurchaseSetting;
use App\Models\StockMovement;
use App\Models\StockSetting;
use App\Models\TaxRate;
use App\Models\TaxSetting;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Purchases, branch by branch. A user with a branch only sees and works on that branch;
 * a user with no branch (all branches) can pick any branch.
 */
#[Group('Purchases', 'Purchases per branch. Receiving adds stock at the branch and updates the product cost.', weight: 10)]
class PurchaseController extends Controller
{
    /**
     * List purchases
     *
     * 25 per page, newest first. Filters: `branch_id`, `tab` (ordered, received, unpaid), `search` (reference or supplier),
     * `supplier_id`, `payment_status` (paid, partial, due), `from`, `to`.
     * Also returns `counts` for the tabs and `totals` for the current filter.
     * A user with a branch always gets only that branch. Needs `purchases.view`.
     */
    #[QueryParameter('branch_id', 'Only this branch. Ignored for a user who has a branch.', type: 'int')]
    #[QueryParameter('tab', 'ordered, received or unpaid.', type: 'string')]
    #[QueryParameter('search', 'Reference or supplier name.', type: 'string')]
    #[QueryParameter('supplier_id', 'Only this supplier.', type: 'int')]
    #[QueryParameter('payment_status', 'paid, partial or due.', type: 'string')]
    #[QueryParameter('from', 'From this date, e.g. 2026-09-01.', type: 'string', format: 'date')]
    #[QueryParameter('to', 'Up to this date, e.g. 2026-09-30.', type: 'string', format: 'date')]
    public function index(Request $request)
    {
        $query = $this->filtered($request);
        $this->tabFilter($query, $request->input('tab'));

        $totalsQuery = clone $query;
        $totals = [
            'total' => (float) $totalsQuery->sum('total'),
            'due' => (float) $totalsQuery->sum('due'),
        ];

        $page = $query->with('supplier:id,name', 'branch:id,name')
            ->withCount('items')
            ->orderBy('date', 'desc')
            ->orderBy('id', 'desc')
            ->paginate(25);

        $counts = [];
        foreach (['all', 'ordered', 'received', 'unpaid'] as $tab) {
            $countQuery = $this->filtered($request);
            $this->tabFilter($countQuery, $tab);
            $counts[$tab] = $countQuery->count();
        }

        return [
            'data' => $page->items(),
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'total' => $page->total(),
            'counts' => $counts,
            'totals' => $totals,
            'owed' => (float) $this->filtered($request)->sum('due'),
        ];
    }

    /**
     * Form defaults and choices
     *
     * What the new-purchase form starts with: the next reference, tax rate, default branch, and the payment accounts.
     * Needs `purchases.create` or `purchases.edit`.
     */
    public function formData(Request $request)
    {
        return [
            'next_ref' => $this->nextRef(),
            'tax_rate' => $this->taxRate(),
            'branch_id' => $request->user()->branch_id ?? StockSetting::current()->default_branch_id,
            'default_terms' => PurchaseSetting::current()->default_supplier_terms,
            'payment_accounts' => PaymentAccount::where('active', true)->orderBy('name')->get(['id', 'name', 'branch_id', 'bal', 'defs']),
        ];
    }

    /**
     * Find products to buy
     *
     * Products sold at the branch, one row per product or variant, with the stock at that branch.
     * `branch_id` is required. `search` matches name, SKU or barcode. `low=1` lists only stock at or below the alert quantity.
     * Needs `purchases.create` or `purchases.edit`.
     */
    #[QueryParameter('search', 'Name, SKU or barcode.', type: 'string')]
    public function products(Request $request)
    {
        $request->validate(['branch_id' => 'required|exists:branches,id']);
        $branchId = (int) $request->input('branch_id');
        if (! $this->canUseBranch($request, $branchId)) {
            return $this->wrongBranch();
        }

        $query = Product::with('unit:id,short_name', 'variants')
            ->where('active', true)
            ->whereHas('branches', function ($q) use ($branchId) {
                $q->where('branches.id', $branchId);
            });

        $search = trim((string) $request->input('search'));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%$search%")
                    ->orWhere('sku', 'like', "%$search%")
                    ->orWhere('barcode', $search)
                    ->orWhereHas('variants', function ($v) use ($search) {
                        $v->where('sku', 'like', "%$search%")->orWhere('barcode', $search);
                    });
            });
        }

        $lowOnly = $request->boolean('low');
        $rows = [];

        foreach ($query->orderBy('name')->get() as $product) {
            $stocks = ProductStock::where('product_id', $product->id)->where('branch_id', $branchId)->get();
            $unit = $product->unit ? $product->unit->short_name : '';

            if ($product->type === 'variable') {
                foreach ($product->variants as $variant) {
                    $qty = (float) $stocks->where('variant_id', $variant->id)->sum('qty');
                    $rows[] = $this->productRow($product, $variant->id, $product->name.' · '.$variant->name, $variant->sku, $variant->cost, $qty, $unit);
                }
            } else {
                $qty = (float) $stocks->sum('qty');
                $rows[] = $this->productRow($product, null, $product->name, $product->sku, $product->cost, $qty, $unit);
            }
        }

        // Keep the rows we want, up to 12 of them.
        $result = [];
        foreach ($rows as $row) {
            if ($lowOnly && (! $row['manage_stock'] || $row['stock'] > $row['alert_qty'])) {
                continue;
            }
            $result[] = $row;
            if (count($result) === 12) {
                break;
            }
        }

        return $result;
    }

    /**
     * Add purchase
     *
     * Leave `ref` empty for the next number. `receive: true` saves and receives the stock now; otherwise it is saved as ordered.
     * Totals are worked out here. `paid_amount` with `payment_account_id` also records a payment.
     * Needs `purchases.create`.
     */
    public function store(Request $request)
    {
        $data = $request->validate($this->rules());

        if (! $this->canUseBranch($request, $data['branch_id'])) {
            return $this->wrongBranch();
        }
        $error = $this->checkSupplier($data['supplier_id'], (int) $data['branch_id']);
        if ($error) {
            return response()->json(['message' => $error], 422);
        }
        $items = $this->buildItems($data['lines'], $data['branch_id']);
        if (is_string($items)) {
            return response()->json(['message' => $items], 422);
        }

        $header = $this->header($data, $items, $this->taxRate());

        $paidAmount = round((float) ($data['paid_amount'] ?? 0), 2);
        if ($paidAmount > $header['total']) {
            return response()->json(['message' => 'The payment is more than the total of '.number_format($header['total'], 2).'.'], 422);
        }
        if ($paidAmount > 0 && empty($data['payment_account_id'])) {
            return response()->json(['message' => 'Pick the account the payment comes from.'], 422);
        }

        $userName = $request->user()->name;

        $purchase = DB::transaction(function () use ($data, $items, $header, $paidAmount, $userName) {
            $purchase = Purchase::create($header + [
                'ref' => ! empty($data['ref']) ? $data['ref'] : $this->nextRef(),
                'status' => 'ordered',
                'created_by' => $userName,
                'user_id' => (string) request()->user()->id,
            ]);
            $purchase->items()->createMany($items);
            $purchase->updateDue();

            if ($paidAmount > 0) {
                $purchase->addPayment($paidAmount, $data['payment_account_id'], $data['date'], null, $userName);
            }
            if (! empty($data['receive'])) {
                $this->receiveStock($purchase, $userName);
            }

            return $purchase;
        });

        return response()->json($this->details($purchase), 201);
    }

    /**
     * Show purchase
     *
     * With supplier, branch, items (each with `returned` and `returnable`), payments and returns.
     * Needs `purchases.view` (a user with a branch: only that branch).
     */
    public function show(Request $request, Purchase $purchase)
    {
        if (! $this->isMine($request, $purchase, 'purchases')) {
            return $this->notMine();
        }
        if (! $this->canUseBranch($request, $purchase->branch_id)) {
            return $this->wrongBranch();
        }

        return $this->details($purchase);
    }

    /**
     * Update purchase
     *
     * Only before it is received: after that, send items back with a return. Send all fields and lines.
     * `receive: true` also receives the stock. Needs `purchases.edit`.
     */
    public function update(Request $request, Purchase $purchase)
    {
        if (! $this->isMine($request, $purchase, 'purchases')) {
            return $this->notMine();
        }
        if (! $this->canUseBranch($request, $purchase->branch_id)) {
            return $this->wrongBranch();
        }
        if ($purchase->status === 'received') {
            return response()->json(['message' => $purchase->ref.' is already received. Use Return items to send stock back.'], 422);
        }

        $data = $request->validate($this->rules($purchase));

        if (! $this->canUseBranch($request, $data['branch_id'])) {
            return $this->wrongBranch();
        }
        $error = $this->checkSupplier($data['supplier_id'], (int) $data['branch_id']);
        if ($error) {
            return response()->json(['message' => $error], 422);
        }
        $items = $this->buildItems($data['lines'], $data['branch_id']);
        if (is_string($items)) {
            return response()->json(['message' => $items], 422);
        }

        $header = $this->header($data, $items, $purchase->tax_rate);
        if ($header['total'] < $purchase->paid) {
            return response()->json(['message' => 'The new total is less than the '.number_format($purchase->paid, 2).' already paid.'], 422);
        }

        $userName = $request->user()->name;

        DB::transaction(function () use ($purchase, $data, $items, $header, $userName) {
            $purchase->update($header + ['ref' => ! empty($data['ref']) ? $data['ref'] : $purchase->ref]);
            $purchase->items()->delete();
            $purchase->items()->createMany($items);
            $purchase->updateDue();

            if (! empty($data['receive'])) {
                $this->receiveStock($purchase, $userName);
            }
        });

        return $this->details($purchase->fresh());
    }

    /**
     * Receive stock
     *
     * Adds every line to the stock of the purchase's branch, updates the product cost and adds the total to what we owe
     * the supplier. Needs `purchases.edit`.
     */
    public function receive(Request $request, Purchase $purchase)
    {
        if (! $this->isMine($request, $purchase, 'purchases')) {
            return $this->notMine();
        }
        if (! $this->canUseBranch($request, $purchase->branch_id)) {
            return $this->wrongBranch();
        }
        if ($purchase->status === 'received') {
            return response()->json(['message' => $purchase->ref.' is already received.'], 422);
        }

        $userName = $request->user()->name;

        DB::transaction(function () use ($purchase, $userName) {
            $this->receiveStock($purchase, $userName);
        });

        return $this->details($purchase->fresh());
    }

    /**
     * Delete purchase
     *
     * Only when it is not received and has no payments. Needs `purchases.delete`.
     */
    public function destroy(Request $request, Purchase $purchase)
    {
        if (! $this->isMine($request, $purchase, 'purchases')) {
            return $this->notMine();
        }
        if (! $this->canUseBranch($request, $purchase->branch_id)) {
            return $this->wrongBranch();
        }
        if ($purchase->status === 'received') {
            return response()->json(['message' => $purchase->ref.' is received, so it cannot be deleted. Use Return items instead.'], 422);
        }
        if ($purchase->payments()->exists()) {
            return response()->json(['message' => $purchase->ref.' has payments, so it cannot be deleted.'], 422);
        }

        if ($purchase->invoice_file) {
            Storage::disk('public')->delete($purchase->invoice_file);
        }
        $purchase->delete();

        return response()->noContent();
    }

    /**
     * Upload supplier invoice
     *
     * Send the file in the "file" form field (PDF, PNG or JPG, up to 10 MB). The old file is deleted. Needs `purchases.create` or `purchases.edit`.
     */
    public function uploadInvoice(Request $request, Purchase $purchase)
    {
        if (! $this->isMine($request, $purchase, 'purchases')) {
            return $this->notMine();
        }
        if (! $this->canUseBranch($request, $purchase->branch_id)) {
            return $this->wrongBranch();
        }
        $request->validate(['file' => 'required|file|mimes:pdf,png,jpg,jpeg|max:10240']);

        if ($purchase->invoice_file) {
            Storage::disk('public')->delete($purchase->invoice_file);
        }
        $purchase->invoice_file = $request->file('file')->store('purchases', 'public');
        $purchase->save();

        return $this->details($purchase);
    }

    // ---------------------------------------------------------------------

    /** The purchase with everything the details page, the edit form and the return form need. */
    private function details(Purchase $purchase): array
    {
        $purchase->load('supplier:id,name,pay_term,balance', 'branch:id,name', 'items.returnItems', 'payments', 'returns');

        $row = $purchase->toArray();
        $row['invoice_url'] = $purchase->invoice_file ? Storage::disk('public')->url($purchase->invoice_file) : null;

        foreach ($purchase->items as $i => $item) {
            $returned = (float) $item->returnItems->sum('qty');
            $row['items'][$i]['returned'] = $returned;
            $row['items'][$i]['returnable'] = max(0, $item->qty - $returned);
            $row['items'][$i]['on_hand'] = (float) ProductStock::where('product_id', $item->product_id)
                ->where('variant_id', $item->variant_id)
                ->where('branch_id', $purchase->branch_id)
                ->value('qty');
            unset($row['items'][$i]['return_items']);
        }

        return $row;
    }

    /** Purchases this user may see, with the search filters. */
    private function filtered(Request $request)
    {
        $query = Purchase::query();
        $this->onlyMine($query, $request, 'purchases');

        // A user with a branch only ever gets that branch.
        $branchId = $request->user()->branch_id ?? $request->input('branch_id');
        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        $search = trim((string) $request->input('search'));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('ref', 'like', "%$search%")
                    ->orWhereHas('supplier', function ($s) use ($search) {
                        $s->where('name', 'like', "%$search%");
                    });
            });
        }

        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->input('supplier_id'));
        }
        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->input('payment_status'));
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
        if ($tab === 'ordered') {
            $query->whereIn('status', ['ordered', 'pending']);
        } elseif ($tab === 'received') {
            $query->where('status', 'received');
        } elseif ($tab === 'unpaid') {
            $query->where('due', '>', 0);
        }
    }

    /** A user with a branch can only work on that branch. */
    private function canUseBranch(Request $request, int $branchId): bool
    {
        $userBranch = $request->user()->branch_id;

        return ! $userBranch || (int) $userBranch === $branchId;
    }

    private function wrongBranch()
    {
        return response()->json(['message' => 'You can only work with purchases of your own branch.'], 403);
    }

    private function checkSupplier(int $supplierId, int $branchId): ?string
    {
        $supplier = Contact::find($supplierId);
        if ($supplier->type === 'Customer') {
            return $supplier->name.' is a customer, not a supplier. Change the type in Contacts first.';
        }
        if (! $supplier->isAtBranch($branchId)) {
            return $supplier->name.' is a supplier of another branch.';
        }

        return null;
    }

    private function productRow(Product $product, ?int $variantId, string $name, string $sku, float $cost, float $stock, string $unit): array
    {
        return [
            'product_id' => $product->id,
            'variant_id' => $variantId,
            'name' => $name,
            'sku' => $sku,
            'unit' => $unit,
            'cost' => $cost,
            'stock' => $stock,
            'alert_qty' => $product->alert_qty,
            'manage_stock' => $product->manage_stock,
        ];
    }

    /**
     * Turn the form lines into purchase_items rows.
     * Returns the rows, or a message (string) if a line is not allowed.
     */
    private function buildItems(array $lines, int $branchId)
    {
        $branch = Branch::find($branchId);
        $items = [];

        foreach ($lines as $line) {
            $product = Product::with('unit:id,short_name')->find($line['product_id']);

            if (! $product->branches()->where('branches.id', $branchId)->exists()) {
                return $product->name.' is not sold at '.$branch->name.'. Tick the branch in Edit product first.';
            }

            $name = $product->name;
            $variantId = null;
            if ($product->type === 'variable') {
                $variant = ProductVariant::where('product_id', $product->id)->where('id', $line['variant_id'] ?? 0)->first();
                if (! $variant) {
                    return 'Pick one of the variants of '.$product->name.'.';
                }
                $variantId = $variant->id;
                $name = $product->name.' · '.$variant->name;
            }

            $discount = (float) ($line['discount'] ?? 0);

            $items[] = [
                'product_id' => $product->id,
                'variant_id' => $variantId,
                'name' => $name,
                'unit' => $product->unit ? $product->unit->short_name : null,
                'qty' => $line['qty'],
                'cost' => $line['cost'],
                'discount' => $discount,
                'line_total' => round($line['qty'] * $line['cost'] * (1 - $discount / 100), 2),
            ];
        }

        return $items;
    }

    /** The purchase columns from the form, with the totals worked out from the lines. */
    private function header(array $data, array $items, float $taxRate): array
    {
        $subtotal = 0;
        foreach ($items as $item) {
            $subtotal += $item['line_total'];
        }
        $discount = (float) ($data['order_discount'] ?? 0);
        $freight = (float) ($data['freight'] ?? 0);
        $tax = round(($subtotal - $discount + $freight) * $taxRate / 100, 2);

        return [
            'date' => $data['date'],
            'supplier_id' => $data['supplier_id'],
            'branch_id' => $data['branch_id'],
            'subtotal' => round($subtotal, 2),
            'order_discount' => $discount,
            'freight' => $freight,
            'tax_rate' => $taxRate,
            'tax' => $tax,
            'total' => round($subtotal - $discount + $freight + $tax, 2),
            'due_date' => $this->dueDate($data['supplier_id'], $data['date']),
            'notes' => $data['notes'] ?? null,
        ];
    }

    /** "30 days" after the purchase date; the supplier's pay term, or the default from Settings > Purchases. */
    private function dueDate(int $supplierId, string $date): string
    {
        $term = Contact::find($supplierId)->pay_term ?: PurchaseSetting::current()->default_supplier_terms;
        $days = (int) $term; // "30 days" -> 30, "On delivery" -> 0

        return date('Y-m-d', strtotime($date.' +'.$days.' days'));
    }

    /** Stock in, cost update, and what we owe the supplier goes up. */
    private function receiveStock(Purchase $purchase, string $userName): void
    {
        $costMethod = PurchaseSetting::current()->cost_method;

        foreach ($purchase->items()->with('product')->get() as $item) {
            $product = $item->product;
            if (! $product) {
                continue; // the product was deleted
            }

            $unitCost = round($item->cost * (1 - $item->discount / 100), 2);
            $this->updateCost($product, $item->variant_id, $item->qty, $unitCost, $costMethod);

            if ($product->manage_stock) {
                $movement = StockMovement::record($product->id, $item->variant_id, $purchase->branch_id, $item->qty, 'Purchase received', $purchase->ref, null, $userName);
                $item->stock_after = $movement->balance;
                $item->save();
            }
        }

        $purchase->status = 'received';
        $purchase->received_at = now();
        $purchase->received_by = $userName;
        $purchase->save();

        $supplier = $purchase->supplier;
        $supplier->balance = round($supplier->balance - $purchase->total, 2);
        $supplier->business = round($supplier->business + $purchase->total, 2);
        $supplier->last_activity = $purchase->date->format('d/m');
        $supplier->save();
    }

    /** New cost of the product or variant, using the method in Settings > Purchases. */
    private function updateCost(Product $product, ?int $variantId, float $qty, float $unitCost, string $costMethod): void
    {
        $record = $variantId ? ProductVariant::find($variantId) : $product;
        if (! $record) {
            return;
        }

        $newCost = $unitCost;
        if ($costMethod === 'Weighted average') {
            // Stock at every branch, because a product has one cost.
            $onHand = (float) ProductStock::where('product_id', $product->id)->where('variant_id', $variantId)->sum('qty');
            if ($onHand > 0) {
                $newCost = round((($onHand * $record->cost) + ($qty * $unitCost)) / ($onHand + $qty), 2);
            }
        }

        $record->cost = $newCost;
        $record->save();
    }

    /** The tax rate for new purchases: 0 when not registered for GST. */
    private function taxRate(): float
    {
        if (! TaxSetting::current()->gst_registered) {
            return 0;
        }

        return (float) TaxRate::where('active', true)->where('rate', '>', 0)->orderBy('id')->value('rate');
    }

    /** The PO prefix from Settings > Purchases plus the next free number: "PO-" and PO-2043 give PO-2044. */
    private function nextRef(): string
    {
        $prefix = (string) PurchaseSetting::current()->po_prefix;
        $max = 0;
        foreach (Purchase::where('ref', 'like', $prefix.'%')->pluck('ref') as $ref) {
            $rest = substr($ref, strlen($prefix));
            if (ctype_digit($rest) && (int) $rest > $max) {
                $max = (int) $rest;
            }
        }

        return $prefix.($max + 1);
    }

    private function rules(?Purchase $purchase = null): array
    {
        return [
            'ref' => ['nullable', 'string', 'max:40', Rule::unique('purchases')->ignore($purchase)],
            'date' => 'required|date',
            'supplier_id' => 'required|exists:contacts,id',
            'branch_id' => 'required|exists:branches,id',
            'order_discount' => 'nullable|numeric|min:0',
            'freight' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
            'receive' => 'boolean',
            // First payment (new purchases only)
            'paid_amount' => 'nullable|numeric|min:0',
            'payment_account_id' => 'nullable|exists:payment_accounts,id',
            // Lines
            'lines' => 'required|array|min:1',
            'lines.*.product_id' => 'required|exists:products,id',
            'lines.*.variant_id' => 'nullable|integer',
            'lines.*.qty' => 'required|numeric|gt:0',
            'lines.*.cost' => 'required|numeric|min:0',
            'lines.*.discount' => 'nullable|numeric|min:0|max:100',
        ];
    }
}
