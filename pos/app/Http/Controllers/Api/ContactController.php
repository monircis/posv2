<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\CustomerGroup;
use App\Models\Purchase;
use App\Models\Sale;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Contacts: customers, suppliers or both. A contact belongs to one branch, or to all branches (branch_id null).
 * A user with a branch sees their branch's contacts and the all-branch ones, unless they have contacts.all-locations.
 */
#[Group('Contacts', 'Customers and suppliers. balance: + they owe us, - we owe them.', weight: 20)]
class ContactController extends Controller
{
    /**
     * List contacts
     *
     * Contacts with their customer group and branch, sorted by name. `branch_id` shows one branch's
     * contacts plus the all-branch ones. A user limited to their branch always gets theirs.
     */
    public function index(Request $request)
    {
        $request->validate(['branch_id' => 'nullable|integer|exists:branches,id']);
        $branchId = $this->ownBranchOnly($request) ? (int) $request->user()->branch_id : $request->integer('branch_id');

        return Contact::with('customerGroup', 'branch:id,name')
            ->when($branchId, fn ($q) => $q->forBranch($branchId))
            ->orderBy('name')->get();
    }

    /**
     * Add contact
     *
     * Give a business name, a contact name, or both (an individual has no business name).
     * The starting balance is the opening balance (they owe us) minus the advance balance (paid to a supplier).
     * Needs `contacts.create`.
     */
    public function store(Request $request)
    {
        $data = $request->validate($this->rules());
        $data['branch_id'] = $this->branchToSave($request, $data['branch_id'] ?? null);
        $problem = $this->groupProblem($data);
        if ($problem) {
            return response()->json(['message' => $problem], 422);
        }

        // An individual has no business name: use the person's name.
        if (empty($data['name'])) {
            $data['name'] = $data['contact_name'];
        }
        $data['balance'] = ($data['opening_balance'] ?? 0) - ($data['advance_balance'] ?? 0);

        $contact = Contact::create($data);

        return response()->json($contact->fresh()->load('customerGroup', 'branch:id,name'), 201);
    }

    /**
     * Show contact
     *
     * Needs `contacts.view`.
     */
    public function show(Request $request, Contact $contact)
    {
        if (! $this->canSee($request, $contact->branch_id)) {
            return $this->notYourBranch();
        }

        return $contact->load('customerGroup', 'branch:id,name');
    }

    /**
     * Update contact
     *
     * Send all fields. The balance is not changed here (it comes from sales, purchases and payments).
     * A new name is copied to the contact's sales. Needs `contacts.edit`.
     */
    public function update(Request $request, Contact $contact)
    {
        if (! $this->canSee($request, $contact->branch_id)) {
            return $this->notYourBranch();
        }
        $data = $request->validate($this->rules($contact));
        $data['branch_id'] = $this->branchToSave($request, $data['branch_id'] ?? null);
        $problem = $this->groupProblem($data);
        if ($problem) {
            return response()->json(['message' => $problem], 422);
        }

        if (empty($data['name'])) {
            $data['name'] = $data['contact_name'];
        }

        // Sales also keep the customer's name, for the list and the receipt.
        if ($data['name'] !== $contact->name) {
            Sale::where('contact_id', $contact->id)->update(['customer_name' => $data['name']]);
        }

        $contact->update($data);

        return $contact->fresh()->load('customerGroup', 'branch:id,name');
    }

    /**
     * Delete contact
     *
     * Refused (422) while the balance is not zero or the contact has sales or purchases.
     * Needs `contacts.delete`.
     */
    public function destroy(Request $request, Contact $contact)
    {
        if (! $this->canSee($request, $contact->branch_id)) {
            return $this->notYourBranch();
        }
        if ($contact->balance != 0) {
            return response()->json(['message' => $contact->name.' still has a balance. Settle it first.'], 422);
        }
        if (Sale::where('contact_id', $contact->id)->exists() || Purchase::where('supplier_id', $contact->id)->exists()) {
            return response()->json(['message' => $contact->name.' has sales or purchases, so it cannot be deleted.'], 422);
        }

        $contact->delete();

        return response()->noContent();
    }

    /**
     * Contact transactions
     *
     * The contact's sales and purchases, newest first. Needs `contacts.view`.
     */
    public function transactions(Request $request, Contact $contact)
    {
        if (! $this->canSee($request, $contact->branch_id)) {
            return $this->notYourBranch();
        }
        $rows = [];

        foreach (Sale::where('contact_id', $contact->id)->get() as $sale) {
            $rows[] = [
                'ref' => $sale->ref,
                'date' => $sale->sold_at->format('d/m/Y'),
                'kind' => 'Sale',
                'total' => $sale->total,
                'due' => $sale->due,
                'status' => $sale->status,
                'sort' => $sale->created_at,
            ];
        }

        foreach (Purchase::where('supplier_id', $contact->id)->get() as $purchase) {
            $rows[] = [
                'ref' => $purchase->ref,
                'date' => $purchase->date->format('d/m/Y'),
                'kind' => 'Purchase',
                'total' => $purchase->total,
                'due' => $purchase->due,
                'status' => $purchase->payment_status,
                'sort' => $purchase->created_at,
            ];
        }

        // Newest first, then drop the sort helper.
        usort($rows, fn ($a, $b) => $b['sort'] <=> $a['sort']);
        foreach ($rows as $i => $row) {
            unset($rows[$i]['sort']);
        }

        return $rows;
    }

    private function rules(?Contact $contact = null): array
    {
        return [
            'branch_id' => 'nullable|integer|exists:branches,id',
            'type' => 'required|in:Customer,Supplier,Both',
            'name' => ['nullable', 'required_without:contact_name', 'string', 'max:190', Rule::unique('contacts')->ignore($contact)],
            'contact_name' => 'nullable|required_without:name|string|max:190',
            'phone' => 'required|string|max:40',
            'email' => 'nullable|email|max:190',
            'abn' => 'nullable|string|max:20',
            // Customer terms
            'customer_group_id' => 'nullable|exists:customer_groups,id',
            'credit_limit' => 'nullable|numeric|min:0',
            'opening_balance' => 'nullable|numeric',
            // Supplier terms
            'pay_term' => 'nullable|in:On delivery,7 days,14 days,30 days,60 days',
            'advance_balance' => 'nullable|numeric|min:0',
            // Address
            'street' => 'nullable|string|max:190',
            'suburb' => 'nullable|string|max:120',
            'state' => 'nullable|in:NSW,VIC,QLD,WA,SA,TAS,ACT,NT',
            'postcode' => 'nullable|string|max:10',
            'note' => 'nullable|string|max:255',
        ];
    }

    /** The customer group must be for the contact's branch, or for all branches. */
    private function groupProblem(array $data): ?string
    {
        if (empty($data['customer_group_id'])) {
            return null;
        }
        $group = CustomerGroup::find($data['customer_group_id']);
        if ($group && $group->branch_id && $group->branch_id !== $data['branch_id']) {
            return 'The '.$group->name.' group is for another branch.';
        }

        return null;
    }

    // ---- branches ----------------------------------------------------------

    /** A user with a branch only works with their branch (and all-branch records), unless they have contacts.all-locations. */
    private function ownBranchOnly(Request $request): bool
    {
        $user = $request->user();

        return $user->branch_id && ! $user->hasPermission('contacts.all-locations');
    }

    /** Can this user open a record of that branch (null = all branches)? */
    private function canSee(Request $request, $branchId): bool
    {
        return ! $this->ownBranchOnly($request) || ! $branchId || (int) $branchId === (int) $request->user()->branch_id;
    }

    /** The branch to save: a user limited to their branch always saves their own. */
    private function branchToSave(Request $request, $asked): ?int
    {
        if ($this->ownBranchOnly($request)) {
            return (int) $request->user()->branch_id;
        }

        return $asked ? (int) $asked : null;
    }

    private function notYourBranch()
    {
        return response()->json(['message' => 'This belongs to another branch.'], 403);
    }
}
