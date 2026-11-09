<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CoinTransaction;
use App\Models\Contact;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * A customer's loyalty coins. Sales earn and spend them (see SaleController);
 * here you can see the history and add or take off coins by hand.
 */
#[Group('Contacts', weight: 20)]
class CoinController extends Controller
{
    /**
     * Coin history
     *
     * The customer's coin balance and every change, newest first. Needs `contacts.view`.
     */
    public function index(Request $request, Contact $contact)
    {
        if (! $this->canSee($request, $contact)) {
            return response()->json(['message' => 'This belongs to another branch.'], 403);
        }
        $rows = CoinTransaction::with('sale:id,ref')
            ->where('contact_id', $contact->id)
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        return [
            'coins' => $contact->coins,
            'transactions' => $rows,
        ];
    }

    /**
     * Adjust coins
     *
     * Add coins (e.g. a birthday gift) or take them off (e.g. a mistake). `change` is + or -.
     * The balance cannot go below 0 this way. A note is needed. Needs `contacts.edit`.
     */
    public function adjust(Request $request, Contact $contact)
    {
        if (! $this->canSee($request, $contact)) {
            return response()->json(['message' => 'This belongs to another branch.'], 403);
        }
        $data = $request->validate([
            'change' => 'required|integer|not_in:0|between:-1000000,1000000',
            'note' => 'required|string|max:190',
        ]);
        $userName = $request->user()->name;

        $result = DB::transaction(function () use ($data, $contact, $userName) {
            $locked = Contact::lockForUpdate()->find($contact->id);

            if ($locked->type === 'Supplier') {
                return $locked->name.' is a supplier. Only customers have coins.';
            }
            if ($locked->coins + $data['change'] < 0) {
                return $locked->name.' only has '.$locked->coins.' coins.';
            }

            CoinTransaction::record($locked, (int) $data['change'], 'Adjusted', null, $data['note'], $userName);

            return $locked;
        });

        if (is_string($result)) {
            return response()->json(['message' => $result], 422);
        }

        return $this->index($result);
    }

    /** A user limited to their branch only sees their branch's contacts and the all-branch ones. */
    private function canSee(Request $request, Contact $contact): bool
    {
        $user = $request->user();
        if (! $user->branch_id || $user->hasPermission('contacts.all-locations')) {
            return true;
        }

        return $contact->isAtBranch((int) $user->branch_id);
    }
}
