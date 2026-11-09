<?php

namespace App\Http\Controllers\Api\Hrm;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\Request;

/**
 * Base for the HRM controllers: the branch rules.
 * A user with a branch only works with that branch's staff and records, unless they have hrm.all-locations.
 * Records with branch_id null (departments, shifts, holidays, policies) are for every branch.
 */
abstract class HrmController extends Controller
{
    /** The user's own branch when they are kept to it, or null (they can see every branch). */
    protected function lockedBranch(Request $request): ?int
    {
        $user = $request->user();

        return $user->branch_id && ! $user->hasPermission('hrm.all-locations') ? (int) $user->branch_id : null;
    }

    /** The branch to show: the user's own when they are kept to it, else ?branch_id (or null = all). */
    protected function branchFor(Request $request): ?int
    {
        return $this->lockedBranch($request) ?? ($request->filled('branch_id') ? (int) $request->input('branch_id') : null);
    }

    /** May this user work with records of that branch? (null = an all-branch record) */
    protected function isAtBranch(Request $request, ?int $branchId): bool
    {
        $locked = $this->lockedBranch($request);

        return ! $locked || $locked === $branchId;
    }

    protected function canSeeEmployee(Request $request, Employee $employee): bool
    {
        return $this->isAtBranch($request, $employee->branch_id);
    }

    /**
     * Employees the user may see, with the common filters:
     * branch_id, department_id, designation_id, employee_id, status (or "working" = Active and Probation).
     */
    protected function employeeQuery(Request $request)
    {
        $query = Employee::query();
        $branch = $this->branchFor($request);
        if ($branch) {
            $query->where('branch_id', $branch);
        }
        foreach (['department_id', 'designation_id'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->input($field));
            }
        }
        if ($request->filled('employee_id')) {
            $query->where('id', $request->input('employee_id'));
        }
        if ($request->input('status') === 'working') {
            $query->whereIn('status', Employee::WORKING);
        } elseif ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        return $query;
    }

    /** Ids of the employees the user may see (for filtering other tables). */
    protected function employeeIds(Request $request)
    {
        return $this->employeeQuery($request)->select('id');
    }

    /** The employee, when the user may work with them; null otherwise. */
    protected function findEmployee(Request $request, $id): ?Employee
    {
        $employee = Employee::find($id);

        return $employee && $this->canSeeEmployee($request, $employee) ? $employee : null;
    }

    protected function notYourBranch()
    {
        return response()->json(['message' => 'You can only work with staff of your own branch.'], 403);
    }

    protected function failed(string $message, int $status = 422)
    {
        return response()->json(['message' => $message], $status);
    }

    /** The name saved in "created by" fields. */
    protected function by(Request $request): string
    {
        return (string) $request->user()->name;
    }
}
