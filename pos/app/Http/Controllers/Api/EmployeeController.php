<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Hrm\HrmController;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeavePolicy;
use App\Models\LeaveRequest;
use App\Models\Shift;
use Carbon\Carbon;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * HRM > Employees. A user with a branch only sees and changes staff of that branch (unless hrm.all-locations).
 * PINs are never sent out. The code (EMP-0001) is made when it is not given.
 */
#[Group('HRM', 'Employees, attendance, leave, overtime, deductions, payroll, reports and self-service.', weight: 18)]
class EmployeeController extends HrmController
{
    /**
     * List employees
     *
     * Sorted by id, with `has_pin`, their branch, department and designation. `status` shows On leave when approved
     * leave covers `today`. Filters: branch_id, department_id, designation_id, status (or "working"), q (name, code,
     * phone, email). Send per_page for pages. Needs `hrm.view`.
     */
    #[QueryParameter('today', 'The date to check leave against, e.g. 2026-11-04. Default: today.', type: 'string', format: 'date')]
    #[QueryParameter('q', 'Search name, code, phone or email.', type: 'string')]
    #[QueryParameter('per_page', 'Rows per page. Leave out for the whole list.', type: 'integer')]
    public function index(Request $request)
    {
        $query = $this->employeeQuery($request)->with(['branch:id,name', 'department:id,name', 'designation:id,name'])->orderBy('id');
        if ($request->filled('q')) {
            $q = '%'.$request->input('q').'%';
            $query->where(fn ($w) => $w->where('first', 'like', $q)->orWhere('last', 'like', $q)->orWhere('code', 'like', $q)
                ->orWhere('phone', 'like', $q)->orWhere('email', 'like', $q));
        }

        $today = $request->input('today', now()->toDateString());
        $onLeave = LeaveRequest::where('status', 'Approved')->where('date_from', '<=', $today)->where('date_to', '>=', $today)->pluck('employee_id')->all();
        $row = function (Employee $employee) use ($onLeave) {
            $row = $employee->toArray();
            if ($employee->isWorking() && in_array($employee->id, $onLeave, true)) {
                $row['status'] = 'On leave';
            }

            return $row;
        };

        if ($request->filled('per_page')) {
            $page = $query->paginate(min(200, (int) $request->input('per_page')));
            $page->setCollection($page->getCollection()->map($row));

            return $page;
        }

        return $query->get()->map($row)->all();
    }

    /**
     * Employee profile
     *
     * Everything about one employee: department, designation, manager, documents, education, experience,
     * status history, today's shift and this year's leave balances. The salary is only included with `hrm.payroll`.
     * Needs `hrm.view`.
     */
    public function show(Request $request, Employee $employee)
    {
        if (! $this->canSeeEmployee($request, $employee)) {
            return $this->notYourBranch();
        }

        return self::profile($employee, $request->user()->hasPermission('hrm.payroll'));
    }

    /** The full profile (also used by self-service). */
    public static function profile(Employee $employee, bool $withSalary): array
    {
        $employee->load(['branch:id,name', 'department:id,name', 'designation:id,name', 'manager:id,code,first,last', 'documents', 'educations', 'experiences', 'statusHistory']);
        $today = now(config('app.shop_timezone'))->toDateString();
        $year = (int) substr($today, 0, 4);

        $balances = [];
        foreach (LeavePolicy::forEmployee($employee)->where('track_balance', true) as $policy) {
            $balance = LeaveBalance::for($employee, $policy, $year);
            $balances[] = ['policy_id' => $policy->id, 'name' => $policy->name, 'available' => $balance->available, 'used' => $balance->used, 'pending' => $balance->pendingDays()];
        }

        $data = $employee->toArray();
        unset($data['rate'], $data['basis']);
        if ($withSalary) {
            $data['rate'] = $employee->rate;
            $data['basis'] = $employee->basis;
            $data['salary'] = $employee->salaryOn($today);
        } else {
            unset($data['bank_account_number'], $data['bank_bsb']);
        }
        $data['shift_today'] = Shift::forEmployee($employee, $today);
        $data['leave_balances'] = $balances;

        return $data;
    }

    /**
     * Add employee
     *
     * `pin` is 4 digits and must be different from every other employee's. `tfn` is saved masked.
     * With probation_months the status starts as Probation until start + months. Needs `hrm.create`.
     */
    public function store(Request $request)
    {
        $data = $request->validate($this->rules(null));

        if (! $this->isAtBranch($request, (int) $data['branch_id'])) {
            return $this->notYourBranch();
        }
        $problem = $this->pinProblem($data['pin'] ?? null);
        if ($problem) {
            return $this->failed($problem);
        }

        $fields = $this->fields($data);
        $fields['code'] = $data['code'] ?? Employee::nextCode();
        $fields['status'] = ! empty($fields['probation_end']) && $fields['probation_end'] > now()->toDateString() ? 'Probation' : 'Active';

        $employee = Employee::create($fields);
        $employee->statusHistory()->create(['from_status' => null, 'to_status' => $employee->status, 'date' => $fields['start'] ?? now()->toDateString(), 'note' => 'Joined', 'changed_by' => $this->by($request)]);

        return response()->json($employee->fresh()->load('branch:id,name'), 201);
    }

    /**
     * Update employee
     *
     * Leave `pin` out to keep the current one. Needs `hrm.edit`.
     */
    public function update(Request $request, Employee $employee)
    {
        $data = $request->validate($this->rules($employee));

        if (! $this->canSeeEmployee($request, $employee) || ! $this->isAtBranch($request, (int) $data['branch_id'])) {
            return $this->notYourBranch();
        }
        $problem = $this->pinProblem($data['pin'] ?? null, $employee);
        if ($problem) {
            return $this->failed($problem);
        }
        if (! empty($data['reporting_manager_id']) && (int) $data['reporting_manager_id'] === $employee->id) {
            return $this->failed('An employee cannot be their own manager.');
        }

        $fields = $this->fields($data);
        if (! empty($data['code'])) {
            $fields['code'] = $data['code'];
        }
        $employee->update($fields);

        return $employee->fresh()->load('branch:id,name');
    }

    /**
     * Change status
     *
     * Active, Probation or Inactive (Resigned and Terminated go through the exit endpoint). Kept in the status history.
     * Needs `hrm.edit`.
     */
    public function status(Request $request, Employee $employee)
    {
        $data = $request->validate(['status' => 'required|in:Active,Inactive,Probation', 'date' => 'nullable|date', 'note' => 'nullable|string|max:255']);

        if (! $this->canSeeEmployee($request, $employee)) {
            return $this->notYourBranch();
        }

        $employee->changeStatus($data['status'], $data['date'] ?? null, $data['note'] ?? null, $this->by($request));

        return $employee->fresh()->load('branch:id,name');
    }

    /**
     * Confirm after probation
     *
     * Sets the confirmation date and the status to Active. Needs `hrm.edit`.
     */
    public function confirm(Request $request, Employee $employee)
    {
        $data = $request->validate(['date' => 'nullable|date', 'note' => 'nullable|string|max:255']);
        if (! $this->canSeeEmployee($request, $employee)) {
            return $this->notYourBranch();
        }
        $date = $data['date'] ?? now()->toDateString();
        $employee->update(['confirmation_date' => $date]);
        $employee->changeStatus('Active', $date, $data['note'] ?? 'Confirmed', $this->by($request));

        return $employee->fresh();
    }

    /**
     * Resignation or termination
     *
     * `type` Resigned or Terminated. The employee still counts for attendance and payroll up to `exit_date`.
     * Needs `hrm.edit`.
     */
    public function exit(Request $request, Employee $employee)
    {
        $data = $request->validate([
            'type' => 'required|in:Resigned,Terminated',
            'notice_date' => 'nullable|date',
            'exit_date' => 'required|date',
            'reason' => 'nullable|string|max:255',
        ]);
        if (! $this->canSeeEmployee($request, $employee)) {
            return $this->notYourBranch();
        }
        $employee->update(['notice_date' => $data['notice_date'] ?? now()->toDateString(), 'exit_date' => $data['exit_date'], 'exit_reason' => $data['reason'] ?? null]);
        $employee->changeStatus($data['type'], $data['exit_date'], $data['reason'] ?? null, $this->by($request));

        return $employee->fresh();
    }

    /**
     * Delete employee
     *
     * A soft delete: the employee is hidden, their attendance and pay stay. Needs `hrm.delete`.
     */
    public function destroy(Request $request, Employee $employee)
    {
        if (! $this->canSeeEmployee($request, $employee)) {
            return $this->notYourBranch();
        }
        $employee->update(['user_id' => null]);
        $employee->delete();

        return response()->noContent();
    }

    /**
     * Upload photo
     *
     * Send the file in the "photo" form field (PNG, JPG or WebP, up to 3 MB). Needs `hrm.edit`.
     */
    public function photo(Request $request, Employee $employee)
    {
        $request->validate(['photo' => 'required|image|mimes:png,jpg,jpeg,webp|max:3072']);
        if (! $this->canSeeEmployee($request, $employee)) {
            return $this->notYourBranch();
        }
        if ($employee->photo) {
            Storage::disk('public')->delete($employee->photo);
        }
        $employee->update(['photo' => $request->file('photo')->store('employees', 'public')]);

        return $employee->fresh();
    }

    // ---------------------------------------------------------------------

    private function fields(array $data): array
    {
        $fields = [];
        foreach ([
            'first', 'last', 'gender', 'marital_status', 'nationality', 'phone', 'email', 'address', 'city', 'dob', 'emerg',
            'emergency_name', 'emergency_phone', 'emergency_relation', 'role', 'job_description', 'dept', 'department_id',
            'designation_id', 'reporting_manager_id', 'branch_id', 'overtime_policy_id', 'start', 'probation_months', 'type',
            'basis', 'rate', 'hours', 'super_fund', 'user_id', 'bank_name', 'bank_account_name', 'bank_bsb', 'bank_account_number',
        ] as $key) {
            if (array_key_exists($key, $data)) {
                $fields[$key] = $data[$key];
            }
        }
        $fields['hours'] = $data['hours'] ?? 0;

        // The probation ends probation_months after the start.
        if (array_key_exists('probation_months', $data) || array_key_exists('start', $data)) {
            $months = (int) ($data['probation_months'] ?? 0);
            $fields['probation_end'] = $months > 0 && ! empty($data['start']) ? Carbon::parse($data['start'])->addMonths($months)->toDateString() : null;
        }

        if (! empty($data['pin'])) {
            $fields['pin'] = $data['pin'];
        }

        // A full tax file number is never stored: only the last three digits.
        $tfn = preg_replace('/\D/', '', (string) ($data['tfn'] ?? ''));
        if (strlen($tfn) === 9) {
            $fields['tfn'] = '••• ••• '.substr($tfn, -3);
        }

        return $fields;
    }

    /** PINs are hashed, so each one has to be checked against the new PIN. */
    private function pinProblem(?string $pin, ?Employee $except = null): ?string
    {
        if (! $pin) {
            return null;
        }
        foreach (Employee::whereNotNull('pin')->get() as $other) {
            $otherPin = $other->getRawOriginal('pin');
            if ((! $except || $other->id !== $except->id) && $otherPin && Hash::check($pin, $otherPin)) {
                return 'That PIN is already used by another employee.';
            }
        }

        return null;
    }

    private function rules(?Employee $employee): array
    {
        return [
            'code' => ['nullable', 'string', 'max:20', Rule::unique('employees')->ignore($employee?->id)],
            'user_id' => ['nullable', 'string', 'max:64', Rule::unique('employees')->ignore($employee?->id)],
            'first' => 'required|string|max:80',
            'last' => 'required|string|max:80',
            'gender' => 'nullable|in:Male,Female,Other',
            'marital_status' => 'nullable|string|max:20',
            'nationality' => 'nullable|string|max:60',
            'phone' => 'required|string|max:40',
            'email' => 'nullable|email|max:190',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:80',
            'dob' => 'nullable|string|max:20',
            'emerg' => 'nullable|string|max:190',
            'emergency_name' => 'nullable|string|max:120',
            'emergency_phone' => 'nullable|string|max:40',
            'emergency_relation' => 'nullable|string|max:40',
            'role' => 'required|string|max:120',
            'job_description' => 'nullable|string|max:2000',
            'dept' => 'nullable|string|max:60',
            'department_id' => 'nullable|exists:departments,id',
            'designation_id' => 'nullable|exists:designations,id',
            'reporting_manager_id' => 'nullable|exists:employees,id',
            'overtime_policy_id' => 'nullable|exists:overtime_policies,id',
            'branch_id' => 'required|exists:branches,id',
            'start' => 'nullable|date',
            'probation_months' => 'nullable|integer|min:0|max:24',
            'type' => 'required|in:'.implode(',', Employee::TYPES),
            'basis' => 'required|in:Hourly,Salary',
            'rate' => 'required|numeric|gt:0',
            'hours' => 'nullable|numeric|min:0',
            'pin' => 'nullable|digits:4',
            'tfn' => 'nullable|string|max:20',
            'super_fund' => 'nullable|string|max:120',
            'bank_name' => 'nullable|string|max:80',
            'bank_account_name' => 'nullable|string|max:120',
            'bank_bsb' => 'nullable|string|max:20',
            'bank_account_number' => 'nullable|string|max:40',
        ];
    }
}
