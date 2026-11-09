<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\PaymentAccount;
use App\Models\Tip;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** HRM > Tips: tips customers gave at the POS, and paying them out to the staff. */
#[Group('HRM', weight: 20)]
class TipController extends Controller
{
    /**
     * Tips
     *
     * Newest first, at most 500. `staff` adds up each staff member's tips for the same filter.
     * A user with a branch only sees that branch. Needs `hrm.view`.
     */
    #[QueryParameter('status', 'unpaid (default), paid or all.', type: 'string')]
    #[QueryParameter('from', 'First day, YYYY-MM-DD.', type: 'string')]
    #[QueryParameter('to', 'Last day, YYYY-MM-DD.', type: 'string')]
    #[QueryParameter('branch_id', 'One branch.', type: 'int')]
    public function index(Request $request)
    {
        $request->validate([
            'status' => 'nullable|in:unpaid,paid,all',
            'from' => 'nullable|date',
            'to' => 'nullable|date',
            'branch_id' => 'nullable|integer',
        ]);

        $query = Tip::with('sale:id,ref,total', 'employee:id,first,last', 'branch:id,name')->orderByDesc('id');

        $status = $request->input('status', 'unpaid');
        if ($status === 'unpaid') {
            $query->whereNull('paid_at');
        } elseif ($status === 'paid') {
            $query->whereNotNull('paid_at');
        }
        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->input('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->input('to'));
        }

        $userBranch = $request->user()->branch_id;
        if ($userBranch) {
            $query->where('branch_id', $userBranch);
        } elseif ($request->filled('branch_id')) {
            $query->where('branch_id', $request->input('branch_id'));
        }

        $tips = $query->limit(500)->get();

        $rows = [];
        $staff = [];
        foreach ($tips as $tip) {
            $name = $tip->employee ? trim($tip->employee->first.' '.$tip->employee->last) : 'Unknown';
            $rows[] = [
                'id' => $tip->id,
                'date' => $tip->created_at->toDateTimeString(),
                'sale_ref' => $tip->sale ? $tip->sale->ref : null,
                'sale_total' => $tip->sale ? (float) $tip->sale->total : null,
                'employee_id' => $tip->employee_id,
                'employee' => $name,
                'branch' => $tip->branch ? $tip->branch->name : null,
                'amount' => $tip->amount,
                'method' => $tip->method,
                'taken_by' => $tip->created_by,
                'paid_at' => $tip->paid_at ? $tip->paid_at->toDateTimeString() : null,
                'paid_by' => $tip->paid_by,
            ];

            // One line per staff member: how many tips, the total, and what is still to pay.
            $id = $tip->employee_id;
            if (! isset($staff[$id])) {
                $staff[$id] = ['employee_id' => $id, 'employee' => $name, 'count' => 0, 'total' => 0, 'unpaid' => 0, 'unpaid_ids' => []];
            }
            $staff[$id]['count']++;
            $staff[$id]['total'] = round($staff[$id]['total'] + $tip->amount, 2);
            if (! $tip->paid_at) {
                $staff[$id]['unpaid'] = round($staff[$id]['unpaid'] + $tip->amount, 2);
                $staff[$id]['unpaid_ids'][] = $tip->id;
            }
        }

        $staff = array_values($staff);
        usort($staff, function ($a, $b) {
            return $b['unpaid'] <=> $a['unpaid'];
        });

        return [
            'data' => $rows,
            'staff' => $staff,
            'total' => round(array_sum(array_column($rows, 'amount')), 2),
            'accounts' => PaymentAccount::where('active', true)->orderBy('name')->get(['id', 'name', 'kind']),
        ];
    }

    /**
     * Pay tips
     *
     * Pays the staff member the tips in `tip_ids` (all must be theirs and not paid yet).
     * The money comes out of `payment_account_id`, e.g. the cash drawer. Needs `hrm.edit`.
     */
    public function pay(Request $request)
    {
        $data = $request->validate([
            'employee_id' => 'required|integer|exists:employees,id',
            'tip_ids' => 'required|array|min:1',
            'tip_ids.*' => 'integer',
            'payment_account_id' => 'required|string|exists:payment_accounts,id',
        ]);
        $userName = $request->user()->name;
        $userBranch = $request->user()->branch_id;

        $result = DB::transaction(function () use ($data, $userName, $userBranch) {
            $tips = Tip::lockForUpdate()->whereIn('id', $data['tip_ids'])->get();

            if ($tips->count() !== count(array_unique($data['tip_ids']))) {
                return 'Some of these tips no longer exist. Reload the page.';
            }
            foreach ($tips as $tip) {
                if ($tip->employee_id !== (int) $data['employee_id']) {
                    return 'Some of these tips are for someone else.';
                }
                if ($tip->paid_at) {
                    return 'Some of these tips were already paid. Reload the page.';
                }
                if ($userBranch && $tip->branch_id !== (int) $userBranch) {
                    return 'You can only pay tips of your own branch.';
                }
            }

            $account = PaymentAccount::lockForUpdate()->find($data['payment_account_id']);
            if (! $account->active) {
                return $account->name.' is closed.';
            }

            $employee = Employee::find($data['employee_id']);
            $name = trim($employee->first.' '.$employee->last);
            $total = round($tips->sum('amount'), 2);

            $account->addTransaction(-$total, now()->toDateString(), 'tip payout', 'Tips paid ('.$tips->count().')', $name, 'TIPS');

            foreach ($tips as $tip) {
                $tip->paid_at = now();
                $tip->paid_by = $userName;
                $tip->paid_from_account_id = $account->id;
                $tip->save();
            }

            return ['employee' => $name, 'count' => $tips->count(), 'total' => $total, 'account' => $account->name];
        });

        if (is_string($result)) {
            return response()->json(['message' => $result], 422);
        }

        return $result;
    }
}
