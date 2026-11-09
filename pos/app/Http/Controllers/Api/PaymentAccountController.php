<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Expense;
use App\Models\PaymentAccount;
use App\Models\SalePayment;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Payment Accounts screen. Each account belongs to a branch, or to the whole business (branch_id null).
 * A user with a branch only sees and uses their branch's accounts and the whole-business ones.
 */
#[Group('Payment accounts', 'Cash drawers, bank accounts, cards and wallets, per branch.', weight: 40)]
class PaymentAccountController extends Controller
{
    /**
     * List accounts
     *
     * Every account with its branch name and money in/out totals (without the transactions).
     * `branch_id` shows one branch's accounts plus the whole-business ones; `branch_id=0` only the whole-business ones.
     * Also `branches` (for the filter and the form; empty for a user with a branch, who only has their own).
     */
    #[QueryParameter('branch_id', 'One branch (and the whole-business accounts). 0 = whole-business only.', type: 'int')]
    public function index(Request $request)
    {
        $request->validate(['branch_id' => 'nullable|integer']);

        $query = PaymentAccount::with('branch:id,name')->orderBy('created_at');
        $branchId = $request->user()->branch_id ?? ($request->filled('branch_id') ? (int) $request->input('branch_id') : null);
        if ($branchId === 0) {
            $query->whereNull('branch_id');
        } elseif ($branchId) {
            $query->where(function ($q) use ($branchId) {
                $q->where('branch_id', $branchId)->orWhereNull('branch_id');
            });
        }

        $rows = [];
        foreach ($query->get() as $account) {
            $rows[] = $this->row($account);
        }

        return [
            'data' => $rows,
            'branches' => Branch::where('active', true)->orderBy('name')->get(['id', 'name']),
            'user_branch_id' => $request->user()->branch_id,
        ];
    }

    /**
     * Show account
     *
     * One account with all its transactions (newest first).
     */
    public function show(Request $request, string $id)
    {
        $account = PaymentAccount::with('branch:id,name')->findOrFail($id);
        if (! $this->canSee($request, $account)) {
            return $this->notYours();
        }

        return $this->row($account) + ['tx' => $account->tx ?? []];
    }

    /**
     * Add account
     *
     * `branch_id` null = the whole business. `opening` is the first balance (for a card: what is owing).
     * Each default in `defs` moves to this account from the other account that had it at the same branch.
     */
    public function store(Request $request)
    {
        $data = $request->validate($this->rules() + [
            'kind' => 'required|in:cash,bank,card,wallet',
            'opening' => 'nullable|numeric|min:0|max:10000000',
        ]);
        $branchId = $this->branchFor($request, $data['branch_id'] ?? null);
        if ($branchId === false) {
            return $this->notYours();
        }
        if ($data['kind'] === 'cash' && ! $branchId) {
            return response()->json(['message' => 'A cash drawer belongs to a branch. Choose the branch.'], 422);
        }

        $account = DB::transaction(function () use ($data, $branchId) {
            $defs = $data['defs'] ?? [];
            $this->takeDefaults($defs, $branchId, null);

            $account = PaymentAccount::create([
                'id' => $this->newId($data['name']),
                'name' => trim($data['name']),
                'kind' => $data['kind'],
                'branch_id' => $branchId,
                'num' => in_array($data['kind'], ['bank', 'card'], true) ? ($data['num'] ?? '') : '',
                'bsb' => $data['kind'] === 'bank' ? ($data['bsb'] ?? '') : '',
                'limit' => $data['kind'] === 'card' ? (float) ($data['limit'] ?? 0) : 0,
                'bal' => 0,
                'active' => true,
                'defs' => $defs,
                'tx' => [],
            ]);

            $opening = round((float) ($data['opening'] ?? 0), 2);
            if ($opening > 0) {
                $amount = $data['kind'] === 'card' ? -$opening : $opening;
                $account->addTransaction($amount, now()->toDateString(), 'deposit', 'Opening balance', '', 'OB');
            }

            return $account;
        });

        return response()->json($this->row($account->load('branch:id,name')), 201);
    }

    /**
     * Edit account
     *
     * Name, branch, number, BSB, limit, defaults, and `active` (an account can only be closed with a zero balance).
     * The kind never changes.
     */
    public function update(Request $request, string $id)
    {
        $account = PaymentAccount::findOrFail($id);
        if (! $this->canSee($request, $account)) {
            return $this->notYours();
        }
        $data = $request->validate($this->rules() + ['active' => 'required|boolean']);
        $branchId = $this->branchFor($request, $data['branch_id'] ?? null);
        if ($branchId === false) {
            return $this->notYours();
        }
        if ($account->kind === 'cash' && ! $branchId) {
            return response()->json(['message' => 'A cash drawer belongs to a branch. Choose the branch.'], 422);
        }
        if ($account->active && ! $data['active'] && abs($account->bal) > 0.005) {
            return response()->json(['message' => 'Move the '.number_format($account->bal, 2).' balance out before closing this account.'], 422);
        }

        DB::transaction(function () use ($account, $data, $branchId) {
            $defs = $data['active'] ? ($data['defs'] ?? []) : [];
            $this->takeDefaults($defs, $branchId, $account->id);

            $account->update([
                'name' => trim($data['name']),
                'branch_id' => $branchId,
                'num' => in_array($account->kind, ['bank', 'card'], true) ? ($data['num'] ?? '') : '',
                'bsb' => $account->kind === 'bank' ? ($data['bsb'] ?? '') : '',
                'limit' => $account->kind === 'card' ? (float) ($data['limit'] ?? 0) : 0,
                'active' => $data['active'],
                'defs' => $defs,
            ]);
        });

        return $this->row($account->fresh('branch:id,name'));
    }

    /**
     * Delete account
     *
     * Only an account nothing has been paid from or into. Otherwise close it instead.
     */
    public function destroy(Request $request, string $id)
    {
        $account = PaymentAccount::findOrFail($id);
        if (! $this->canSee($request, $account)) {
            return $this->notYours();
        }
        if (Expense::withTrashed()->where('payment_account_id', $id)->exists()) {
            return response()->json(['message' => 'This account has expenses, so it cannot be deleted. Switch it off instead.'], 422);
        }
        if (SalePayment::where('payment_account_id', $id)->exists()) {
            return response()->json(['message' => 'This account has sale payments, so it cannot be deleted. Switch it off instead.'], 422);
        }
        $account->delete();

        return response()->noContent();
    }

    /**
     * Transfer funds
     *
     * Moves money between two accounts (e.g. banking a branch's cash drawer). Not income or expense.
     * A bank, cash or wallet account cannot go below zero; a card cannot go over its limit.
     */
    public function transfer(Request $request)
    {
        $data = $request->validate([
            'from' => 'required|string|exists:payment_accounts,id',
            'to' => 'required|string|exists:payment_accounts,id|different:from',
            'amount' => 'required|numeric|gt:0|max:10000000',
            'note' => 'nullable|string|max:190',
        ]);
        $user = $request->user();

        $result = DB::transaction(function () use ($data, $request, $user) {
            $from = PaymentAccount::with('branch:id,name')->lockForUpdate()->find($data['from']);
            $to = PaymentAccount::with('branch:id,name')->lockForUpdate()->find($data['to']);
            $amount = round($data['amount'], 2);

            if (! $this->canSee($request, $from) || ! $this->canSee($request, $to)) {
                return 'You can only move money between your branch\'s accounts and the whole-business ones.';
            }
            if (! $from->active || ! $to->active) {
                return 'Both accounts must be open.';
            }
            if ($from->kind !== 'card' && $amount > $from->bal + 0.005) {
                return $from->name.' only has '.number_format($from->bal, 2).'.';
            }
            if ($from->kind === 'card' && $from->limit > 0 && -$from->bal + $amount > $from->limit + 0.005) {
                return 'That would go over the '.number_format($from->limit, 2).' card limit.';
            }
            if ($to->kind === 'card' && $amount > -$to->bal + 0.005) {
                return 'That is more than the '.number_format(-$to->bal, 2).' owing on '.$to->name.'.';
            }

            $ref = 'TF-'.now()->format('ymd-His');
            $note = $data['note'] ?? '';
            $from->addTransaction(-$amount, now()->toDateString(), 'xout', 'To '.$this->label($to), $note ?: $user->name, $ref);
            $to->addTransaction($amount, now()->toDateString(), $to->kind === 'card' ? 'payment' : 'xin', 'From '.$this->label($from), $note ?: $user->name, $ref);

            return ['ref' => $ref, 'amount' => $amount, 'from' => $this->row($from), 'to' => $this->row($to)];
        });

        if (is_string($result)) {
            return response()->json(['message' => $result], 422);
        }

        return $result;
    }

    // ---------------------------------------------------------------------

    private function rules(): array
    {
        return [
            'name' => 'required|string|max:80',
            'branch_id' => 'nullable|integer|exists:branches,id',
            'num' => 'nullable|string|max:40',
            'bsb' => ['nullable', 'string', 'regex:/^\d{3}-?\d{3}$/'],
            'limit' => 'nullable|numeric|min:0|max:10000000',
            'defs' => 'nullable|array',
            'defs.*' => 'in:'.implode(',', PaymentAccount::DEFAULTS),
        ];
    }

    /** The account as the list shows it: with its branch name, and money in/out from its transactions. */
    private function row(PaymentAccount $account): array
    {
        $in = 0;
        $out = 0;
        foreach ($account->tx ?? [] as $t) {
            if ($t['amt'] > 0) {
                $in += $t['amt'];
            } else {
                $out -= $t['amt'];
            }
        }

        return [
            'id' => $account->id,
            'name' => $account->name,
            'kind' => $account->kind,
            'branch_id' => $account->branch_id,
            'branch_name' => $account->branch ? $account->branch->name : null,
            'num' => $account->num,
            'bsb' => $account->bsb,
            'limit' => $account->limit,
            'bal' => $account->bal,
            'active' => $account->active,
            'defs' => $account->defs ?? [],
            'tx_count' => count($account->tx ?? []),
            'money_in' => round($in, 2),
            'money_out' => round($out, 2),
        ];
    }

    private function label(PaymentAccount $account): string
    {
        return $account->name.($account->branch ? ' · '.$account->branch->name : '');
    }

    /** A default (e.g. "expenses") is held by one account per branch: take it off the others at this branch. */
    private function takeDefaults(array $defs, ?int $branchId, ?string $exceptId): void
    {
        if (! $defs) {
            return;
        }
        $others = PaymentAccount::where('branch_id', $branchId)->when($exceptId, function ($q) use ($exceptId) {
            $q->where('id', '!=', $exceptId);
        })->lockForUpdate()->get();
        foreach ($others as $other) {
            $left = array_values(array_diff($other->defs ?? [], $defs));
            if (count($left) !== count($other->defs ?? [])) {
                $other->defs = $left;
                $other->save();
            }
        }
    }

    /** A short readable id from the name, e.g. "cba-business-x7k2". */
    private function newId(string $name): string
    {
        do {
            $id = Str::slug(Str::limit($name, 24, '')).'-'.Str::lower(Str::random(4));
        } while (PaymentAccount::whereKey($id)->exists());

        return $id;
    }

    /** The branch to save on the account: a user with a branch can only use their own (or none = whole business). */
    private function branchFor(Request $request, $branchId)
    {
        $userBranch = $request->user()->branch_id;
        $branchId = $branchId ? (int) $branchId : null;
        if ($userBranch && $branchId && (int) $userBranch !== $branchId) {
            return false;
        }

        return $branchId;
    }

    private function canSee(Request $request, PaymentAccount $account): bool
    {
        $userBranch = $request->user()->branch_id;

        return ! $userBranch || ! $account->branch_id || (int) $account->branch_id === (int) $userBranch;
    }

    private function notYours()
    {
        return response()->json(['message' => 'This account belongs to another branch.'], 403);
    }
}
