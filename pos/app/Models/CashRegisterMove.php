<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Cash into (+) or out of (-) the drawer during a register shift: sale, refund, tip, received, cash_in, cash_out. */
class CashRegisterMove extends Model
{
    protected $table = 'cash_register_moves';

    protected $fillable = ['cash_register_id', 'type', 'amount', 'ref', 'note', 'user_name'];

    protected function casts(): array
    {
        return [
            'amount' => 'float',
        ];
    }
}
