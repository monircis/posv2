<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Expense;
use App\Models\MaterialStock;
use App\Models\ProductionOrder;
use App\Models\ProductStock;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\StockAdjustment;
use App\Models\StockTransfer;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Branches: Settings > Branches & locations, the user form and the sign-in page. */
class BranchController extends Controller
{
    public function index()
    {
        $counts = $this->userCounts() ?? [];
        $branches = Branch::orderBy('id')->get();

        $rows = [];
        foreach ($branches as $branch) {
            $rows[] = $this->row($branch, $counts);
        }

        return $rows;
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules());
        $data['code'] = $this->uniqueCode($data['code'] ?? $data['name']);
        $branch = Branch::create($data);

        return response()->json($this->row($branch, []), 201);
    }

    public function update(Request $request, Branch $branch)
    {
        $data = $request->validate($this->rules($branch));
        $branch->update($data);

        return $this->row($branch->fresh(), $this->userCounts() ?? []);
    }

    public function destroy(Branch $branch)
    {
        $counts = $this->userCounts();
        if ($counts === null) {
            return response()->json(['message' => 'Cannot check the branch users: the auth service is not running.'], 503);
        }
        $staff = $counts[$branch->id] ?? 0;

        if ($staff > 0) {
            return response()->json(['message' => $branch->name.' still has '.$staff.' '.Str::plural('user', $staff).'. Move them to another branch first.'], 422);
        }
        if (ProductStock::where('branch_id', $branch->id)->where('qty', '!=', 0)->exists()) {
            return response()->json(['message' => $branch->name.' still has product stock. Move or adjust it to 0 first.'], 422);
        }
        if (StockTransfer::where('from_branch_id', $branch->id)->orWhere('to_branch_id', $branch->id)->exists()) {
            return response()->json(['message' => $branch->name.' has stock transfers, so it cannot be deleted. Switch it off instead.'], 422);
        }
        if (StockAdjustment::where('branch_id', $branch->id)->exists()) {
            return response()->json(['message' => $branch->name.' has stock adjustments, so it cannot be deleted. Switch it off instead.'], 422);
        }
        if (Expense::withTrashed()->where('branch_id', $branch->id)->exists()) {
            return response()->json(['message' => $branch->name.' has expenses, so it cannot be deleted. Switch it off instead.'], 422);
        }
        if (ProductionOrder::where('branch_id', $branch->id)->exists() || MaterialStock::where('branch_id', $branch->id)->exists()) {
            return response()->json(['message' => $branch->name.' has production orders or materials, so it cannot be deleted. Switch it off instead.'], 422);
        }
        if (Sale::where('branch_id', $branch->id)->exists()) {
            return response()->json(['message' => $branch->name.' has sales, so it cannot be deleted. Switch it off instead.'], 422);
        }
        if (Purchase::where('branch_id', $branch->id)->exists()) {
            return response()->json(['message' => $branch->name.' has purchases, so it cannot be deleted. Switch it off instead.'], 422);
        }
        $branch->delete();

        return response()->noContent();
    }

    /** One branch for the frontend, with users_count added. */
    private function row(Branch $branch, array $counts): array
    {
        $row = $branch->toArray();
        $row['users_count'] = $counts[$branch->id] ?? 0;

        return $row;
    }

    /**
     * How many users each branch has, e.g. [1 => 2, 3 => 6].
     * Users are in the auth service, so we ask it (GET /api/internal/branch-user-counts).
     * Returns null if the auth service is down.
     */
    private function userCounts(): ?array
    {
        try {
            $response = Http::withHeaders(['X-Service-Key' => config('services.auth.key'), 'Accept' => 'application/json'])
                ->timeout(5)
                ->get(config('services.auth.url').'/internal/branch-user-counts');
        } catch (Exception $e) {
            return null;
        }

        if ($response->failed()) {
            return null;
        }

        return $response->json();
    }

    private function rules(?Branch $branch = null): array
    {
        return [
            'name' => [$branch ? 'sometimes' : 'required', 'string', 'max:80', Rule::unique('branches')->ignore($branch)],
            'code' => ['sometimes', 'nullable', 'string', 'max:20', 'alpha_dash', Rule::unique('branches')->ignore($branch)],
            'address' => 'sometimes|nullable|string|max:190',
            'invoice_prefix' => 'sometimes|nullable|string|max:10',
            'active' => 'sometimes|boolean',
        ];
    }

    /** "Bankstown shop" -> "bankstown-shop", with -2, -3 ... if taken. */
    private function uniqueCode(string $text): string
    {
        $base = rtrim(substr(Str::slug($text), 0, 16), '-') ?: 'branch';
        $code = $base;
        for ($i = 2; Branch::where('code', $code)->exists(); $i++) {
            $code = $base.'-'.$i;
        }

        return $code;
    }
}
