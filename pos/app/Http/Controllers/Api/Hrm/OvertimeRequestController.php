<?php

namespace App\Http\Controllers\Api\Hrm;

use App\Models\Approval;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\OvertimePolicy;
use App\Models\OvertimeRequest;
use App\Models\Shift;
use Carbon\Carbon;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\Request;

/**
 * HRM > Overtime. "auto" requests are made from the attendance (extra minutes past the shift, by the overtime policy);
 * "manual" ones are asked for. Both go through the "overtime" approval workflow; payroll pays the approved ones.
 */
#[Group('HRM', weight: 18)]
class OvertimeRequestController extends HrmController
{
    /**
     * List overtime
     *
     * Newest first. Filters: overtime_status, month (2026-11), employee_id, branch_id, department_id, source. Needs `hrm.view`.
     */
    public function index(Request $request)
    {
        $query = OvertimeRequest::with('employee:id,code,first,last')->whereIn('employee_id', $this->employeeIds($request))->orderByDesc('date')->orderByDesc('id');
        if ($request->filled('overtime_status')) {
            $query->where('status', $request->input('overtime_status'));
        }
        if ($request->filled('source')) {
            $query->where('source', $request->input('source'));
        }
        if ($request->filled('month')) {
            $from = Carbon::parse($request->input('month').'-01');
            $query->whereBetween('date', [$from->toDateString(), $from->copy()->endOfMonth()->toDateString()]);
        }

        return $query->limit(1000)->get();
    }

    /** One overtime request with its approval history. Needs `hrm.view`. */
    public function show(Request $request, OvertimeRequest $overtimeRequest)
    {
        if (! $this->canSeeEmployee($request, $overtimeRequest->employee)) {
            return $this->notYourBranch();
        }

        return $overtimeRequest->load('employee:id,code,first,last')->toArray() + ['approvals' => Approval::history('overtime', $overtimeRequest->id)];
    }

    /**
     * Ask for overtime
     *
     * `minutes` of overtime on `date`, with a reason. The rate comes from the overtime policy and the kind of day
     * (workday, weekend, holiday). Needs `hrm.create`.
     */
    public function store(Request $request)
    {
        $data = $request->validate(self::rules());
        $employee = $this->findEmployee($request, $data['employee_id']);
        if (! $employee) {
            return $this->notYourBranch();
        }

        return self::create($employee, $data, (string) $request->user()->id);
    }

    public static function rules(): array
    {
        return [
            'employee_id' => 'required|integer',
            'date' => 'required|date',
            'minutes' => 'required|integer|min:1|max:1440',
            'reason' => 'required|string|max:500',
        ];
    }

    public static function create(Employee $employee, array $data, string $userId)
    {
        $policy = OvertimePolicy::forEmployee($employee);
        if ($policy && $policy->max_daily_minutes > 0 && $data['minutes'] > $policy->max_daily_minutes) {
            return response()->json(['message' => 'The most overtime in a day is '.round($policy->max_daily_minutes / 60, 1).' hours.'], 422);
        }
        if (OvertimeRequest::where('employee_id', $employee->id)->whereDate('date', $data['date'])->whereIn('status', ['pending', 'approved'])->exists()) {
            return response()->json(['message' => $employee->first.' already has overtime on that day.'], 422);
        }

        $date = $data['date'];
        $dayType = Holiday::onDay($employee->branch_id, $date) ? 'holiday' : (Shift::isOffFor($employee, $date) ? 'weekend' : 'workday');
        $request = new OvertimeRequest([
            'ref' => OvertimeRequest::nextRef(), 'employee_id' => $employee->id, 'branch_id' => $employee->branch_id,
            'attendance_id' => Attendance::where('employee_id', $employee->id)->whereDate('date', $date)->value('id'),
            'date' => $date, 'minutes' => $data['minutes'], 'day_type' => $dayType, 'source' => 'manual',
            'reason' => $data['reason'], 'status' => 'pending', 'user_id' => $userId,
        ]);
        $request->price($employee, $policy);
        $request->save();
        Approval::start('overtime', $request);

        return response()->json($request->fresh(), 201);
    }

    /** Cancel a pending overtime request. Needs `hrm.edit`. */
    public function cancel(Request $request, OvertimeRequest $overtimeRequest)
    {
        if (! $this->canSeeEmployee($request, $overtimeRequest->employee)) {
            return $this->notYourBranch();
        }
        if ($overtimeRequest->status !== 'pending') {
            return $this->failed($overtimeRequest->ref.' is '.$overtimeRequest->status.'.');
        }
        $overtimeRequest->update(['status' => 'cancelled']);
        Approval::cancel('overtime', $overtimeRequest->id);

        return $overtimeRequest->fresh();
    }

    /**
     * Monthly overtime summary
     *
     * Per employee: approved, pending and paid minutes and amounts in the month. Needs `hrm.view`.
     */
    #[QueryParameter('month', 'e.g. 2026-11. Default: this month.', type: 'string')]
    public function summary(Request $request)
    {
        $month = $request->input('month', Attendance::shopNow()->format('Y-m'));
        $from = Carbon::parse($month.'-01');
        $rows = OvertimeRequest::with('employee:id,code,first,last')->whereIn('employee_id', $this->employeeIds($request))
            ->whereBetween('date', [$from->toDateString(), $from->copy()->endOfMonth()->toDateString()])
            ->whereIn('status', ['pending', 'approved'])->get()->groupBy('employee_id');

        $out = [];
        foreach ($rows as $employeeId => $list) {
            $approved = $list->where('status', 'approved');
            $pending = $list->where('status', 'pending');
            $out[] = [
                'employee_id' => $employeeId, 'code' => $list->first()->employee?->code, 'name' => $list->first()->employee?->name,
                'approved_minutes' => $approved->sum('minutes'), 'approved_amount' => round($approved->sum('amount'), 2),
                'pending_minutes' => $pending->sum('minutes'), 'pending_amount' => round($pending->sum('amount'), 2),
                'paid_amount' => round($approved->whereNotNull('pay_run_id')->sum('amount'), 2),
            ];
        }

        return ['month' => $month, 'rows' => $out];
    }
}
