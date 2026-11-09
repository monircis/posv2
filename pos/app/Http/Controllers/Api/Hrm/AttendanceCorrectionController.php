<?php

namespace App\Http\Controllers\Api\Hrm;

use App\Http\Controllers\Api\AttendanceController;
use App\Models\Approval;
use App\Models\AttendanceCorrection;
use App\Models\Employee;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;

/**
 * HRM > Attendance corrections (regularization requests). They go through the "correction" approval workflow;
 * when the last step approves, the day's punches are replaced by the asked-for times.
 */
#[Group('HRM', weight: 18)]
class AttendanceCorrectionController extends HrmController
{
    /**
     * List corrections
     *
     * Newest first. Filters: status, employee_id, branch_id, from, to. Needs `hrm.view`.
     */
    public function index(Request $request)
    {
        $query = AttendanceCorrection::with('employee:id,code,first,last')->whereIn('employee_id', $this->employeeIds($request))->orderByDesc('id');
        if ($request->filled('correction_status')) {
            $query->where('status', $request->input('correction_status'));
        }
        if ($request->filled('from')) {
            $query->where('date', '>=', $request->input('from'));
        }
        if ($request->filled('to')) {
            $query->where('date', '<=', $request->input('to'));
        }

        return $query->limit(500)->get();
    }

    /** One correction with its approval history. Needs `hrm.view`. */
    public function show(Request $request, AttendanceCorrection $attendanceCorrection)
    {
        if (! $this->canSeeEmployee($request, $attendanceCorrection->employee)) {
            return $this->notYourBranch();
        }

        return $attendanceCorrection->load('employee:id,code,first,last')->toArray() + ['approvals' => Approval::history('correction', $attendanceCorrection->id)];
    }

    /**
     * Ask for a correction (for an employee)
     *
     * in_at / out_at are the shop's clock times. Needs `hrm.create`.
     */
    public function store(Request $request)
    {
        $data = $request->validate(self::rules());
        $employee = $this->findEmployee($request, $data['employee_id']);
        if (! $employee) {
            return $this->notYourBranch();
        }

        return self::create($employee, $data, (string) $request->user()->id, $this->by($request));
    }

    /** Shared with self-service. */
    public static function rules(): array
    {
        return [
            'employee_id' => 'required|integer',
            'date' => 'required|date',
            'in_at' => 'required|date',
            'out_at' => 'nullable|date|after:in_at',
            'break_minutes' => 'nullable|integer|min:0|max:600',
            'reason' => 'required|string|max:500',
        ];
    }

    public static function create(Employee $employee, array $data, string $userId, string $by)
    {
        $problem = AttendanceController::correctionProblem($data);
        if ($problem) {
            return response()->json(['message' => $problem], 422);
        }
        if (AttendanceCorrection::where('employee_id', $employee->id)->whereDate('date', $data['date'])->where('status', 'pending')->exists()) {
            return response()->json(['message' => 'A fix for that day is already waiting.'], 422);
        }

        $correction = AttendanceCorrection::create([
            'ref' => AttendanceCorrection::nextRef(), 'employee_id' => $employee->id, 'branch_id' => $employee->branch_id,
            'date' => $data['date'], 'in_at' => $data['in_at'], 'out_at' => $data['out_at'] ?? null,
            'break_minutes' => $data['break_minutes'] ?? null, 'reason' => $data['reason'], 'status' => 'pending',
            'user_id' => $userId, 'created_by' => $by,
        ]);
        Approval::start('correction', $correction);

        return response()->json($correction->fresh(), 201);
    }

    /** Cancel a pending correction. Needs `hrm.edit`. */
    public function cancel(Request $request, AttendanceCorrection $attendanceCorrection)
    {
        if (! $this->canSeeEmployee($request, $attendanceCorrection->employee)) {
            return $this->notYourBranch();
        }

        return self::cancelIt($attendanceCorrection);
    }

    public static function cancelIt(AttendanceCorrection $correction)
    {
        if ($correction->status !== 'pending') {
            return response()->json(['message' => $correction->ref.' is '.$correction->status.'.'], 422);
        }
        $correction->update(['status' => 'cancelled']);
        Approval::cancel('correction', $correction->id);

        return $correction->fresh();
    }
}
