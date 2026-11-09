<?php

namespace App\Http\Controllers\Api\Hrm;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * List / create / update / delete for an HRM setup table with a branch_id
 * (departments, shifts, holidays, leave, overtime and deduction policies).
 * Like CrudController, plus the branch rule: a user kept to a branch sees their branch's rows and the all-branch rows,
 * and can only add or change rows of their own branch.
 * A child controller names the model, the validation rules and the list order.
 */
abstract class HrmSetupController extends HrmController
{
    /** @var class-string<Model> */
    protected string $model;

    protected string $orderBy = 'name';

    abstract protected function rules(Request $request, ?Model $record): array;

    /** List. ?branch_id shows one branch's rows plus the all-branch rows; ?active=1 only the active ones. */
    public function index(Request $request)
    {
        $query = $this->model::orderBy($this->orderBy)->orderBy('id');
        $branch = $this->branchFor($request);
        if ($branch) {
            $query->where(fn ($q) => $q->whereNull('branch_id')->orWhere('branch_id', $branch));
        }
        if ($request->boolean('active')) {
            $query->where('active', true);
        }

        return $query->get();
    }

    public function show(Request $request, string $id)
    {
        $record = $this->model::findOrFail($id);
        if ($record->branch_id && ! $this->isAtBranch($request, $record->branch_id)) {
            return $this->notYourBranch();
        }

        return $record;
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules($request, null));
        if (! $this->isAtBranch($request, $data['branch_id'] ?? null)) {
            return $this->failed('You can only add these for your own branch.', 403);
        }
        $problem = $this->problem($data, null);
        if ($problem) {
            return $this->failed($problem);
        }

        return response()->json($this->model::create($data)->fresh(), 201);
    }

    public function update(Request $request, string $id)
    {
        $record = $this->model::findOrFail($id);
        $data = $request->validate($this->rules($request, $record));
        if (! $this->isAtBranch($request, $record->branch_id) || ! $this->isAtBranch($request, $data['branch_id'] ?? null)) {
            return $this->failed('You can only change these for your own branch.', 403);
        }
        $problem = $this->problem($data, $record);
        if ($problem) {
            return $this->failed($problem);
        }
        $record->update($data);

        return $record->fresh();
    }

    public function destroy(Request $request, string $id)
    {
        $record = $this->model::findOrFail($id);
        if (! $this->isAtBranch($request, $record->branch_id)) {
            return $this->failed('You can only delete these for your own branch.', 403);
        }
        $problem = $this->deleteProblem($record);
        if ($problem) {
            return $this->failed($problem);
        }
        $record->delete();

        return response()->noContent();
    }

    /** Extra checks before saving; a message stops the save. */
    protected function problem(array $data, ?Model $record): ?string
    {
        return null;
    }

    /** Extra checks before deleting; a message stops it. */
    protected function deleteProblem(Model $record): ?string
    {
        return null;
    }
}
