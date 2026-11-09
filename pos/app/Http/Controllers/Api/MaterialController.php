<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Material;
use App\Models\MaterialStock;
use App\Models\RecipeItem;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Manufacturing > Materials: raw materials and their stock per branch. */
#[Group('Manufacturing', 'Materials, recipes and production orders. Starting an order takes the materials; finishing it adds the product to stock.', weight: 17)]
class MaterialController extends Controller
{
    /**
     * List materials
     *
     * Sorted by name. `on_hand` is the stock at the chosen branch, or at all branches.
     * A user with a branch only sees their own. Needs `mfg.view`.
     */
    #[QueryParameter('branch_id', 'Only the stock at this branch.', type: 'int')]
    public function index(Request $request)
    {
        $branchId = $request->user()->branch_id ?? $request->input('branch_id');

        $rows = [];
        foreach (Material::with('stocks')->orderBy('name')->get() as $material) {
            $row = $material->toArray();
            $stocks = $branchId ? $material->stocks->where('branch_id', (int) $branchId) : $material->stocks;
            $row['on_hand'] = round($stocks->sum('qty'), 3);
            unset($row['stocks']);
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Add material
     *
     * Names must be unique. Needs `mfg.create`.
     */
    public function store(Request $request)
    {
        $material = Material::create($request->validate($this->rules()));

        return response()->json($material, 201);
    }

    /**
     * Update material
     *
     * A new cost changes recipes and planned orders, not orders already started. Needs `mfg.edit`.
     */
    public function update(Request $request, Material $material)
    {
        $material->update($request->validate($this->rules($material)));

        return $material;
    }

    /**
     * Delete material
     *
     * Refused (422) while a recipe uses it or it has stock. Needs `mfg.delete`.
     */
    public function destroy(Material $material)
    {
        if (RecipeItem::where('material_id', $material->id)->exists()) {
            return response()->json(['message' => 'A recipe uses '.$material->name.'. Take it off the recipe first.'], 422);
        }
        if (MaterialStock::where('material_id', $material->id)->where('qty', '!=', 0)->exists()) {
            return response()->json(['message' => $material->name.' still has stock. Take it out first.'], 422);
        }
        if (DB::table('production_order_items')->where('material_id', $material->id)->exists()) {
            return response()->json(['message' => 'Old production orders used '.$material->name.', so it cannot be deleted.'], 422);
        }

        $material->delete();

        return response()->noContent();
    }

    /**
     * Change material stock
     *
     * `change` is + for stock in and - for stock out. It cannot go below 0.
     * Needs `mfg.edit` (a user with a branch: only that branch).
     */
    public function adjust(Request $request, Material $material)
    {
        $data = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'change' => 'required|numeric|not_in:0',
            'note' => 'nullable|string|max:190',
        ]);

        $userBranch = $request->user()->branch_id;
        if ($userBranch && (int) $userBranch !== (int) $data['branch_id']) {
            return response()->json(['message' => 'You can only change stock at your own branch.'], 403);
        }

        $userName = $request->user()->name;

        $problem = DB::transaction(function () use ($data, $material, $userName) {
            $onHand = (float) MaterialStock::where('material_id', $material->id)->where('branch_id', $data['branch_id'])->lockForUpdate()->value('qty');
            if ($onHand + $data['change'] < -0.0005) {
                return 'Only '.$onHand.' '.$material->unit.' of '.$material->name.' is there.';
            }

            Material::record($material->id, $data['branch_id'], (float) $data['change'], $data['change'] > 0 ? 'Stock in' : 'Stock out', null, $data['note'] ?? null, $userName);

            return null;
        });

        if ($problem) {
            return response()->json(['message' => $problem], 422);
        }

        return response()->json(['on_hand' => (float) MaterialStock::where('material_id', $material->id)->where('branch_id', $data['branch_id'])->value('qty')]);
    }

    private function rules(?Material $material = null): array
    {
        return [
            'name' => ['required', 'string', 'max:120', Rule::unique('materials')->ignore($material)],
            'unit' => 'required|string|max:20',
            'cost' => 'required|numeric|min:0',
        ];
    }
}
