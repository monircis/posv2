<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\PayRun;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * HRM > Payroll: one fortnight at a time. draft, then approved (hours locked), then paid.
 * Paying records the run. It does not move money, email payslips or post to Accounting.
 */
#[Group('HRM', weight: 18)]
class PayRunController extends Controller
{
    /**
     * Current pay run
     *
     * The latest run with its items. A draft run also gets a row for any active employee who has none.
     * Null when there are no runs yet. Needs `hrm.view`.
     */
    public function current()
    {
        $run = PayRun::where('kind', 'fortnight')->orderByDesc('period_from')->orderByDesc('id')->first();
        if (! $run) {
            return response()->json(null);
        }

        if ($run->status === 'draft') {
            $has = $run->items()->pluck('employee_id');
            foreach (Employee::whereIn('status', Employee::WORKING)->whereNotIn('id', $has)->get() as $employee) {
                $run->items()->create(['employee_id' => $employee->id]);
            }
        }

        return $run->load('items');
    }

    /**
     * Start the next pay run
     *
     * The fortnight after the last run. Ordinary hours start from the worked minutes (without breaks) in that fortnight; salaried staff need none.
     * Refused while the last run is not paid. Needs `hrm.create`.
     */
    public function store()
    {
        $last = PayRun::where('kind', 'fortnight')->orderByDesc('period_from')->orderByDesc('id')->first();
        if ($last && $last->status !== 'paid') {
            return response()->json(['message' => 'Finish the current pay run first.'], 422);
        }

        $from = $last ? $last->period_to->copy()->addDay() : now()->startOfWeek();
        $to = $from->copy()->addDays(13);

        $run = DB::transaction(function () use ($from, $to) {
            $run = PayRun::create(['kind' => 'fortnight', 'period_from' => $from, 'period_to' => $to, 'pay_date' => $to->copy()->addDays(2), 'status' => 'draft']);

            foreach (Employee::whereIn('status', Employee::WORKING)->get() as $employee) {
                $minutes = 0;
                foreach (Attendance::where('employee_id', $employee->id)->whereBetween('date', [$from->toDateString(), $to->toDateString()])->whereNotNull('in')->whereNotNull('out')->get() as $day) {
                    $minutes += $day->worked_minutes; // without breaks
                }
                $run->items()->create(['employee_id' => $employee->id, 'ord_hours' => round($minutes / 60, 2)]);
            }

            return $run;
        });

        return response()->json($run->load('items'), 201);
    }

    /**
     * Save hours
     *
     * Send `rows` with employee_id, ord_hours and ot_hours. Only while the run is a draft. Needs `hrm.edit`.
     */
    public function saveHours(Request $request, PayRun $payRun)
    {
        abort_if($payRun->kind !== 'fortnight', 404);
        $rows = $this->validatedRows($request);

        DB::transaction(function () use ($rows, $payRun) {
            $run = PayRun::lockForUpdate()->find($payRun->id);
            if ($run->status !== 'draft') {
                throw ValidationException::withMessages(['status' => ['Hours are locked. Go back to draft to change them.']]);
            }
            $this->storeHours($run, $rows);
        });

        return $payRun->fresh()->load('items');
    }

    /**
     * Approve pay run
     *
     * Saves the hours sent, works out each person's pay and locks the run. Needs `hrm.edit`.
     */
    public function approve(Request $request, PayRun $payRun)
    {
        abort_if($payRun->kind !== 'fortnight', 404);
        $rows = $this->validatedRows($request);
        $userName = $request->user()->name;

        DB::transaction(function () use ($rows, $payRun, $userName) {
            $run = PayRun::lockForUpdate()->find($payRun->id);
            if ($run->status !== 'draft') {
                throw ValidationException::withMessages(['status' => ['This pay run is not a draft.']]);
            }
            $this->storeHours($run, $rows);

            foreach ($run->items()->get() as $item) {
                $item->update(PayRun::amounts(Employee::find($item->employee_id), $item->ord_hours, $item->ot_hours));
            }

            $run->status = 'approved';
            $run->approved_by = $userName;
            $run->save();
        });

        return $payRun->fresh()->load('items');
    }

    /**
     * Back to draft
     *
     * Only from approved: a paid run cannot be reopened. Needs `hrm.edit`.
     */
    public function reopen(PayRun $payRun)
    {
        abort_if($payRun->kind !== 'fortnight', 404);
        if ($payRun->status !== 'approved') {
            return response()->json(['message' => 'Only an approved pay run can go back to draft.'], 422);
        }

        $payRun->update(['status' => 'draft', 'approved_by' => null]);

        return $payRun->fresh()->load('items');
    }

    /**
     * Mark as paid
     *
     * Records the run as paid. It does not move money. Needs `hrm.edit`.
     */
    public function pay(Request $request, PayRun $payRun)
    {
        abort_if($payRun->kind !== 'fortnight', 404);
        if ($payRun->status !== 'approved') {
            return response()->json(['message' => 'Approve the pay run first.'], 422);
        }

        $payRun->update(['status' => 'paid', 'paid_by' => $request->user()->name, 'paid_at' => now()]);

        return $payRun->fresh()->load('items');
    }

    // ---------------------------------------------------------------------

    private function validatedRows(Request $request): array
    {
        return $request->validate([
            'rows' => 'present|array',
            'rows.*.employee_id' => 'required|exists:employees,id',
            'rows.*.ord_hours' => 'required|numeric|min:0|max:400',
            'rows.*.ot_hours' => 'required|numeric|min:0|max:400',
        ])['rows'];
    }

    private function storeHours(PayRun $run, array $rows): void
    {
        foreach ($rows as $row) {
            $run->items()->updateOrCreate(
                ['employee_id' => $row['employee_id']],
                ['ord_hours' => $row['ord_hours'], 'ot_hours' => $row['ot_hours']]
            );
        }
    }
}
