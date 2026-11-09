<?php

namespace App\Http\Controllers\Api\Settings;

use App\Http\Controllers\Controller;
use App\Models\PriceRule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Settings > Price rules: sell dearer or cheaper on some dates. */
class PriceRuleController extends Controller
{
    /**
     * Price rules
     *
     * Newest first. Each rule has `status` (Running, Upcoming, Ended or Off), `label` (e.g. "+10%")
     * `products` (id and name; empty when `all_products` is true) and `branches` (empty when `all_branches` is true).
     */
    public function index()
    {
        $rules = PriceRule::with('products:id,name', 'branches:id,name')->orderByDesc('start_date')->orderByDesc('id')->get();

        return $rules->map(fn ($rule) => $this->row($rule));
    }

    public function show(int $id)
    {
        return $this->row(PriceRule::with('products:id,name', 'branches:id,name')->findOrFail($id));
    }

    /**
     * Add a price rule
     *
     * `amount` is a percent (`amount_type` percent) or dollars per unit (fixed), taken off or added to the
     * POS price including tax. With `all_products` false, send `product_ids`; with `all_branches` false,
     * send `branch_ids`.
     */
    public function store(Request $request)
    {
        $data = $this->validated($request);

        $rule = DB::transaction(function () use ($data) {
            $rule = PriceRule::create($data);
            $rule->products()->sync($data['all_products'] ? [] : $data['product_ids']);
            $rule->branches()->sync($data['all_branches'] ? [] : $data['branch_ids']);

            return $rule;
        });

        return response()->json($this->row($rule->load('products:id,name', 'branches:id,name')), 201);
    }

    /** Change a price rule. Sales already made keep their prices. */
    public function update(Request $request, int $id)
    {
        $rule = PriceRule::findOrFail($id);
        $data = $this->validated($request);

        DB::transaction(function () use ($rule, $data) {
            $rule->update($data);
            $rule->products()->sync($data['all_products'] ? [] : $data['product_ids']);
            $rule->branches()->sync($data['all_branches'] ? [] : $data['branch_ids']);
        });

        return $this->row($rule->load('products:id,name', 'branches:id,name'));
    }

    /** Delete a price rule. Sales already made keep their prices. */
    public function destroy(int $id)
    {
        PriceRule::findOrFail($id)->delete();

        return response()->noContent();
    }

    // ---------------------------------------------------------------------

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:80',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'change' => 'required|in:increase,decrease',
            'amount_type' => 'required|in:percent,fixed',
            'amount' => 'required|numeric|gt:0|max:99999',
            'all_products' => 'required|boolean',
            'product_ids' => 'exclude_if:all_products,true|required|array|min:1',
            'product_ids.*' => 'integer|exists:products,id',
            'all_branches' => 'required|boolean',
            'branch_ids' => 'exclude_if:all_branches,true|required|array|min:1',
            'branch_ids.*' => 'integer|exists:branches,id',
            'active' => 'boolean',
        ], [
            'end_date.after_or_equal' => 'The end date cannot be before the start date.',
            'product_ids.required' => 'Pick at least one product, or choose all products.',
            'branch_ids.required' => 'Pick at least one branch, or choose all branches.',
        ]);

        // A cut of more than 100% would make everything free.
        if ($data['change'] === 'decrease' && $data['amount_type'] === 'percent' && $data['amount'] > 100) {
            abort(response()->json(['message' => 'A decrease cannot be more than 100%.'], 422));
        }

        $data['product_ids'] = $data['product_ids'] ?? [];
        $data['branch_ids'] = $data['branch_ids'] ?? [];

        return $data;
    }

    private function row(PriceRule $rule): array
    {
        return [
            'id' => $rule->id,
            'name' => $rule->name,
            'start_date' => $rule->start_date->toDateString(),
            'end_date' => $rule->end_date->toDateString(),
            'change' => $rule->change,
            'amount_type' => $rule->amount_type,
            'amount' => $rule->amount,
            'all_products' => $rule->all_products,
            'active' => $rule->active,
            'products' => $rule->products->map(fn ($p) => ['id' => $p->id, 'name' => $p->name])->values(),
            'all_branches' => $rule->all_branches,
            'branches' => $rule->branches->map(fn ($b) => ['id' => $b->id, 'name' => $b->name])->values(),
            'label' => $rule->label(),
            'status' => $rule->status(),
        ];
    }
}
