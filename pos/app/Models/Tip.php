<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A tip a customer gave at the POS, for one staff member.
 * It is not part of the sale total. paid_at is set when the staff member has been paid it (HRM > Tips).
 */
class Tip extends Model
{
    protected $table = 'tips';

    protected $fillable = [
        'sale_id', 'branch_id', 'employee_id', 'amount', 'method', 'payment_account_id', 'created_by',
        'paid_at', 'paid_by', 'paid_from_account_id',
    ];

    protected function casts(): array
    {
        return [
            'sale_id' => 'integer',
            'branch_id' => 'integer',
            'employee_id' => 'integer',
            'amount' => 'float',
            'paid_at' => 'datetime',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
