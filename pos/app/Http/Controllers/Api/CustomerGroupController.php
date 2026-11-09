<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\CustomerGroup;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;

/**
 * Contacts > Customer groups. A group belongs to one branch, or to all branches (branch_id null).
 * A user with a branch sees their branch's groups and the all-branch ones, unless they have contacts.all-locations.
 */
#[Group('Customer groups', 'A group sets the price group, default discount and payment terms for its customers.', weight: 21)]
class CustomerGroupController extends Controller
{
    /**
     * List customer groups
     *
     * `contacts_count` = contacts in the group, `balance_due` = what those contacts owe us.
     * Needs `contacts.view`.
     */
    public function index(Request $request)
    {
        $request->validate(['branch_id' => 'nullable|integer|exists:branches,id']);
        $branchId = $this->ownBranchOnly($request) ? (int) $request->user()->branch_id : $request->integer('branch_id');

        $groups = CustomerGroup::with('branch:id,name')->withCount('contacts')
            ->when($branchId, fn ($q) => $q->where(fn ($q2) => $q2->where('branch_id', $branchId)->orWhereNull('branch_id')))
            ->orderBy('id')->get();

        foreach ($groups as $group) {
            $group->balance_due = $this->balanceDue($group);
        }

        return $groups;
    }

    /**
     * Add customer group
     *
     * Needs `contacts.create`.
     */
    public function store(Request $request)
    {
        $data = $request->validate($this->rules());
        $data['branch_id'] = $this->branchToSave($request, $data['branch_id'] ?? null);
        if ($this->nameTaken($data['name'], $data['branch_id'], null)) {
            return response()->json(['message' => 'There is already a '.$data['name'].' group here.'], 422);
        }

        $group = CustomerGroup::create($data);

        return response()->json($this->withTotals($group), 201);
    }

    /**
     * Show customer group
     *
     * Needs `contacts.view`.
     */
    public function show(Request $request, CustomerGroup $customerGroup)
    {
        if (! $this->canSee($request, $customerGroup->branch_id)) {
            return $this->notYourBranch();
        }
        return $this->withTotals($customerGroup);
    }

    /**
     * Update customer group
     *
     * Send all fields. Needs `contacts.edit`.
     */
    public function update(Request $request, CustomerGroup $customerGroup)
    {
        if (! $this->canSee($request, $customerGroup->branch_id)) {
            return $this->notYourBranch();
        }
        $data = $request->validate($this->rules());
        $data['branch_id'] = $this->branchToSave($request, $data['branch_id'] ?? null);
        if ($this->nameTaken($data['name'], $data['branch_id'], $customerGroup->id)) {
            return response()->json(['message' => 'There is already a '.$data['name'].' group here.'], 422);
        }
        // Contacts of another branch cannot stay in a group that moves to one branch.
        if ($data['branch_id'] && $customerGroup->contacts()->whereNotNull('branch_id')->where('branch_id', '!=', $data['branch_id'])->exists()) {
            return response()->json(['message' => 'Contacts of other branches are in this group. Move them first.'], 422);
        }

        $customerGroup->update($data);

        return $this->withTotals($customerGroup->fresh());
    }

    /**
     * Delete customer group
     *
     * Refused (422) while contacts are in the group. Needs `contacts.delete`.
     */
    public function destroy(Request $request, CustomerGroup $customerGroup)
    {
        if (! $this->canSee($request, $customerGroup->branch_id)) {
            return $this->notYourBranch();
        }
        $count = $customerGroup->contacts()->count();
        if ($count > 0) {
            return response()->json(['message' => $customerGroup->name.' still has '.$count.' contacts. Move them to another group first.'], 422);
        }

        $customerGroup->delete();

        return response()->noContent();
    }

    /** What the group's contacts owe us (positive balances only). */
    private function balanceDue(CustomerGroup $group): float
    {
        return (float) Contact::where('customer_group_id', $group->id)->where('balance', '>', 0)->sum('balance');
    }

    private function withTotals(CustomerGroup $group): CustomerGroup
    {
        $group->loadCount('contacts');
        $group->load('branch:id,name');
        $group->balance_due = $this->balanceDue($group);

        return $group;
    }

    private function rules(): array
    {
        return [
            'name' => 'required|string|max:120',
            'branch_id' => 'nullable|integer|exists:branches,id',
            'description' => 'nullable|string|max:255',
            'price_group' => 'nullable|string|max:60',
            'discount' => 'required|numeric|min:0|max:100',
            'terms' => 'nullable|string|max:40',
        ];
    }

    /** A group name only has to be different among the groups a branch can see. */
    private function nameTaken(string $name, ?int $branchId, ?int $exceptId): bool
    {
        return CustomerGroup::where('name', $name)
            ->where(fn ($q) => $branchId ? $q->where('branch_id', $branchId)->orWhereNull('branch_id') : $q)
            ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
            ->exists();
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
