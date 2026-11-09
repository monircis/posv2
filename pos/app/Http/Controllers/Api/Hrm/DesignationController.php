<?php

namespace App\Http\Controllers\Api\Hrm;

use App\Models\Designation;
use App\Models\Employee;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** HRM > Designations (job titles), optionally inside a department. */
#[Group('HRM setup', weight: 19)]
class DesignationController extends HrmController
{
    /** List designations. ?department_id for one department. */
    public function index(Request $request)
    {
        $query = Designation::with('department:id,name')->orderBy('name');
        if ($request->filled('department_id')) {
            $query->where('department_id', $request->input('department_id'));
        }

        return $query->get();
    }

    public function store(Request $request)
    {
        return response()->json(Designation::create($request->validate($this->rules($request, null))), 201);
    }

    public function update(Request $request, Designation $designation)
    {
        $designation->update($request->validate($this->rules($request, $designation->id)));

        return $designation->fresh();
    }

    public function destroy(Designation $designation)
    {
        $count = Employee::where('designation_id', $designation->id)->count();
        if ($count) {
            return $this->failed($count.' employee(s) have this designation. Change them first, or switch it off.');
        }
        $designation->delete();

        return response()->noContent();
    }

    private function rules(Request $request, ?int $id): array
    {
        return [
            'name' => ['required', 'string', 'max:80', Rule::unique('designations')->where('department_id', $request->input('department_id'))->ignore($id)],
            'department_id' => 'nullable|exists:departments,id',
            'active' => 'boolean',
        ];
    }
}
