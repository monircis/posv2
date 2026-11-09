<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Payment Accounts screen: cash drawers, bank accounts, cards and wallets.
 * An account belongs to one branch (branch_id), or to the whole business when branch_id is null.
 * `defs` are what it is the default for: posCash, posCard, expenses, purchases. A default is per branch:
 * a branch's own default comes first, then a whole-business one.
 */
class PaymentAccount extends Record
{
    protected $table = 'payment_accounts';

    public const DEFAULTS = ['posCash', 'posCard', 'expenses', 'purchases'];

    protected $fillable = [
        'id', 'name', 'kind', 'branch_id', 'num', 'bsb',
        'limit', 'bal', 'active', 'defs', 'tx',
    ];

    protected function casts(): array
    {
        return [
            'branch_id' => 'integer',
            'limit' => 'float',
            'bal' => 'float',
            'active' => 'boolean',
            'defs' => 'array',
            'tx' => 'array',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** The default account for $def (e.g. posCard) at a branch: the branch's own, else a whole-business one. Locked for update. */
    public static function defaultFor(string $def, ?int $branchId): ?PaymentAccount
    {
        $query = static::lockForUpdate()->where('active', true)->whereJsonContains('defs', $def);

        if ($branchId) {
            $own = (clone $query)->where('branch_id', $branchId)->first();
            if ($own) {
                return $own;
            }
        }

        return $query->whereNull('branch_id')->first();
    }

    /** Where a branch's cash goes: its cash drawer (the one marked for POS cash first), else the POS cash default. Locked for update. */
    public static function cashDrawerFor(int $branchId): ?PaymentAccount
    {
        $drawers = static::lockForUpdate()->where('active', true)->where('kind', 'cash')->where('branch_id', $branchId)->get();
        foreach ($drawers as $drawer) {
            if (in_array('posCash', $drawer->defs ?? [], true)) {
                return $drawer;
            }
        }
        if ($drawers->count()) {
            return $drawers->first();
        }

        return static::defaultFor('posCash', $branchId);
    }

    /** Change the balance and add a line to the account's transactions (a JSON list, newest first). */
    public function addTransaction(float $amount, string $date, string $type, string $desc, string $party, string $ref): void
    {
        $tx = $this->tx ?? [];
        array_unshift($tx, [
            'date' => date('d/m', strtotime($date)),
            'type' => $type,
            'desc' => $desc,
            'party' => $party,
            'ref' => $ref,
            'amt' => $amount,
        ]);
        $this->tx = $tx;
        $this->bal = round($this->bal + $amount, 2);
        $this->save();
    }
}
