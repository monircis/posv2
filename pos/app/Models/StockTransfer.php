<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Stock Transfers screen: stock moving from one branch to another.
 * status: draft -> transit -> received (or variance, when less arrived). A draft can be cancelled.
 */
class StockTransfer extends Model
{
    protected $table = 'stock_transfers';

    protected $hidden = ['updated_at'];

    protected $fillable = [
        'user_id',
        'ref', 'date', 'from_branch_id', 'to_branch_id', 'status', 'stock_value', 'shipping_cost',
        'loss_value', 'notes', 'created_by', 'sent_at', 'received_at',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'from_branch_id' => 'integer',
            'to_branch_id' => 'integer',
            'stock_value' => 'float',
            'shipping_cost' => 'float',
            'loss_value' => 'float',
            'sent_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }

    public function fromBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'from_branch_id');
    }

    public function toBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'to_branch_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockTransferItem::class);
    }

    /** Newest first. */
    public function logs(): HasMany
    {
        return $this->hasMany(StockTransferLog::class)->orderBy('id', 'desc');
    }

    /** Add a row to the activity list. tone: neutral, info, success, warn, error. */
    public function addLog(string $message, string $tone = 'neutral'): void
    {
        $this->logs()->create(['message' => $message, 'tone' => $tone, 'logged_at' => now()]);
    }
}
