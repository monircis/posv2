<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\PaymentAccount;
use App\Models\StockSetting;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Expenses: money spent at a branch, paid from a payment account or kept as an unpaid bill.
 * A paid expense changes the account's balance straight away.
 * A user with a branch only sees and changes expenses of that branch.
 */
#[Group('Expenses', 'Paid expenses and unpaid bills. A paid expense changes the payment account balance straight away.', weight: 13)]
class ExpenseController extends Controller
{
    /**
     * List expenses
     *
     * 25 per page, newest first. Also returns `counts` for the tabs, the totals for the current filter,
     * `overdue` bills and `by_category` for the chosen month. Needs `expenses.view`.
     */
    #[QueryParameter('tab', 'paid, unpaid or no_receipt.', type: 'string')]
    #[QueryParameter('search', 'Payee, reference, invoice number or note.', type: 'string')]
    #[QueryParameter('branch_id', 'Only this branch.', type: 'int')]
    #[QueryParameter('category_id', 'Only this category.', type: 'int')]
    #[QueryParameter('account', 'Paid from this payment account.', type: 'string')]
    #[QueryParameter('month', 'The month for by_category, e.g. 2026-09. Default: this month.', type: 'string')]
    public function index(Request $request)
    {
        $query = $this->filtered($request);
        $this->applyTab($query, $request->input('tab'));

        $totalAmount = (float) (clone $query)->sum('amount');
        $totalGst = (float) (clone $query)->sum('gst_amount');

        $page = $query->with('branch:id,name', 'category:id,name,color', 'paymentAccount:id,name')
            ->orderBy('date', 'desc')
            ->orderBy('id', 'desc')
            ->paginate(25);

        $counts = [];
        foreach (['all', 'paid', 'unpaid', 'no_receipt'] as $tab) {
            $countQuery = $this->filtered($request);
            $this->applyTab($countQuery, $tab);
            $counts[$tab] = $countQuery->count();
        }

        return [
            'data' => $page->items(),
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'total' => $page->total(),
            'counts' => $counts,
            'total_amount' => round($totalAmount, 2),
            'total_gst' => round($totalGst, 2),
            'all_count' => $this->visible($request)->count(),
            'unpaid_amount' => round((float) $this->visible($request)->whereNull('payment_account_id')->sum('amount'), 2),
            'overdue' => $this->visible($request)
                ->whereNull('payment_account_id')
                ->where('due_date', '<', now()->toDateString())
                ->orderBy('due_date')
                ->get(['id', 'ref', 'payee', 'amount', 'due_date']),
            'by_category' => $this->byCategory($request),
            'categories' => ExpenseCategory::orderBy('name')->get(),
            'accounts' => $this->accounts(false),
        ];
    }

    /**
     * Form defaults
     *
     * The next reference, the branch the form starts with, the categories and the active payment accounts.
     * Needs `expenses.create` or `expenses.edit`.
     */
    public function formData(Request $request)
    {
        return [
            'next_ref' => $this->nextRef(),
            'branch_id' => $request->user()->branch_id ?? StockSetting::current()->default_branch_id,
            'categories' => ExpenseCategory::where('active', true)->orderBy('name')->get(),
            'accounts' => $this->accounts(true),
        ];
    }

    /**
     * Add expense
     *
     * With `paid` true the amount comes out of `payment_account_id` straight away.
     * With `paid` false it is an unpaid bill with a `due_date`. Needs `expenses.create`.
     */
    public function store(Request $request)
    {
        $data = $this->validated($request);
        $user = $request->user();

        if (! $this->isAtBranch($request, $data['branch_id'])) {
            return $this->notYourBranch();
        }
        if ($data['paid'] && ! PaymentAccount::where('id', $data['payment_account_id'])->where('active', true)->exists()) {
            return response()->json(['message' => 'That payment account is switched off. Pick another one.'], 422);
        }
        if (! ExpenseCategory::where('id', $data['expense_category_id'])->where('active', true)->exists()) {
            return response()->json(['message' => 'That category is switched off. Pick another one.'], 422);
        }

        $expense = DB::transaction(function () use ($data, $user) {
            $expense = Expense::create($this->fields($data) + [
                'ref' => $this->nextRef(),
                'paid_date' => $data['paid'] ? $data['date'] : null,
                'created_by' => $user->name,
                'user_id' => (string) request()->user()->id,
            ]);

            $what = 'Added by '.$user->name;
            if ($data['paid']) {
                $expense->payFrom($data['payment_account_id'], $data['date']);
                $what .= ', paid from '.$this->accountName($data['payment_account_id']);
            } else {
                $what .= ', bill due '.date('d/m/Y', strtotime($data['due_date']));
            }
            $expense->logs()->create(['what' => $what, 'kind' => 'neutral']);

            return $expense;
        });

        return response()->json($this->details($expense), 201);
    }

    /**
     * Show expense
     *
     * With the branch, the payment account and the activity. Needs `expenses.view`
     * (a user with a branch: only that branch).
     */
    public function show(Request $request, Expense $expense)
    {
        if (! $this->isMine($request, $expense, 'expenses')) {
            return $this->notMine();
        }
        if (! $this->isAtBranch($request, $expense->branch_id)) {
            return $this->notYourBranch();
        }

        return $this->details($expense);
    }

    /**
     * Update expense
     *
     * If the amount or the account changes, the old amount goes back into the old account and
     * the new amount comes out of the new one. Needs `expenses.edit` (a user with a branch: only that branch).
     */
    public function update(Request $request, Expense $expense)
    {
        if (! $this->isMine($request, $expense, 'expenses')) {
            return $this->notMine();
        }
        $data = $this->validated($request);
        $user = $request->user();

        if (! $this->isAtBranch($request, $expense->branch_id) || ! $this->isAtBranch($request, $data['branch_id'])) {
            return $this->notYourBranch();
        }

        // An account that was switched off can stay on an expense that already uses it.
        if ($data['paid'] && $data['payment_account_id'] !== $expense->payment_account_id
            && ! PaymentAccount::where('id', $data['payment_account_id'])->where('active', true)->exists()) {
            return response()->json(['message' => 'That payment account is switched off. Pick another one.'], 422);
        }
        // The same goes for a category that was switched off.
        if ((int) $data['expense_category_id'] !== $expense->expense_category_id
            && ! ExpenseCategory::where('id', $data['expense_category_id'])->where('active', true)->exists()) {
            return response()->json(['message' => 'That category is switched off. Pick another one.'], 422);
        }

        $result = DB::transaction(function () use ($data, $expense, $user) {
            // Locking the row stops two changes at the same moment moving the money twice.
            $locked = Expense::lockForUpdate()->find($expense->id);
            if (! $locked) {
                return $expense->ref.' has been deleted.';
            }

            $oldAccount = $locked->payment_account_id;
            $oldAmount = $locked->amount;
            $newAccount = $data['paid'] ? $data['payment_account_id'] : null;

            $paidDate = null;
            if ($newAccount) {
                $paidDate = $oldAccount && $locked->paid_date ? $locked->paid_date->toDateString() : now()->toDateString();
            }

            $locked->fill($this->fields($data) + ['paid_date' => $paidDate]);
            $locked->save();

            if ($oldAccount !== $newAccount || $oldAmount != $locked->amount) {
                if ($oldAccount) {
                    $locked->refundTo($oldAccount, $oldAmount, now()->toDateString());
                }
                if ($newAccount) {
                    $locked->payFrom($newAccount, $paidDate);
                }
            }

            $locked->logs()->create(['what' => 'Edited by '.$user->name, 'kind' => 'info']);

            return $locked;
        });

        if (is_string($result)) {
            return response()->json(['message' => $result], 422);
        }

        return $this->details($result);
    }

    /**
     * Mark as paid
     *
     * Takes the amount out of the payment account. Needs `expenses.edit` (a user with a branch: only that branch).
     */
    public function pay(Request $request, Expense $expense)
    {
        if (! $this->isMine($request, $expense, 'expenses')) {
            return $this->notMine();
        }
        if (! $this->isAtBranch($request, $expense->branch_id)) {
            return $this->notYourBranch();
        }

        $data = $request->validate(['payment_account_id' => 'required|exists:payment_accounts,id']);
        if (! PaymentAccount::where('id', $data['payment_account_id'])->where('active', true)->exists()) {
            return response()->json(['message' => 'That payment account is switched off. Pick another one.'], 422);
        }

        $userName = $request->user()->name;

        $result = DB::transaction(function () use ($data, $expense, $userName) {
            $locked = Expense::lockForUpdate()->find($expense->id);
            if (! $locked) {
                return $expense->ref.' has been deleted.';
            }
            if ($locked->payment_account_id) {
                return $locked->ref.' is already paid.';
            }

            $today = now()->toDateString();
            $locked->payment_account_id = $data['payment_account_id'];
            $locked->paid_date = $today;
            $locked->due_date = null;
            $locked->save();

            $locked->payFrom($data['payment_account_id'], $today);
            $locked->logs()->create(['what' => 'Paid from '.$this->accountName($data['payment_account_id']).' by '.$userName, 'kind' => 'success']);

            return $locked;
        });

        if (is_string($result)) {
            return response()->json(['message' => $result], 422);
        }

        return $this->details($result);
    }

    /**
     * Delete expense
     *
     * A paid expense's amount goes back into its account. The expense is hidden, not erased.
     * Needs `expenses.delete` (a user with a branch: only that branch).
     */
    public function destroy(Request $request, Expense $expense)
    {
        if (! $this->isMine($request, $expense, 'expenses')) {
            return $this->notMine();
        }
        if (! $this->isAtBranch($request, $expense->branch_id)) {
            return $this->notYourBranch();
        }

        $userName = $request->user()->name;

        DB::transaction(function () use ($expense, $userName) {
            $locked = Expense::lockForUpdate()->find($expense->id);
            if (! $locked) {
                return;
            }

            if ($locked->payment_account_id) {
                $locked->refundTo($locked->payment_account_id, $locked->amount, now()->toDateString());
            }
            $locked->logs()->create(['what' => 'Deleted by '.$userName, 'kind' => 'info']);
            $locked->delete();
        });

        return response()->noContent();
    }

    /**
     * Upload receipt
     *
     * Send the file in the "file" form field (PDF, PNG or JPG, up to 10 MB). The old file is deleted.
     * Needs `expenses.create` or `expenses.edit`.
     */
    public function uploadReceipt(Request $request, Expense $expense)
    {
        if (! $this->isMine($request, $expense, 'expenses')) {
            return $this->notMine();
        }
        if (! $this->isAtBranch($request, $expense->branch_id)) {
            return $this->notYourBranch();
        }
        $request->validate(['file' => 'required|file|mimes:pdf,png,jpg,jpeg|max:10240']);

        if ($expense->receipt_file) {
            Storage::disk('public')->delete($expense->receipt_file);
        }
        $file = $request->file('file');
        $expense->receipt_file = $file->store('expenses', 'public');
        $expense->receipt_name = $file->getClientOriginalName();
        $expense->save();
        $expense->logs()->create(['what' => 'Receipt attached by '.$request->user()->name, 'kind' => 'info']);

        return $this->details($expense);
    }

    /**
     * Remove receipt
     *
     * Needs `expenses.create` or `expenses.edit`.
     */
    public function deleteReceipt(Request $request, Expense $expense)
    {
        if (! $this->isMine($request, $expense, 'expenses')) {
            return $this->notMine();
        }
        if (! $this->isAtBranch($request, $expense->branch_id)) {
            return $this->notYourBranch();
        }

        if ($expense->receipt_file) {
            Storage::disk('public')->delete($expense->receipt_file);
            $expense->receipt_file = null;
            $expense->receipt_name = null;
            $expense->save();
            $expense->logs()->create(['what' => 'Receipt removed by '.$request->user()->name, 'kind' => 'info']);
        }

        return $this->details($expense);
    }

    // ---------------------------------------------------------------------

    /** The expense with everything the details page and the edit form need. */
    private function details(Expense $expense): array
    {
        $expense->load('branch:id,name', 'category:id,name,color,active', 'paymentAccount:id,name,kind,branch_id,num,limit,bal,active,defs', 'logs');

        $row = $expense->toArray();
        $row['logs'] = $expense->logs->sortByDesc('id')->values();

        return $row;
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'date' => 'required|date',
            'branch_id' => 'required|exists:branches,id',
            'expense_category_id' => 'required|exists:expense_categories,id',
            'payee' => 'nullable|string|max:190',
            'amount' => 'required|numeric|gt:0|max:9999999999',
            'has_gst' => 'required|boolean',
            'paid' => 'required|boolean',
            'payment_account_id' => 'required_if:paid,true,1|nullable|exists:payment_accounts,id',
            'due_date' => 'required_if:paid,false,0|nullable|date',
            'invoice_no' => 'nullable|string|max:190',
            'notes' => 'nullable|string|max:1000',
        ]);
    }

    /** The columns an expense takes from the form. GST is one eleventh of an amount that includes it. */
    private function fields(array $data): array
    {
        $amount = round($data['amount'], 2);
        $payee = trim((string) ($data['payee'] ?? ''));

        return [
            'date' => $data['date'],
            'branch_id' => $data['branch_id'],
            'expense_category_id' => $data['expense_category_id'],
            'payee' => $payee !== '' ? $payee : ExpenseCategory::where('id', $data['expense_category_id'])->value('name'),
            'amount' => $amount,
            'has_gst' => $data['has_gst'],
            'gst_amount' => $data['has_gst'] ? round($amount / 11, 2) : 0,
            'payment_account_id' => $data['paid'] ? $data['payment_account_id'] : null,
            'due_date' => $data['paid'] ? null : $data['due_date'],
            'invoice_no' => $data['invoice_no'] ?? null,
            'notes' => $data['notes'] ?? null,
        ];
    }

    /** Expenses this user may see, with the search filters. */
    private function filtered(Request $request)
    {
        $query = $this->visible($request);

        $search = trim((string) $request->input('search'));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('payee', 'like', "%$search%")
                    ->orWhere('ref', 'like', "%$search%")
                    ->orWhere('invoice_no', 'like', "%$search%")
                    ->orWhere('notes', 'like', "%$search%");
            });
        }

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->input('branch_id'));
        }
        if ($request->filled('category_id')) {
            $query->where('expense_category_id', $request->input('category_id'));
        }
        if ($request->filled('account')) {
            $query->where('payment_account_id', $request->input('account'));
        }

        return $query;
    }

    private function applyTab($query, ?string $tab): void
    {
        if ($tab === 'paid') {
            $query->whereNotNull('payment_account_id');
        } elseif ($tab === 'unpaid') {
            $query->whereNull('payment_account_id');
        } elseif ($tab === 'no_receipt') {
            $query->whereNull('receipt_file');
        }
    }

    /** Expenses at the user's own branch, or all of them for a user with no branch. */
    private function visible(Request $request)
    {
        $query = Expense::query();
        $this->onlyMine($query, $request, 'expenses');

        $userBranch = $request->user()->branch_id;
        if ($userBranch) {
            $query->where('branch_id', $userBranch);
        }

        return $query;
    }

    /** The chosen month's spending per category, biggest first. */
    private function byCategory(Request $request): array
    {
        $month = (string) $request->input('month');
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            $month = now()->format('Y-m');
        }

        $totals = $this->visible($request)
            ->where('date', '>=', $month.'-01')
            ->where('date', '<=', date('Y-m-t', strtotime($month.'-01')))
            ->groupBy('expense_category_id')
            ->selectRaw('expense_category_id, sum(amount) as value')
            ->orderByDesc('value')
            ->with('category:id,name,color')
            ->get();

        $rows = [];
        foreach ($totals as $total) {
            $rows[] = [
                'id' => $total->expense_category_id,
                'name' => $total->category->name,
                'color' => $total->category->color,
                'value' => round((float) $total->value, 2),
            ];
        }

        return ['month' => $month, 'total' => round(array_sum(array_column($rows, 'value')), 2), 'rows' => $rows];
    }

    /** Payment accounts for the filter and the form, with the name of the branch each belongs to (null = whole business). */
    private function accounts(bool $activeOnly): array
    {
        $query = PaymentAccount::orderBy('name');
        if ($activeOnly) {
            $query->where('active', true);
        }

        $branchNames = Branch::pluck('name', 'id');

        $rows = [];
        foreach ($query->get(['id', 'name', 'kind', 'branch_id', 'num', 'limit', 'bal', 'active', 'defs']) as $account) {
            $row = $account->toArray();
            $row['branch_name'] = $branchNames[$account->branch_id] ?? null;
            $rows[] = $row;
        }

        return $rows;
    }

    private function accountName(string $id): string
    {
        return (string) PaymentAccount::where('id', $id)->value('name');
    }

    /** A user with no branch works everywhere; otherwise only at their own branch. */
    private function isAtBranch(Request $request, int $branchId): bool
    {
        $userBranch = $request->user()->branch_id;

        return ! $userBranch || (int) $userBranch === $branchId;
    }

    private function notYourBranch()
    {
        return response()->json(['message' => 'You can only work with expenses of your own branch.'], 403);
    }

    /** "EX-1092" gives "EX-1093". Deleted expenses count too, so a reference is never used twice. */
    private function nextRef(): string
    {
        $max = 1000;
        foreach (Expense::withTrashed()->where('ref', 'like', 'EX-%')->pluck('ref') as $ref) {
            $number = substr($ref, 3);
            if (ctype_digit($number) && (int) $number > $max) {
                $max = (int) $number;
            }
        }

        return 'EX-'.($max + 1);
    }
}
