<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A table or room that can be booked, at one branch. */
class BookingTable extends Model
{
    protected $table = 'booking_tables';

    protected $fillable = ['branch_id', 'name', 'seats', 'zone', 'deposit_per_head', 'active', 'sort'];

    protected $hidden = ['created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'branch_id' => 'integer',
            'seats' => 'integer',
            'deposit_per_head' => 'float',
            'active' => 'boolean',
            'sort' => 'integer',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
