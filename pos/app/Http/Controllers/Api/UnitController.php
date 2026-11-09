<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Unit;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Products > Units. Products point to a unit by unit_id. */
#[Group('Units', 'Units products are sold in, e.g. Kilogram (kg), Each (ea).', weight: 12)]
class UnitController extends Controller
{
    /**
     * List units
     *
     * Sorted by name. `products_count` = products sold in this unit. Needs `products.view` or `sell.view`.
     */
    public function index()
    {
        return Unit::withCount('products')->orderBy('name')->get();
    }

    /**
     * Add unit
     *
     * `allow_decimals`: true for weight or volume (1.25 kg), false for whole items. Needs `products.create`.
     */
    public function store(Request $request)
    {
        $data = $request->validate($this->rules());

        $unit = Unit::create($data);

        return response()->json($unit->fresh(), 201);
    }

    /**
     * Show unit
     *
     * Needs `products.view` or `sell.view`.
     */
    public function show(Unit $unit)
    {
        return $unit;
    }

    /**
     * Update unit
     *
     * Send all fields. Needs `products.edit`.
     */
    public function update(Request $request, Unit $unit)
    {
        $data = $request->validate($this->rules($unit));

        $unit->update($data);

        return $unit->fresh();
    }

    /**
     * Delete unit
     *
     * Refused (422) while products use it. Needs `products.delete`.
     */
    public function destroy(Unit $unit)
    {
        if ($unit->products()->exists()) {
            return response()->json(['message' => 'Products still use '.$unit->name.'. Change their unit first.'], 422);
        }

        $unit->delete();

        return response()->noContent();
    }

    private function rules(?Unit $unit = null): array
    {
        return [
            'name' => ['required', 'string', 'max:60', Rule::unique('units')->ignore($unit)],
            'short_name' => ['required', 'string', 'max:10', Rule::unique('units')->ignore($unit)],
            'allow_decimals' => 'boolean',
            'active' => 'boolean',
        ];
    }
}
