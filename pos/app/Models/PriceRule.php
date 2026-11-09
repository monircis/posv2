<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A price change for some dates, e.g. "Eid week: +10% on all products at all branches".
 * A rule is for all products or chosen products, and for all branches or chosen branches.
 *
 * When two rules are on for the same product, branch and day, the closer one wins:
 *   1. a rule for chosen products wins over an all-products rule,
 *   2. then a rule for chosen branches wins over an all-branches rule,
 *   3. then the newest rule wins.
 * Only one rule is used.
 */
class PriceRule extends Model
{
    // The shop's own date decides which rules are on (the app itself runs in UTC).
    const TIMEZONE = 'Australia/Sydney';

    protected $fillable = [
        'name', 'start_date', 'end_date', 'change', 'amount_type', 'amount', 'all_products', 'all_branches', 'active',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
            'amount' => 'float',
            'all_products' => 'boolean',
            'all_branches' => 'boolean',
            'active' => 'boolean',
        ];
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'price_rule_products');
    }

    public function branches()
    {
        return $this->belongsToMany(Branch::class, 'price_rule_branches');
    }

    /** Today in the shop, e.g. 2026-11-10. */
    public static function today(): string
    {
        return now(self::TIMEZONE)->toDateString();
    }

    // Today's rules, loaded once per request.
    private static ?array $todaysRules = null;

    public static function todaysRules(): array
    {
        if (self::$todaysRules === null) {
            self::$todaysRules = self::with('products:id', 'branches:id')
                ->where('active', true)
                ->whereDate('start_date', '<=', self::today())
                ->whereDate('end_date', '>=', self::today())
                ->orderBy('all_products')   // chosen products first
                ->orderBy('all_branches')   // then chosen branches
                ->orderByDesc('id')         // then the newest
                ->get()
                ->all();
        }

        return self::$todaysRules;
    }

    /** The rule used for this product at this branch today, or null. */
    public static function forProduct(int $productId, ?int $branchId): ?PriceRule
    {
        foreach (self::todaysRules() as $rule) {
            $productOk = $rule->all_products || $rule->products->contains('id', $productId);
            $branchOk = $rule->all_branches || ($branchId && $rule->branches->contains('id', $branchId));

            if ($productOk && $branchOk) {
                return $rule;
            }
        }

        return null;
    }

    /** The price after this rule, never below 0. */
    public function apply(float $price): float
    {
        $change = $this->amount_type === 'percent' ? $price * $this->amount / 100 : $this->amount;
        $new = $this->change === 'increase' ? $price + $change : $price - $change;

        return max(0, round($new, 2));
    }

    /** "+10%", "-$2.00" */
    public function label(): string
    {
        $sign = $this->change === 'increase' ? '+' : '-';

        return $this->amount_type === 'percent'
            ? $sign.rtrim(rtrim(number_format($this->amount, 2, '.', ''), '0'), '.').'%'
            : $sign.'$'.number_format($this->amount, 2);
    }

    /** Running, Upcoming, Ended or Off. */
    public function status(): string
    {
        if (! $this->active) {
            return 'Off';
        }
        if ($this->start_date->toDateString() > self::today()) {
            return 'Upcoming';
        }
        if ($this->end_date->toDateString() < self::today()) {
            return 'Ended';
        }

        return 'Running';
    }
}
