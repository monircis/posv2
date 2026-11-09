<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Hrm\HrmController;
use App\Models\Approval;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeavePolicy;
use App\Models\LeaveRequest;
use Carbon\Carbon;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * HRM > Leave. A request is checked against its leave policy and the balance, then goes through the
 * "leave" approval workflow. Approving takes the days off the balance and marks the days in attendance.
 */
#[Group('HRM', weight: 18)]
class LeaveRequestController extends HrmController
{
    /**
     * List leave requests
     *
     * Newest first. Filters: leave_status (Pending, Approved, Declined, Cancelled), employee_id, branch_id,
     * department_id, leave_policy_id, from, to. Needs `hrm.view`.
     */
    public function index(Request $request)
    {
        $query = LeaveRequest::whereIn('employee_id', $this->employeeIds($request))->orderByDesc('date_from')->orderByDesc('id');
        if ($request->filled('leave_status')) {
            $query->where('status', $request->input('leave_status'));
        }
        if ($request->filled('leave_policy_id')) {
            $query->where('leave_policy_id', $request->input('leave_policy_id'));
        }
        if ($request->filled('from')) {
            $query->where('date_to', '>=', $request->input('from'));
        }
        if ($request->filled('to')) {
            $query->where('date_from', '<=', $request->input('to'));
        }

        return $query->limit(1000)->get();
    }

    /** One request with its approval history. Needs `hrm.view`. */
    public function show(Request $request, LeaveRequest $leaveRequest)
    {
        if (! $this->canSeeEmployee($request, $leaveRequest->employee)) {
            return $this->notYourBranch();
        }

        return $leaveRequest->load('employee:id,code,first,last')->toArray() + ['approvals' => Approval::history('leave', $leaveRequest->id)];
    }

    /**
     * Request leave
     *
     * Send leave_policy_id (or `type` with the policy's name, e.g. Annual). duration: full (default), half or hours
     * (half and hours are for one day; send `hours`). `days` is worked out here, without days off and holidays when
     * the policy says so. A file can be sent as `attachment` (form data). Needs `hrm.create`.
     */
    public function store(Request $request)
    {
        $data = $request->validate(self::rules());
        $employee = $this->findEmployee($request, $data['employee_id']);
        if (! $employee) {
            return $this->notYourBranch();
        }

        return self::apply($employee, $data, $request->file('attachment'), (string) $request->user()->id);
    }

    public static function rules(): array
    {
        return [
            'employee_id' => 'required|integer',
            'leave_policy_id' => 'required_without:type|nullable|exists:leave_policies,id',
            'type' => 'required_without:leave_policy_id|nullable|string|max:60',
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
            'duration' => 'nullable|in:full,half,hours',
            'hours' => 'required_if:duration,hours|nullable|numeric|min:0.5|max:24',
            'note' => 'nullable|string|max:500',
            'attachment' => 'nullable|file|mimes:pdf,png,jpg,jpeg,webp|max:5120',
        ];
    }

    /** Checks the request and saves it (shared with self-service). */
    public static function apply(Employee $employee, array $data, ?UploadedFile $file, string $userId)
    {
        $policy = ! empty($data['leave_policy_id']) ? LeavePolicy::find($data['leave_policy_id']) : LeavePolicy::where('name', $data['type'])->first();
        if (! $policy) {
            return response()->json(['message' => 'There is no '.$data['type'].' leave.'], 422);
        }
        $duration = $data['duration'] ?? 'full';
        $problem = $policy->problemFor($employee)
            ?? self::problem($employee, $policy, $data, $duration, $file);
        if ($problem) {
            return response()->json(['message' => $problem], 422);
        }

        $days = $policy->countDays($employee, $data['date_from'], $data['date_to'], $duration, $data['hours'] ?? null);
        if ($days <= 0) {
            return response()->json(['message' => 'Those dates have no working days.'], 422);
        }
        if ($policy->attachment_after_days !== null && $days > $policy->attachment_after_days && ! $file) {
            return response()->json(['message' => 'Please attach a document for '.strtolower($policy->name).' leave of more than '.$policy->attachment_after_days.' days.'], 422);
        }
        if ($policy->track_balance) {
            $balance = LeaveBalance::for($employee, $policy, (int) substr($data['date_from'], 0, 4));
            $left = round($balance->available - $balance->pendingDays(), 2);
            if ($left < $days) {
                return response()->json(['message' => $employee->first.' only has '.$left.' days of '.strtolower($policy->name).' leave left (including requests that are waiting).'], 422);
            }
        }

        $leave = LeaveRequest::create([
            'ref' => LeaveRequest::nextRef(), 'employee_id' => $employee->id, 'leave_policy_id' => $policy->id, 'branch_id' => $employee->branch_id,
            'type' => $policy->name, 'date_from' => $data['date_from'], 'date_to' => $data['date_to'], 'duration' => $duration,
            'hours' => $duration === 'hours' ? $data['hours'] : null, 'days' => $days, 'status' => 'Pending', 'note' => $data['note'] ?? null,
            'attachment' => $file ? $file->store('leave-attachments', 'local') : null, 'user_id' => $userId,
        ]);
        Approval::start('leave', $leave);

        return response()->json($leave->fresh(), 201);
    }

    private static function problem(Employee $employee, LeavePolicy $policy, array $data, string $duration, ?UploadedFile $file): ?string
    {
        if (in_array($duration, ['half', 'hours'], true) && $data['date_from'] !== $data['date_to']) {
            return 'Half-day and hourly leave are for one day.';
        }
        if ($duration === 'half' && ! $policy->allow_half_day) {
            return $policy->name.' leave cannot be taken as a half day.';
        }
        if ($duration === 'hours' && ! $policy->allow_hourly) {
            return $policy->name.' leave cannot be taken by the hour.';
        }
        if (! $policy->probation_allowed && $employee->onProbation($data['date_from'])) {
            return $employee->first.' is on probation until '.$employee->probation_end->format('d/m/Y').' and cannot take '.strtolower($policy->name).' leave yet.';
        }
        if (substr($data['date_from'], 0, 4) !== substr($data['date_to'], 0, 4)) {
            return 'Leave cannot run into the next year. Please ask for the two years separately.';
        }
        $overlap = LeaveRequest::where('employee_id', $employee->id)->whereIn('status', ['Pending', 'Approved'])
            ->where('date_from', '<=', $data['date_to'])->where('date_to', '>=', $data['date_from'])->first();
        if ($overlap) {
            return $employee->first.' already has leave '.$overlap->ref.' on those days.';
        }

        return null;
    }

    /**
     * Approve or decline (the HRM screen)
     *
     * Decides every approval step this user is allowed to decide, one after the other.
     * For a single approval step use POST /approvals/leave/{id} instead. Needs `hrm.edit`.
     */
    public function decide(Request $request, LeaveRequest $leaveRequest)
    {
        $data = $request->validate(['approve' => 'required|boolean', 'remarks' => 'nullable|string|max:500']);
        if (! $this->canSeeEmployee($request, $leaveRequest->employee)) {
            return $this->notYourBranch();
        }
        if ($leaveRequest->status !== 'Pending') {
            return $this->failed($leaveRequest->ref.' has already been decided.');
        }

        for ($i = 0; $i < count(Approval::LEVELS); $i++) {
            $answer = Approval::act($request->user(), 'leave', $leaveRequest->fresh(), (bool) $data['approve'], $data['remarks'] ?? null);
            if (isset($answer['error'])) {
                if ($i === 0) {
                    return $this->failed($answer['error'], str_contains($answer['error'], 'not you') ? 403 : 422);
                }
                break; // the next step is for someone else
            }
            if ($answer['result'] !== 'next') {
                break;
            }
        }

        return $leaveRequest->fresh();
    }

    /**
     * Cancel leave
     *
     * A pending request is withdrawn. An approved one is taken back: the days go back on the balance. Needs `hrm.edit`.
     */
    public function cancel(Request $request, LeaveRequest $leaveRequest)
    {
        if (! $this->canSeeEmployee($request, $leaveRequest->employee)) {
            return $this->notYourBranch();
        }
        if ($leaveRequest->status === 'Pending') {
            $leaveRequest->update(['status' => 'Cancelled', 'decided_by' => $this->by($request)]);
            Approval::cancel('leave', $leaveRequest->id);
        } elseif ($leaveRequest->status === 'Approved') {
            $leaveRequest->cancelApproved($this->by($request));
        } else {
            return $this->failed($leaveRequest->ref.' is '.strtolower($leaveRequest->status).'.');
        }

        return $leaveRequest->fresh();
    }

    /** Download the attachment. Needs `hrm.view`. */
    public function attachment(Request $request, LeaveRequest $leaveRequest)
    {
        if (! $this->canSeeEmployee($request, $leaveRequest->employee)) {
            return $this->notYourBranch();
        }
        if (! $leaveRequest->attachment) {
            return $this->failed('This request has no attachment.', 404);
        }

        return Storage::disk('local')->download($leaveRequest->attachment);
    }

    /**
     * Leave calendar
     *
     * Approved and pending leave in a month, one row per request, plus `days`: date => names on leave.
     * Filters: branch_id, department_id, employee_id. Needs `hrm.view`.
     */
    #[QueryParameter('month', 'e.g. 2026-11. Default: this month.', type: 'string')]
    public function calendar(Request $request)
    {
        return self::calendarFor($this->employeeIds($request), $request->input('month', Attendance::shopNow()->format('Y-m')));
    }

    public static function calendarFor($employeeIds, string $month): array
    {
        $from = Carbon::parse($month.'-01');
        $to = $from->copy()->endOfMonth();
        $rows = LeaveRequest::with('employee:id,first,last')->whereIn('employee_id', $employeeIds)->whereIn('status', ['Pending', 'Approved'])
            ->where('date_from', '<=', $to->toDateString())->where('date_to', '>=', $from->toDateString())->orderBy('date_from')->get();

        $days = [];
        foreach ($rows as $leave) {
            for ($day = $leave->date_from->copy()->max($from); $day->lte($leave->date_to) && $day->lte($to); $day->addDay()) {
                $days[$day->toDateString()][] = ['employee_id' => $leave->employee_id, 'name' => $leave->employee?->name, 'type' => $leave->type, 'status' => $leave->status];
            }
        }
        ksort($days);

        return [
            'month' => $month,
            'leave' => $rows->map(fn ($l) => $l->only('id', 'ref', 'employee_id', 'type', 'date_from', 'date_to', 'duration', 'days', 'status') + ['name' => $l->employee?->name])->all(),
            'days' => $days,
        ];
    }
}
