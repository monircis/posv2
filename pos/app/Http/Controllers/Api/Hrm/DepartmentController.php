<?php

namespace App\Http\Controllers\Api\Hrm;

use App\Models\Department;
use App\Models\Employee;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** HRM > Departments. branch_id null = used at every branch. manager_id = the department manager (an employee). */
#[Group('HRM setup', 'Departments, designations, shifts, holidays, policies, approval workflows and settings.', weight: 19)]
class DepartmentController extends HrmSetupController
{
    protected string $model = Department::class;

    protected function rules(Request $request, ?Model $record): array
    {
        return [
            'name' => ['required', 'string', 'max:80', Rule::unique('departments')->where('branch_id', $request->input('branch_id'))->ignore($record?->id)],
            'branch_id' => 'nullable|exists:branches,id',
            'manager_id' => 'nullable|exists:employees,id',
            'active' => 'boolean',
        ];
    }

    protected function deleteProblem(Model $record): ?string
    {
        $count = Employee::where('department_id', $record->id)->count();

        return $count ? $count.' employee(s) are in this department. Move them first, or switch it off.' : null;
    }
}
