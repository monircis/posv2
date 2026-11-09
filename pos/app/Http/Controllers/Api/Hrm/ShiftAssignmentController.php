<?php

namespace App\Http\Controllers\Api\Hrm;

use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use Carbon\Carbon;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\Request;

/**
 * HRM > Shift assignments: who works which shift from which date.
 * target_type branch, department or employee. The most specific one wins (employee > department > branch).
 * rotation_shift_ids + rotation_days: the shift changes every N days in that order. Old rows are the shift history.
 */
#[Group('HRM setup', weight: 19)]
class ShiftAssignmentController extends HrmController
{
    /**
     * List assignments
     *
     * Newest first. Filters: target_type + target_id, shift_id, on (only the ones that apply on that date). Needs `hrm.view`.
     */
    #[QueryParameter('on', 'Only the assignments in force on this date.', type: 'string', format: 'date')]
    public function index(Request $request)
    {
        $query = ShiftAssignment::with('shift:id,name,code,start_time,end_time')->orderByDesc('date_from')->orderByDesc('id');
        foreach (['target_type', 'target_id', 'shift_id'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->input($field));
            }
        }
        if ($request->filled('on')) {
            $on = $request->input('on');
            $query->where('date_from', '<=', $on)->where(fn ($q) => $q->whereNull('date_to')->orWhere('date_to', '>=', $on));
        }

        $rows = [];
        foreach ($query->get() as $assignment) {
            if ($this->targetBranchOk($request, $assignment->target_type, $assignment->target_id)) {
                $rows[] = $assignment->toArray() + ['target_name' => $this->targetName($assignment->target_type, $assignment->target_id)];
            }
        }

        return $rows;
    }

    /**
     * Assign a shift
     *
     * Ends the target's open assignment the day before date_from, so the history stays clean. Needs `hrm.edit`.
     */
    public function store(Request $request)
    {
        $data = $request->validate($this->rules());
        if (! $this->targetBranchOk($request, $data['target_type'], $data['target_id'])) {
            return $this->notYourBranch();
        }

        $open = ShiftAssignment::where('target_type', $data['target_type'])->where('target_id', $data['target_id'])
            ->where('date_from', '<', $data['date_from'])->whereNull('date_to')->get();
        foreach ($open as $old) {
            $old->update(['date_to' => Carbon::parse($data['date_from'])->subDay()->toDateString()]);
        }

        $assignment = ShiftAssignment::create($data + ['created_by' => $this->by($request)]);
        Shift::forgetCache();

        return response()->json($assignment->load('shift:id,name,code'), 201);
    }

    /** Change an assignment (e.g. set its end date). Needs `hrm.edit`. */
    public function update(Request $request, ShiftAssignment $shiftAssignment)
    {
        $data = $request->validate($this->rules());
        if (! $this->targetBranchOk($request, $shiftAssignment->target_type, $shiftAssignment->target_id)
            || ! $this->targetBranchOk($request, $data['target_type'], $data['target_id'])) {
            return $this->notYourBranch();
        }
        $shiftAssignment->update($data);

        return $shiftAssignment->fresh()->load('shift:id,name,code');
    }

    /** Delete an assignment. Needs `hrm.edit`. */
    public function destroy(Request $request, ShiftAssignment $shiftAssignment)
    {
        if (! $this->targetBranchOk($request, $shiftAssignment->target_type, $shiftAssignment->target_id)) {
            return $this->notYourBranch();
        }
        $shiftAssignment->delete();

        return response()->noContent();
    }

    /**
     * Shift schedule of an employee
     *
     * One row per day from..to (at most 62 days): the shift, or day off / holiday. Needs `hrm.view`.
     */
    #[QueryParameter('from', 'Default: today.', type: 'string', format: 'date')]
    #[QueryParameter('to', 'Default: from + 13 days.', type: 'string', format: 'date')]
    public function schedule(Request $request, Employee $employee)
    {
        if (! $this->canSeeEmployee($request, $employee)) {
            return $this->notYourBranch();
        }

        return self::scheduleFor($employee, $request->input('from'), $request->input('to'));
    }

    public static function scheduleFor(Employee $employee, ?string $from, ?string $to): array
    {
        $start = Carbon::parse($from ?: now(config('app.shop_timezone'))->toDateString());
        $end = $to ? Carbon::parse($to) : $start->copy()->addDays(13);
        if ($end->lt($start) || $start->diffInDays($end) > 62) {
            $end = $start->copy()->addDays(62);
        }

        $days = [];
        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            $date = $day->toDateString();
            $shift = Shift::forEmployee($employee, $date);
            $holiday = Holiday::onDay($employee->branch_id, $date);
            $days[] = [
                'date' => $date,
                'day' => $day->format('D'),
                'shift' => $shift ? $shift->only('id', 'name', 'code', 'start_time', 'end_time', 'break_minutes', 'flexible') : null,
                'day_off' => Shift::isOffFor($employee, $date),
                'holiday' => $holiday?->name,
            ];
        }

        return $days;
    }

    // ---------------------------------------------------------------------

    private function rules(): array
    {
        return [
            'shift_id' => 'required|exists:shifts,id',
            'target_type' => 'required|in:branch,department,employee',
            'target_id' => 'required|integer|min:1',
            'date_from' => 'required|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'rotation_shift_ids' => 'nullable|array|min:2',
            'rotation_shift_ids.*' => 'integer|exists:shifts,id',
            'rotation_days' => 'nullable|integer|min:1|max:31',
            'note' => 'nullable|string|max:255',
        ];
    }

    /** Is the target (and the user's branch) fine? */
    private function targetBranchOk(Request $request, string $type, int $id): bool
    {
        return match ($type) {
            'branch' => $this->isAtBranch($request, $id),
            // An all-branch department (branch_id null) needs a user who is not kept to one branch.
            'department' => ($d = Department::find($id)) !== null && $this->isAtBranch($request, $d->branch_id),
            'employee' => ($e = Employee::find($id)) !== null && $this->canSeeEmployee($request, $e),
            default => false,
        };
    }

    private function targetName(string $type, int $id): ?string
    {
        return match ($type) {
            'branch' => Branch::whereKey($id)->value('name'),
            'department' => Department::whereKey($id)->value('name'),
            'employee' => Employee::withTrashed()->find($id)?->name,
            default => null,
        };
    }
}
