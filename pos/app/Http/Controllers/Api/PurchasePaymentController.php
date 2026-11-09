<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Purchase;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;

/** Paying a supplier for a purchase. */
#[Group('Purchases', weight: 10)]
class PurchasePaymentController extends Controller
{
    /**
     * Add payment
     *
     * Takes the money out of the payment account and lowers what is due. It cannot be more than what is due.
     * Needs `purchases.edit` (a user with a branch: only that branch).
     */
    public function store(Request $request, Purchase $purchase)
    {
        $userBranch = $request->user()->branch_id;
        if ($userBranch && (int) $userBranch !== $purchase->branch_id) {
            return response()->json(['message' => 'You can only work with purchases of your own branch.'], 403);
        }

        $data = $request->validate([
            'amount' => 'required|numeric|gt:0',
            'payment_account_id' => 'required|exists:payment_accounts,id',
            'date' => 'required|date',
            'note' => 'nullable|string|max:190',
        ]);

        if (round($data['amount'], 2) > $purchase->due) {
            return response()->json(['message' => 'Only '.number_format($purchase->due, 2).' is due on '.$purchase->ref.'.'], 422);
        }

        $purchase->addPayment(round($data['amount'], 2), $data['payment_account_id'], $data['date'], $data['note'] ?? null, $request->user()->name);

        return $purchase->fresh()->load('payments');
    }
}
