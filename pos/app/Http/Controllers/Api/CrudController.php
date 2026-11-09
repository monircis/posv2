<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * List / create / show / update / delete for one ERP table.
 * A resource controller only names its model and its validation rules.
 * Permissions are checked by the routes (see routes/api.php).
 */
abstract class CrudController extends Controller
{
    /** @var class-string<Model> */
    protected string $model;

    /** List order: column (default: the primary key) and direction (default: newest first). */
    protected ?string $orderBy = null;

    protected string $direction = 'desc';

    /** Validation rules for create; on update each rule only applies when the field is sent. */
    protected function rules(): array
    {
        return [];
    }

    public function index()
    {
        $model = new $this->model;

        return $this->model::orderBy($this->orderBy ?? $model->getKeyName(), $this->direction)->get();
    }

    public function store(Request $request)
    {
        $request->validate($this->rules());

        $model = new $this->model;
        $key = $model->getKeyName();
        if ($request->filled($key) && $this->model::whereKey($request->input($key))->exists()) {
            return response()->json(['message' => $request->input($key).' already exists.'], 422);
        }

        $record = $this->model::create($request->only($model->getFillable()));

        return response()->json($record->fresh(), 201);
    }

    public function show(string $id)
    {
        return $this->find($id);
    }

    public function update(Request $request, string $id)
    {
        $record = $this->find($id);
        $request->validate(array_map(fn ($rule) => 'sometimes|'.$rule, $this->rules()));

        // The key identifies the record and is never changed.
        $fields = array_diff($record->getFillable(), [$record->getKeyName()]);
        $record->update($request->only($fields));

        return $record->fresh();
    }

    public function destroy(string $id)
    {
        $this->find($id)->delete();

        return response()->noContent();
    }

    protected function find(string $id): Model
    {
        return $this->model::findOrFail($id);
    }
}
