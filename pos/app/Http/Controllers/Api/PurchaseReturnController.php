<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\PurchaseReturn;
use App\Models\ProductStock;
use App\Models\StockMovement;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Purchases > Returns: goods sent back to the supplier, always against a received purchase. */
#[Group('Purchases', weight: 10)]
class PurchaseReturnController extends Controller
{
    /**
     * List returns
     *
     * 25 per page, newest first. Filter: `branch_id`. A user with a branch only gets that branch.
     * Also returns `total_credit`. Needs `purchases.view`.
     */
    #[QueryParameter('branch_id', 'Only this branch. Ignored for a user who has a branch.', type: 'int')]
    public function index(Request $request)
    {
        $query = PurchaseReturn::query();
        $this->onlyMine($query, $request, 'purchases');

        $branchId = $request->user()->branch_id ?? $request->input('branch_id');
        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        $totalCredit = (float) (clone $query)->sum('credit');

        $page = $query->with('supplier:id,name', 'branch:id,name', 'items')
            ->orderBy('date', 'desc')
            ->orderBy('id', 'desc')
            ->paginate(25);

        return [
            'data' => $page->items(),
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'total' => $page->total(),
            'total_credit' => $totalCredit,
            'next_ref' => $this->nextRef(),
        ];
    }

    /**
     * Add return
     *
     * Only for a received purchase. A line cannot return more than was received minus what already went back.
     * Takes the stock out of the purchase's branch. `settlement`:
     * "Reduced owed" takes the credit off what is due on the purchase (anything over goes to supplier credit),
     * "Refund" waits for the supplier to pay it back, "Supplier credit" keeps it for a later purchase.
     * Needs `purchases.create`.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'purchase_id' => 'required|exists:purchases,id',
            'date' => 'required|date',
            'settlement' => 'required|in:Reduced owed,Refund,Supplier credit',
            'notes' => 'nullable|string|max:1000',
            'lines' => 'required|array|min:1',
            'lines.*.purchase_item_id' => 'required|integer',
            'lines.*.qty' => 'required|numeric|gt:0',
            'lines.*.reason' => 'required|string|max:60',
        ]);

        $purchase = Purchase::find($data['purchase_id']);

        $userBranch = $request->user()->branch_id;
        if ($userBranch && (int) $userBranch !== $purchase->branch_id) {
            return response()->json(['message' => 'You can only work with purchases of your own branch.'], 403);
        }
        if ($purchase->status !== 'received') {
            return response()->json(['message' => $purchase->ref.' is not received yet, so there is nothing to return.'], 422);
        }

        // Check every line before anything is saved.
        $rows = [];
        $subtotal = 0;
        foreach ($data['lines'] as $line) {
            $item = PurchaseItem::with('product')->where('purchase_id', $purchase->id)->where('id', $line['purchase_item_id'])->first();
            if (! $item) {
                return response()->json(['message' => 'That line is not on '.$purchase->ref.'.'], 422);
            }

            $returnable = $item->qty - $item->returnItems()->sum('qty');
            if ($line['qty'] > $returnable) {
                return response()->json(['message' => 'Only '.$returnable.' of '.$item->name.' can be returned.'], 422);
            }

            if ($item->product && $item->product->manage_stock && ! $item->product->allow_negative_stock) {
                $onHand = (float) ProductStock::where('product_id', $item->product_id)
                    ->where('variant_id', $item->variant_id)
                    ->where('branch_id', $purchase->branch_id)
                    ->value('qty');
                if ($line['qty'] > $onHand) {
                    return response()->json(['message' => 'Only '.$onHand.' of '.$item->name.' is left at this branch.'], 422);
                }
            }

            $unitCost = round($item->cost * (1 - $item->discount / 100), 2);
            $credit = round($line['qty'] * $unitCost, 2);
            $subtotal += $credit;

            $rows[] = ['item' => $item, 'qty' => $line['qty'], 'cost' => $unitCost, 'credit' => $credit, 'reason' => $line['reason']];
        }

        $tax = round($subtotal * $purchase->tax_rate / 100, 2);
        $total = round($subtotal + $tax, 2);
        $userName = $request->user()->name;

        $return = DB::transaction(function () use ($data, $purchase, $rows, $subtotal, $tax, $total, $userName) {
            $return = PurchaseReturn::create([
                'ref' => $this->nextRef(),
                'date' => $data['date'],
                'purchase_id' => $purchase->id,
                'purchase_ref' => $purchase->ref,
                'supplier_id' => $purchase->supplier_id,
                'branch_id' => $purchase->branch_id,
                'settlement' => $data['settlement'],
                'status' => $data['settlement'] === 'Refund' ? 'Awaiting refund' : 'Credited',
                'subtotal' => round($subtotal, 2),
                'tax' => $tax,
                'credit' => $total,
                'notes' => $data['notes'] ?? null,
                'user_name' => $userName,
                'user_id' => (string) request()->user()->id,
            ]);

            foreach ($rows as $row) {
                $item = $row['item'];
                $return->items()->create([
                    'purchase_item_id' => $item->id,
                    'name' => $item->name,
                    'qty' => $row['qty'],
                    'cost' => $row['cost'],
                    'reason' => $row['reason'],
                    'credit' => $row['credit'],
                ]);

                if ($item->product && $item->product->manage_stock) {
                    StockMovement::record($item->product_id, $item->variant_id, $purchase->branch_id, -$row['qty'], 'Purchase return', $return->ref, $row['reason'], $userName);
                }
            }

            // The credit always lowers what we owe this supplier.
            $supplier = $purchase->supplier;
            $supplier->balance = round($supplier->balance + $total, 2);

            $toSupplierCredit = 0;
            if ($data['settlement'] === 'Reduced owed') {
                $applied = min($total, $purchase->due);
                $purchase->credit_applied = round($purchase->credit_applied + $applied, 2);
                $purchase->updateDue();
                $toSupplierCredit = round($total - $applied, 2);
            } elseif ($data['settlement'] === 'Supplier credit') {
                $toSupplierCredit = $total;
            }
            $supplier->advance_balance = round($supplier->advance_balance + $toSupplierCredit, 2);
            $supplier->save();

            return $return;
        });

        return response()->json($return->fresh()->load('supplier:id,name', 'items'), 201);
    }

    /** "PR-0018" gives "PR-0019". */
    private function nextRef(): string
    {
        $max = 0;
        foreach (PurchaseReturn::where('ref', 'like', 'PR-%')->pluck('ref') as $ref) {
            $number = substr($ref, 3);
            if (ctype_digit($number) && (int) $number > $max) {
                $max = (int) $number;
            }
        }

        return 'PR-'.str_pad((string) ($max + 1), 4, '0', STR_PAD_LEFT);
    }
}
