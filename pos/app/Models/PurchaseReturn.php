<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Purchases > Returns: goods sent back to the supplier.
 * settlement: Reduced owed, Refund, Supplier credit. status: Credited, Awaiting refund.
 */
class PurchaseReturn extends Model
{
    protected $table = 'purchase_returns';

    protected $hidden = ['updated_at'];

    protected $fillable = [
        'user_id',
        'ref', 'date', 'purchase_id', 'purchase_ref', 'supplier_id', 'branch_id', 'settlement', 'status',
        'subtotal', 'tax', 'credit', 'notes', 'user_name',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'purchase_id' => 'integer',
            'supplier_id' => 'integer',
            'branch_id' => 'integer',
            'subtotal' => 'float',
            'tax' => 'float',
            'credit' => 'float',
        ];
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'supplier_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseReturnItem::class);
    }
}
