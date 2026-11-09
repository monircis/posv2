<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Money taken for a sale. A split payment has one row for card and one for cash. */
class SalePayment extends Model
{
    protected $table = 'sale_payments';

    protected $hidden = ['updated_at'];

    protected $fillable = ['sale_id', 'method', 'amount', 'payment_account_id', 'account_name', 'user_name'];

    protected function casts(): array
    {
        return [
            'amount' => 'float',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }
}
