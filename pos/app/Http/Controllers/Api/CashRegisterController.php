<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\CashRegister;
use App\Models\PaymentAccount;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Cash register shifts on the POS: count the cash to start, track the drawer, count it again to close.
 */
#[Group('Cash register', 'Opening cash, cash in and out, and closing count on the POS.', weight: 16)]
class CashRegisterController extends Controller
{
    /**
     * My register
     *
     * The register this user has open at the branch (null if none) with its totals and latest moves.
     * `suggested_opening` is the cash the last shift at this branch left in the drawer, to start the count from.
     * Needs `sell.view`.
     */
    public function current(Request $request)
    {
        $data = $request->validate(['branch_id' => 'required|integer|exists:branches,id']);
        $branchId = (int) $data['branch_id'];
        if (! $this->isAtBranch($request, $branchId)) {
            return $this->notYourBranch();
        }

        // A register from an earlier day is closed here, so a new day needs a new opening count.
        $register = CashRegister::openFor($request->user()->id, $branchId);
        if ($register) {
            $register->load('branch');
        }

        $last = CashRegister::where('branch_id', $branchId)->whereNotNull('closed_at')->orderByDesc('closed_at')->first();

        return [
            'register' => $register ? $register->summary() : null,
            'moves' => $register ? $register->moves()->orderByDesc('id')->limit(50)->get(['id', 'type', 'amount', 'ref', 'note', 'user_name', 'created_at']) : [],
            'today' => CashRegister::shopToday(),
            'suggested_opening' => $last ? (float) $last->kept_cash : 0,
            'last_close' => $last ? [
                'user_name' => $last->user_name,
                'closed_at' => $last->closed_at,
                'counted_cash' => $last->counted_cash,
                'kept_cash' => $last->kept_cash,
                'expected_cash' => $last->expected_cash,
                'shop_day' => $last->shopDay(),
                'auto_closed' => $last->counted_cash === null,
            ] : null,
        ];
    }

    /**
     * My open registers
     *
     * This user's registers open today, at any branch, for the dashboard's Close register button.
     * Registers left open from an earlier day are closed first (without a count). Needs `sell.view`.
     */
    public function mine(Request $request)
    {
        $rows = [];
        foreach (CashRegister::openForUser($request->user()->id) as $register) {
            $rows[] = $register->summary();
        }

        return ['data' => $rows, 'today' => CashRegister::shopToday()];
    }

    /**
     * Open register
     *
     * Starts a shift with the cash counted in the drawer. One open register per user per branch.
     * Needs `sell.create`.
     */
    public function open(Request $request)
    {
        $data = $request->validate([
            'branch_id' => 'required|integer|exists:branches,id',
            'opening_cash' => 'required|numeric|min:0|max:100000',
            'note' => 'nullable|string|max:190',
        ]);
        $branchId = (int) $data['branch_id'];
        if (! $this->isAtBranch($request, $branchId)) {
            return $this->notYourBranch();
        }
        $user = $request->user();

        if (CashRegister::openFor($user->id, $branchId)) {
            return response()->json(['message' => 'You already have a register open at this branch.'], 422);
        }

        // The count is different from what the last shift left: say so in the note, so a manager can see it.
        $note = $data['note'] ?? null;
        $last = CashRegister::where('branch_id', $branchId)->whereNotNull('closed_at')->orderByDesc('closed_at')->first();
        if ($last && $last->kept_cash !== null && abs($last->kept_cash - $data['opening_cash']) > 0.005 && ! $note) {
            return response()->json(['message' => 'The last shift left $'.number_format($last->kept_cash, 2).' in the drawer. Add a note to say why your count is different.'], 422);
        }

        $register = CashRegister::create([
            'branch_id' => $branchId,
            'user_id' => (string) $user->id,
            'user_name' => $user->name,
            'opened_at' => now(),
            'opening_cash' => round($data['opening_cash'], 2),
            'opening_note' => $note,
        ]);

        return response()->json(['register' => $register->load('branch')->summary(), 'moves' => []], 201);
    }

    /**
     * Cash in or out
     *
     * Cash put into the drawer (e.g. more change) or taken out (e.g. paying a supplier, a bank run).
     * It is also added to the branch's cash drawer account. Needs `sell.create`.
     */
    public function move(Request $request, CashRegister $register)
    {
        if (! $this->isMine($request, $register, 'sell')) {
            return $this->notMine();
        }
        $data = $request->validate([
            'type' => 'required|in:cash_in,cash_out',
            'amount' => 'required|numeric|gt:0|max:100000',
            'note' => 'required|string|max:190',
        ]);
        $problem = $this->cannotUse($request, $register);
        if ($problem) {
            return response()->json(['message' => $problem], 422);
        }
        // A register is for one day: cash in or out after the day has ended belongs to a new register.
        if ($register->isFromEarlierDay()) {
            $register->closeAtDayEnd();

            return response()->json(['message' => 'This register was for '.$register->shopDay().'. Open a new one for today.'], 422);
        }
        $user = $request->user();
        $amount = round($data['amount'], 2);

        if ($data['type'] === 'cash_out' && $amount > $register->expected() + 0.005) {
            return response()->json(['message' => 'There should only be $'.number_format($register->expected(), 2).' in the drawer.'], 422);
        }

        DB::transaction(function () use ($register, $data, $amount, $user) {
            $signed = $data['type'] === 'cash_in' ? $amount : -$amount;
            $register->moves()->create([
                'type' => $data['type'],
                'amount' => $signed,
                'note' => $data['note'],
                'user_name' => $user->name,
            ]);

            $account = $this->drawerAccount($register->branch_id);
            if ($account) {
                $account->addTransaction($signed, now()->toDateString(), $data['type'] === 'cash_in' ? 'cash in' : 'cash out', $data['note'], $user->name, 'Register #'.$register->id);
            }
        });

        return [
            'register' => $register->load('branch')->summary(),
            'moves' => $register->moves()->orderByDesc('id')->limit(50)->get(['id', 'type', 'amount', 'ref', 'note', 'user_name', 'created_at']),
        ];
    }

    /**
     * Close register
     *
     * The cash counted in the drawer at the end of the shift. `kept_cash` is left in the drawer for the
     * next shift (the rest is banked). Over or short is added to the branch's cash drawer account, so the
     * account matches the drawer. Needs `sell.create`.
     */
    public function close(Request $request, CashRegister $register)
    {
        if (! $this->isMine($request, $register, 'sell')) {
            return $this->notMine();
        }
        $data = $request->validate([
            'counted_cash' => 'required|numeric|min:0|max:100000',
            'kept_cash' => 'required|numeric|min:0|max:100000',
            'note' => 'nullable|string|max:190',
        ]);
        $problem = $this->cannotUse($request, $register);
        if ($problem) {
            return response()->json(['message' => $problem], 422);
        }
        if ($data['kept_cash'] > $data['counted_cash'] + 0.005) {
            return response()->json(['message' => 'You cannot leave more in the drawer than you counted.'], 422);
        }

        $user = $request->user();
        $expected = $register->expected();
        $counted = round($data['counted_cash'], 2);
        $difference = round($counted - $expected, 2);

        if (abs($difference) > 0.005 && empty($data['note'])) {
            return response()->json(['message' => 'The drawer is '.($difference > 0 ? 'over' : 'short').' by $'.number_format(abs($difference), 2).'. Add a note to close.'], 422);
        }

        DB::transaction(function () use ($register, $data, $user, $expected, $counted, $difference) {
            $register->update([
                'closed_at' => now(),
                'closed_by' => $user->name,
                'expected_cash' => $expected,
                'counted_cash' => $counted,
                'difference' => $difference,
                'kept_cash' => round($data['kept_cash'], 2),
                'close_note' => $data['note'] ?? null,
            ]);

            if (abs($difference) > 0.005) {
                $account = $this->drawerAccount($register->branch_id);
                if ($account) {
                    $account->addTransaction($difference, now()->toDateString(), 'adjustment', 'Register '.($difference > 0 ? 'over' : 'short'), $register->user_name, 'Register #'.$register->id);
                }
            }
        });

        return ['register' => $register->fresh('branch')->summary()];
    }

    /**
     * Register history
     *
     * Shifts, newest first, for Sell > Registers. Filters: `branch_id`, `from`, `to` (dates), `status` (open, closed).
     * A user with a branch only sees that branch. Needs `sell.view`.
     */
    public function index(Request $request)
    {
        $request->validate([
            'branch_id' => 'nullable|integer',
            'from' => 'nullable|date',
            'to' => 'nullable|date',
            'status' => 'nullable|in:open,closed',
        ]);

        $query = CashRegister::with('branch')->orderByDesc('opened_at');
        $this->onlyMine($query, $request, 'sell');
        $userBranch = $request->user()->branch_id;
        if ($userBranch) {
            $query->where('branch_id', $userBranch);
        } elseif ($request->filled('branch_id')) {
            $query->where('branch_id', $request->input('branch_id'));
        }
        if ($request->filled('from')) {
            $query->whereDate('opened_at', '>=', $request->input('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('opened_at', '<=', $request->input('to'));
        }
        if ($request->input('status') === 'open') {
            $query->whereNull('closed_at');
        } elseif ($request->input('status') === 'closed') {
            $query->whereNotNull('closed_at');
        }

        $rows = [];
        foreach ($query->limit(200)->get() as $register) {
            $rows[] = $register->summary();
        }

        return [
            'data' => $rows,
            'branches' => $userBranch ? [] : Branch::where('active', true)->orderBy('name')->get(['id', 'name']),
        ];
    }

    /**
     * Register moves
     *
     * Every cash move of one shift, oldest first. Needs `sell.view`.
     */
    public function show(Request $request, CashRegister $register)
    {
        if (! $this->isMine($request, $register, 'sell')) {
            return $this->notMine();
        }
        if (! $this->isAtBranch($request, $register->branch_id)) {
            return $this->notYourBranch();
        }

        return [
            'register' => $register->load('branch')->summary(),
            'moves' => $register->moves()->orderBy('id')->get(['id', 'type', 'amount', 'ref', 'note', 'user_name', 'created_at']),
        ];
    }

    // ---------------------------------------------------------------------

    /** Only the cashier who opened it, or a user who can edit sales, can use an open register. */
    private function cannotUse(Request $request, CashRegister $register): ?string
    {
        if ($register->closed_at) {
            return 'This register is already closed.';
        }
        if (! $this->isAtBranch($request, $register->branch_id)) {
            return 'This register is at another branch.';
        }
        $user = $request->user();
        if ($register->user_id !== (string) $user->id && ! $user->hasPermission('sell.edit')) {
            return 'This is '.$register->user_name.'\'s register.';
        }

        return null;
    }

    /** The branch's cash drawer account (the same one a cash sale goes to). */
    private function drawerAccount(int $branchId): ?PaymentAccount
    {
        return PaymentAccount::cashDrawerFor($branchId);
    }

    private function isAtBranch(Request $request, int $branchId): bool
    {
        $userBranch = $request->user()->branch_id;

        return ! $userBranch || (int) $userBranch === $branchId;
    }

    private function notYourBranch()
    {
        return response()->json(['message' => 'You can only use the register at your own branch.'], 403);
    }
}
