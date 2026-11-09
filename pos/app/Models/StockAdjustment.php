<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Stock Adjustment screen: stock added or removed at a branch, or corrected by a count.
 * Stock changes when it is saved. It is never edited: a mistake is undone with a reversal.
 */
class StockAdjustment extends Model
{
    /** Removing more than this value needs a user with the adjust.edit permission. */
    public const APPROVAL_LIMIT = 500;

    public const REASONS = [
        'dec' => ['Spoilage', 'Damaged', 'Trim & waste', 'Missing', 'Staff meal', 'Count shortfall'],
        'inc' => ['Found in count', 'Returned to shelf', 'Supplier bonus', 'Opening stock'],
    ];

    protected $table = 'stock_adjustments';

    protected $hidden = ['updated_at'];

    protected $fillable = [
        'user_id',
        'ref', 'date', 'branch_id', 'type', 'increase_value', 'decrease_value',
        'notes', 'created_by', 'approved_by', 'reverses_id',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'branch_id' => 'integer',
            'increase_value' => 'float',
            'decrease_value' => 'float',
            'reverses_id' => 'integer',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockAdjustmentItem::class);
    }

    /** The adjustment this one undoes. */
    public function reverses(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverses_id');
    }

    /** The adjustment that undid this one. */
    public function reversedBy(): HasOne
    {
        return $this->hasOne(self::class, 'reverses_id');
    }
}
