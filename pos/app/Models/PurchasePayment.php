<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Money paid to the supplier for a purchase. Add one with $purchase->addPayment(). */
class PurchasePayment extends Model
{
    protected $table = 'purchase_payments';

    protected $hidden = ['updated_at'];

    protected $fillable = [
        'purchase_id', 'payment_account_id', 'account_name', 'amount', 'date', 'note', 'user_name',
    ];

    protected function casts(): array
    {
        return [
            'purchase_id' => 'integer',
            'amount' => 'float',
            'date' => 'date:Y-m-d',
        ];
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }
}
