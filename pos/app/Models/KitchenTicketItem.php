<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One line on a kitchen ticket. `done` is ticked by the cook. */
class KitchenTicketItem extends Model
{
    protected $table = 'kitchen_ticket_items';

    protected $fillable = ['kitchen_ticket_id', 'product_id', 'name', 'qty', 'unit', 'station', 'note', 'done'];

    protected $hidden = ['created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'qty' => 'float',
            'done' => 'boolean',
        ];
    }
}
