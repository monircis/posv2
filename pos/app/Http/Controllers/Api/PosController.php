<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Category;
use App\Models\CoinSetting;
use App\Models\Contact;
use App\Models\CustomerGroup;
use App\Models\Employee;
use App\Models\PaymentSetting;
use App\Models\PosSetting;
use App\Models\PriceRule;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\StockSetting;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\Request;

/** The POS terminal screen. */
#[Group('POS', 'What the POS terminal screen reads.', weight: 15)]
class PosController extends Controller
{
    /**
     * Products, categories and customers
     *
     * Everything the POS can sell at one branch, not paged, so the screen can search as you type.
     * One row per product or variant. `price` always includes tax and today's price rule for this branch
     * (`normal_price` is the price without it, `price_rule` the rule's name, e.g. "Eid week +10%"). `stock` is null when there is no limit
     * (the product does not manage stock or may go negative). `category_id` is the top category.
     * Also `customers` (with their loyalty `coins`), `next_ref`, `max_discount`, `delivery_fee`, `use_scale`, `search_keypad`,
     * and the `payments` and `coins` settings.
     * A user with a branch always gets that branch. Needs `sell.view`.
     */
    #[QueryParameter('branch_id', 'For a user with no branch. Default: the default branch.', type: 'int')]
    public function catalog(Request $request)
    {
        $request->validate(['branch_id' => 'nullable|integer|exists:branches,id']);

        $userBranch = $request->user()->branch_id;
        $branchId = $userBranch ?? $request->input('branch_id') ?? $this->defaultBranchId();
        $branch = Branch::find($branchId, ['id', 'name']);

        if (! $branch) {
            return [
                'branch' => null, 'branches' => [], 'categories' => [], 'products' => [], 'customers' => [],
                'next_ref' => '', 'max_discount' => 0, 'delivery_fee' => Sale::DELIVERY_FEE, 'payments' => [], 'use_scale' => true, 'search_keypad' => true, 'require_register' => false, 'coins' => $this->coinSettings(), 'staff' => [],
            ];
        }

        $products = Product::with('unit:id,short_name', 'taxRate:id,rate', 'variants')
            ->where('active', true)
            ->where('available_at_pos', true)
            ->whereHas('branches', function ($q) use ($branch) {
                $q->where('branches.id', $branch->id);
            })
            ->orderByDesc('pos_favourite')
            ->orderBy('name')
            ->get();

        $stocks = ProductStock::where('branch_id', $branch->id)->get();
        $allCategories = Category::all()->keyBy('id');

        $rows = [];
        foreach ($products as $product) {
            if ($product->type === 'variable') {
                foreach ($product->variants as $variant) {
                    $rows[] = $this->row($product, $variant, $branch->id, $stocks, $allCategories);
                }
            } else {
                $rows[] = $this->row($product, null, $branch->id, $stocks, $allCategories);
            }
        }

        return [
            'branch' => $branch,
            // Only a user with no branch can switch.
            'branches' => $userBranch ? [] : Branch::where('active', true)->orderBy('name')->get(['id', 'name']),
            'categories' => $this->categories($rows, $allCategories),
            'products' => $rows,
            'customers' => $this->customers($branch->id),
            'customer_groups' => CustomerGroup::where(fn ($q) => $q->where('branch_id', $branch->id)->orWhereNull('branch_id'))
                ->orderBy('name')->get(['id', 'name', 'discount']),
            'next_ref' => Sale::nextRef($branch->id),
            'max_discount' => PosSetting::current()->max_discount_percent,
            'delivery_fee' => Sale::DELIVERY_FEE,
            'payments' => PaymentSetting::current()->only('card_surcharge', 'round_cash_5c', 'allow_split_payments'),
            'use_scale' => (bool) PosSetting::current()->use_scale,
            'search_keypad' => (bool) PosSetting::current()->search_keypad,
            'require_register' => (bool) PosSetting::current()->require_register,
            'coins' => $this->coinSettings(),
            'staff' => $this->staff($branch->id),
        ];
    }

    /** Staff a tip can be for: this branch's staff first, then the rest. Inactive staff are left out. */
    private function staff(int $branchId): array
    {
        $rows = [];
        $employees = Employee::where('status', '!=', 'Inactive')->orderBy('first')->orderBy('last')->get(['id', 'first', 'last', 'branch_id']);
        foreach ($employees as $e) {
            $rows[] = ['id' => $e->id, 'name' => trim($e->first.' '.$e->last), 'here' => (int) $e->branch_id === $branchId];
        }
        usort($rows, function ($a, $b) {
            return $b['here'] <=> $a['here'];
        });

        return $rows;
    }

    /**
     * Add a customer from the POS
     *
     * Quick add with `name` and `phone`; the rest is optional (email, customer group, address, note).
     * Always a Customer at the POS branch (a user with a branch: their own). `credit_limit` is only kept for
     * users with `contacts.create`. A mobile number that is already a customer here is refused (422).
     * Answers with the customer as the POS list shows it. Needs `sell.create`.
     */
    public function addCustomer(Request $request)
    {
        $data = $request->validate([
            'branch_id' => 'required|integer|exists:branches,id',
            'name' => 'required|string|max:190',
            'phone' => 'required|string|max:40',
            'email' => 'nullable|email|max:190',
            'customer_group_id' => 'nullable|integer|exists:customer_groups,id',
            'street' => 'nullable|string|max:190',
            'suburb' => 'nullable|string|max:120',
            'state' => 'nullable|string|max:10',
            'postcode' => 'nullable|string|max:10',
            'credit_limit' => 'nullable|numeric|min:0|max:1000000',
            'note' => 'nullable|string|max:255',
        ]);
        $user = $request->user();
        $branchId = $user->branch_id ? (int) $user->branch_id : (int) $data['branch_id'];

        // The same mobile (spaces ignored) is already a customer here.
        $digits = preg_replace('/\D/', '', $data['phone']);
        $same = Contact::forBranch($branchId)->where('type', '!=', 'Supplier')->get(['id', 'name', 'phone'])
            ->first(fn ($c) => $digits !== '' && preg_replace('/\D/', '', (string) $c->phone) === $digits);
        if ($same) {
            return response()->json(['message' => $same->name.' already has this mobile number. Pick them from the list.', 'id' => $same->id], 422);
        }

        if (! empty($data['customer_group_id'])) {
            $group = CustomerGroup::find($data['customer_group_id']);
            if ($group->branch_id && $group->branch_id !== $branchId) {
                return response()->json(['message' => 'The '.$group->name.' group is for another branch.'], 422);
            }
        }

        $contact = Contact::create([
            'type' => 'Customer',
            'branch_id' => $branchId,
            'name' => trim($data['name']),
            'contact_name' => trim($data['name']),
            'phone' => trim($data['phone']),
            'email' => $data['email'] ?? null,
            'customer_group_id' => $data['customer_group_id'] ?? null,
            'street' => $data['street'] ?? null,
            'suburb' => $data['suburb'] ?? null,
            'state' => $data['state'] ?? null,
            'postcode' => $data['postcode'] ?? null,
            'credit_limit' => $user->hasPermission('contacts.create') ? (float) ($data['credit_limit'] ?? 0) : 0,
            'note' => $data['note'] ?? null,
            'balance' => 0,
        ]);

        return response()->json($this->customerRow($contact->load('customerGroup:id,name,discount')), 201);
    }

    // ---------------------------------------------------------------------

    /** Customers to pick on the POS. Walk in is not one: a sale with no customer is a walk-in sale. */
    private function customers(int $branchId): array
    {
        $contacts = Contact::with('customerGroup:id,name,discount')
            ->forBranch($branchId)
            ->where('type', '!=', 'Supplier')
            ->where('name', '!=', 'Walk in')
            ->orderBy('name')
            ->get();

        $rows = [];
        foreach ($contacts as $contact) {
            $rows[] = $this->customerRow($contact);
        }

        return $rows;
    }

    /** A customer as the POS shows it. */
    private function customerRow(Contact $contact): array
    {
        $address = array_filter([$contact->street, $contact->suburb]);

        return [
            'id' => $contact->id,
            'name' => $contact->name,
            'phone' => (string) $contact->phone,
            'group' => $contact->customerGroup ? $contact->customerGroup->name : 'Retail',
            'discount' => $contact->customerGroup ? (float) $contact->customerGroup->discount : 0,
            'balance' => (float) $contact->balance,
            'limit' => (float) $contact->credit_limit,
            'coins' => (int) $contact->coins,
            'address' => implode(', ', $address),
        ];
    }

    /** Settings > Loyalty coins, for the pay screen. */
    private function coinSettings(): array
    {
        return CoinSetting::current()->only('enabled', 'earn_per_dollar', 'coin_value', 'min_redeem', 'max_redeem_percent');
    }

    private function defaultBranchId(): ?int
    {
        return StockSetting::current()->default_branch_id ?? Branch::where('active', true)->orderBy('id')->value('id');
    }

    private function row(Product $product, ?ProductVariant $variant, int $branchId, $stocks, $allCategories): array
    {
        $variantId = $variant ? $variant->id : null;
        $hasLimit = $product->manage_stock && ! $product->allow_negative_stock;
        $rule = PriceRule::forProduct($product->id, $branchId); // today's price rule at this branch, if any

        return [
            'id' => $variant ? 'v'.$variant->id : 'p'.$product->id,
            'product_id' => $product->id,
            'variant_id' => $variantId,
            'name' => $variant ? $product->name.' · '.$variant->name : $product->name,
            'sku' => $variant ? $variant->sku : $product->sku,
            'barcode' => $variant ? $variant->barcode : $product->barcode,
            'category_id' => $this->topCategoryId($product->category_id, $allCategories),
            'price' => $product->salePrice($variant, $branchId),
            'normal_price' => $product->posPrice($variant),
            'price_rule' => $rule ? $rule->name.' '.$rule->label() : null,
            'tax_rate' => $product->taxRate ? (float) $product->taxRate->rate : 0,
            'unit' => $product->unit ? $product->unit->short_name : 'ea',
            'stock' => $hasLimit ? (float) $stocks->where('product_id', $product->id)->where('variant_id', $variantId)->sum('qty') : null,
            'alert_qty' => (float) $product->alert_qty,
            'kitchen' => (bool) $product->send_to_kitchen,
        ];
    }

    /** Steaks is under Beef, so a Steaks product shows under Beef on the POS. */
    private function topCategoryId(?int $categoryId, $allCategories): ?int
    {
        $category = $allCategories->get($categoryId);
        while ($category && $category->parent_id) {
            $category = $allCategories->get($category->parent_id);
        }

        return $category ? $category->id : null;
    }

    /** Active top categories that have something to sell, with how many, e.g. Beef 12. */
    private function categories(array $rows, $allCategories): array
    {
        $counts = array_count_values(array_filter(array_column($rows, 'category_id')));

        $list = [];
        foreach ($allCategories->sortBy('name') as $category) {
            if (! $category->parent_id && $category->active && isset($counts[$category->id])) {
                $list[] = [
                    'id' => $category->id,
                    'name' => $category->name,
                    'code' => mb_substr($category->name, 0, 2),
                    'count' => $counts[$category->id],
                ];
            }
        }

        return $list;
    }
}
