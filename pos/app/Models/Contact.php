<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Contacts: customers, suppliers or both.
 * balance: + they owe us, - we owe them. Sales find a contact by its name; purchases by supplier_id.
 */
class Contact extends Model
{
    protected $table = 'contacts';

    protected $hidden = ['updated_at'];

    protected $fillable = [
        'name', 'contact_name', 'phone', 'email', 'abn', 'type', 'branch_id',
        'customer_group_id', 'credit_limit', 'opening_balance', 'pay_term', 'advance_balance',
        'street', 'suburb', 'state', 'postcode', 'business', 'balance', 'last_activity', 'note',
    ];

    protected function casts(): array
    {
        return [
            'customer_group_id' => 'integer',
            'credit_limit' => 'float',
            'opening_balance' => 'float',
            'advance_balance' => 'float',
            'business' => 'float',
            'balance' => 'float',
            'coins' => 'integer',
            'created_at' => 'date:Y-m-d',
        ];
    }

    public function customerGroup(): BelongsTo
    {
        return $this->belongsTo(CustomerGroup::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** Contacts that can be used at a branch: that branch's own and the all-branch ones (branch_id null). */
    public function scopeForBranch($query, int $branchId)
    {
        return $query->where(function ($q) use ($branchId) {
            $q->where('branch_id', $branchId)->orWhereNull('branch_id');
        });
    }

    /** True when this contact can be used at the branch (its own branch, or all branches). */
    public function isAtBranch(int $branchId): bool
    {
        return ! $this->branch_id || (int) $this->branch_id === $branchId;
    }
}
