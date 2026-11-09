<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Material;
use App\Models\MaterialStock;
use App\Models\ProductionOrder;
use App\Models\Recipe;
use App\Models\StockMovement;
use App\Models\StockSetting;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Manufacturing > Production orders.
 * Start takes the materials from the order's branch. Finish adds the product there. Cancel puts materials back.
 * A user with a branch only sees and changes orders of that branch.
 */
#[Group('Manufacturing', weight: 17)]
class ProductionOrderController extends Controller
{
    /** Output over this share of what was expected is refused: it is usually a typing mistake. */
    private const MAX_YIELD = 1.1;

    /**
     * List production orders
     *
     * 25 per page, newest first. Also returns `counts` for the tabs and `month`: this month's finished orders.
     * Needs `mfg.view`.
     */
    #[QueryParameter('status', 'planned, progress, done or cancelled.', type: 'string')]
    #[QueryParameter('branch_id', 'Only this branch.', type: 'int')]
    public function index(Request $request)
    {
        $query = $this->filtered($request);
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $page = $query->with('branch:id,name')->orderBy('date', 'desc')->orderBy('id', 'desc')->paginate(25);

        $counts = ['all' => $this->filtered($request)->count()];
        foreach (['planned', 'progress', 'done', 'cancelled'] as $status) {
            $counts[$status] = $this->filtered($request)->where('status', $status)->count();
        }

        return [
            'data' => $page->items(),
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'total' => $page->total(),
            'counts' => $counts,
            'month' => $this->monthTotals($request),
        ];
    }

    /**
     * Form defaults
     *
     * The next reference, the branch the form starts with, and the recipes with `on_hand` for each
     * material at that branch. Needs `mfg.create`.
     */
    #[QueryParameter('branch_id', 'The branch to show stock for. Default: the user\'s, or the default branch.', type: 'int')]
    public function formData(Request $request)
    {
        $branchId = (int) ($request->user()->branch_id ?? $request->input('branch_id') ?? StockSetting::current()->default_branch_id);
        $stocks = MaterialStock::where('branch_id', $branchId)->pluck('qty', 'material_id');

        $recipes = [];
        foreach (Recipe::with('items.material', 'product.taxRate')->orderBy('name')->get() as $recipe) {
            $row = $recipe->toRow();
            foreach ($row['items'] as $i => $item) {
                $row['items'][$i]['on_hand'] = (float) ($stocks[$item['material_id']] ?? 0);
            }
            $recipes[] = $row;
        }

        return ['next_ref' => ProductionOrder::nextRef(), 'branch_id' => $branchId, 'recipes' => $recipes];
    }

    /**
     * Add production order
     *
     * Saved as planned. With `start` true it also starts straight away, which needs enough materials.
     * Needs `mfg.create` (a user with a branch: only that branch).
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'recipe_id' => 'required|exists:recipes,id',
            'branch_id' => 'required|exists:branches,id',
            'batches' => 'required|integer|min:1|max:1000',
            'date' => 'required|date',
            'start' => 'boolean',
        ]);

        if (! $this->isAtBranch($request, (int) $data['branch_id'])) {
            return $this->notYourBranch();
        }

        $userName = $request->user()->name;

        $order = DB::transaction(function () use ($data, $userName) {
            $recipe = Recipe::with('items.material')->find($data['recipe_id']);

            $order = ProductionOrder::create([
                'ref' => ProductionOrder::nextRef(),
                'recipe_id' => $recipe->id,
                'branch_id' => $data['branch_id'],
                'recipe_name' => $recipe->name,
                'unit' => $recipe->unit,
                'batches' => $data['batches'],
                'date' => $data['date'],
                'status' => 'planned',
                'expected_qty' => $recipe->output_qty * $data['batches'],
                'material_cost' => round($recipe->batchCost() * $data['batches'], 2),
                'created_by' => $userName,
                'user_id' => (string) request()->user()->id,
            ]);
            $order->logs()->create(['what' => 'Planned by '.$userName, 'kind' => 'neutral']);

            if (! empty($data['start'])) {
                $this->startOrder($order, $userName);
            }

            return $order;
        });

        return response()->json($this->details($order), 201);
    }

    /**
     * Show production order
     *
     * With its materials and activity. A planned order shows what it will use and `on_hand` at its branch.
     * Needs `mfg.view` (a user with a branch: only that branch).
     */
    public function show(Request $request, ProductionOrder $productionOrder)
    {
        if (! $this->isMine($request, $productionOrder, 'mfg')) {
            return $this->notMine();
        }
        if (! $this->isAtBranch($request, $productionOrder->branch_id)) {
            return $this->notYourBranch();
        }

        return $this->details($productionOrder);
    }

    /**
     * Start production
     *
     * Takes the materials from the order's branch and fixes the cost. Refused if there is not enough.
     * Needs `mfg.edit` (a user with a branch: only that branch).
     */
    public function start(Request $request, ProductionOrder $productionOrder)
    {
        if (! $this->isMine($request, $productionOrder, 'mfg')) {
            return $this->notMine();
        }
        if (! $this->isAtBranch($request, $productionOrder->branch_id)) {
            return $this->notYourBranch();
        }

        $userName = $request->user()->name;

        $order = DB::transaction(function () use ($productionOrder, $userName) {
            $locked = ProductionOrder::lockForUpdate()->find($productionOrder->id);
            if ($locked->status !== 'planned') {
                throw ValidationException::withMessages(['status' => [$locked->ref.' has already been started or closed.']]);
            }
            $this->startOrder($locked, $userName);

            return $locked;
        });

        return $this->details($order);
    }

    /**
     * Finish production
     *
     * Adds `actual_qty` of the recipe's product to stock at the order's branch. Needs a product linked to the
     * recipe and sold at that branch. Over 110% of what was expected is refused.
     * Needs `mfg.edit` (a user with a branch: only that branch).
     */
    public function finish(Request $request, ProductionOrder $productionOrder)
    {
        if (! $this->isMine($request, $productionOrder, 'mfg')) {
            return $this->notMine();
        }
        if (! $this->isAtBranch($request, $productionOrder->branch_id)) {
            return $this->notYourBranch();
        }

        $data = $request->validate(['actual_qty' => 'required|numeric|gt:0']);
        $userName = $request->user()->name;

        $order = DB::transaction(function () use ($data, $productionOrder, $userName) {
            $locked = ProductionOrder::with('branch', 'recipe.product.unit')->lockForUpdate()->find($productionOrder->id);
            $actual = round((float) $data['actual_qty'], 3);
            $this->checkCanFinish($locked, $actual);

            $recipe = $locked->recipe;
            StockMovement::record($recipe->product_id, $recipe->variant_id, $locked->branch_id, $actual, 'Production', $locked->ref, $recipe->name, $userName);

            $locked->status = 'done';
            $locked->actual_qty = $actual;
            $locked->finished_at = now();
            $locked->save();
            $locked->logs()->create(['what' => 'Finished by '.$userName.' · '.$actual.' '.$locked->unit.' added to '.$locked->branch->name, 'kind' => 'success']);

            return $locked;
        });

        return $this->details($order);
    }

    /**
     * Cancel production order
     *
     * A started order puts its materials back. A finished order cannot be cancelled.
     * Needs `mfg.edit` (a user with a branch: only that branch).
     */
    public function cancel(Request $request, ProductionOrder $productionOrder)
    {
        if (! $this->isMine($request, $productionOrder, 'mfg')) {
            return $this->notMine();
        }
        if (! $this->isAtBranch($request, $productionOrder->branch_id)) {
            return $this->notYourBranch();
        }

        $userName = $request->user()->name;

        $order = DB::transaction(function () use ($productionOrder, $userName) {
            $locked = ProductionOrder::with('items')->lockForUpdate()->find($productionOrder->id);
            if (! in_array($locked->status, ['planned', 'progress'], true)) {
                throw ValidationException::withMessages(['status' => [$locked->ref.' is closed, so it cannot be cancelled.']]);
            }

            $what = 'Cancelled by '.$userName;
            if ($locked->status === 'progress' && $locked->materials_taken) {
                foreach ($locked->items as $item) {
                    Material::record($item->material_id, $locked->branch_id, $item->qty, 'Production cancelled', $locked->ref, null, $userName);
                }
                $what .= ' · materials put back';
            }

            $locked->status = 'cancelled';
            $locked->save();
            $locked->logs()->create(['what' => $what, 'kind' => 'error']);

            return $locked;
        });

        return $this->details($order);
    }

    // ---------------------------------------------------------------------

    /** Take the materials and copy what was used. Throwing undoes the whole transaction. */
    private function startOrder(ProductionOrder $order, string $userName): void
    {
        $recipe = Recipe::with('items.material')->find($order->recipe_id);

        foreach ($recipe->items as $item) {
            $need = round($item->qty * $order->batches, 3);
            // Locking the row stops two orders at the same moment using the same stock.
            $onHand = (float) MaterialStock::where('material_id', $item->material_id)->where('branch_id', $order->branch_id)->lockForUpdate()->value('qty');
            if ($need > $onHand) {
                throw ValidationException::withMessages(['batches' => ['Not enough '.$item->material->name.': needs '.$need.' '.$item->material->unit.', only '.$onHand.' at this branch.']]);
            }

            $order->items()->create([
                'material_id' => $item->material_id,
                'name' => $item->material->name,
                'unit' => $item->material->unit,
                'qty' => $need,
                'cost' => $item->material->cost,
            ]);
            Material::record($item->material_id, $order->branch_id, -$need, 'Production', $order->ref, null, $userName);
        }

        $order->status = 'progress';
        $order->materials_taken = true;
        $order->material_cost = round($recipe->batchCost() * $order->batches, 2);
        $order->expected_qty = $recipe->output_qty * $order->batches;
        $order->save();
        $order->logs()->create(['what' => 'Started by '.$userName.' · materials taken', 'kind' => 'info']);
    }

    private function checkCanFinish(ProductionOrder $order, float $actual): void
    {
        $problem = null;
        $recipe = $order->recipe;
        $product = $recipe->product;

        if ($order->status !== 'progress') {
            $problem = $order->ref.' is not in progress.';
        } elseif (! $product) {
            $problem = 'Link '.$recipe->name.' to a product on the Recipes tab first, so the output can go into stock.';
        } elseif (! $product->manage_stock) {
            $problem = $product->name.' does not manage stock, so the output cannot be added.';
        } elseif (! $product->branches()->where('branches.id', $order->branch_id)->exists()) {
            $problem = $product->name.' is not sold at '.$order->branch->name.'. Tick the branch in Edit product first.';
        } elseif ($product->type === 'variable' && ! $recipe->variant_id) {
            $problem = 'The variant on this recipe no longer exists. Edit the recipe first.';
        } elseif ($actual > $order->expected_qty * self::MAX_YIELD) {
            $problem = 'That is more than 110% of the expected '.$order->expected_qty.' '.$order->unit.'. Check the weight.';
        } elseif ($product->unit && ! $product->unit->allow_decimals && floor($actual) != $actual) {
            $problem = $product->name.' is counted in whole units.';
        }

        if ($problem) {
            throw ValidationException::withMessages(['actual_qty' => [$problem]]);
        }
    }

    /** The order with everything the details panel needs. */
    private function details(ProductionOrder $order): array
    {
        $order->load('branch:id,name', 'recipe.product.taxRate', 'recipe.items.material', 'items', 'logs');
        $recipe = $order->recipe;

        $row = $order->toArray();
        $row['sell_price'] = $recipe->sellPrice();
        $row['has_product'] = (bool) $recipe->product_id;
        $row['logs'] = $order->logs->sortByDesc('id')->values();
        unset($row['recipe']);

        // A planned order has not used anything yet: show what the recipe will use.
        if ($order->status === 'planned') {
            $stocks = MaterialStock::where('branch_id', $order->branch_id)->pluck('qty', 'material_id');
            $row['items'] = [];
            foreach ($recipe->items as $item) {
                $row['items'][] = [
                    'material_id' => $item->material_id,
                    'name' => $item->material->name,
                    'unit' => $item->material->unit,
                    'qty' => round($item->qty * $order->batches, 3),
                    'cost' => $item->material->cost,
                    'on_hand' => (float) ($stocks[$item->material_id] ?? 0),
                ];
            }
        }

        return $row;
    }

    /** Orders this user may see. */
    private function filtered(Request $request)
    {
        $query = ProductionOrder::query();
        $this->onlyMine($query, $request, 'mfg');

        $userBranch = $request->user()->branch_id;
        if ($userBranch) {
            $query->where('branch_id', $userBranch);
        } elseif ($request->filled('branch_id')) {
            $query->where('branch_id', $request->input('branch_id'));
        }

        return $query;
    }

    /** This month's finished orders: output per unit, average yield and material cost. */
    private function monthTotals(Request $request): array
    {
        $orders = $this->filtered($request)
            ->where('status', 'done')
            ->where('date', '>=', now()->startOfMonth()->toDateString())
            ->where('date', '<=', now()->endOfMonth()->toDateString())
            ->get();

        $output = [];
        $yields = 0;
        foreach ($orders as $order) {
            $output[$order->unit] = round(($output[$order->unit] ?? 0) + $order->actual_qty, 3);
            $yields += $order->expected_qty > 0 ? $order->actual_qty / $order->expected_qty : 0;
        }

        return [
            'finished' => $orders->count(),
            'output' => $output,
            'average_yield' => $orders->count() ? round($yields / $orders->count() * 100, 1) : null,
            'material_cost' => round((float) $orders->sum('material_cost'), 2),
        ];
    }

    /** A user with no branch works everywhere; otherwise only at their own branch. */
    private function isAtBranch(Request $request, int $branchId): bool
    {
        $userBranch = $request->user()->branch_id;

        return ! $userBranch || (int) $userBranch === $branchId;
    }

    private function notYourBranch()
    {
        return response()->json(['message' => 'You can only work with production orders of your own branch.'], 403);
    }
}
