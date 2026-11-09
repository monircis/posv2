<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Quantity of a product (or one variant of it) at one branch. */
class ProductStock extends Model
{
    protected $table = 'product_stocks';

    protected $hidden = ['created_at', 'updated_at'];

    protected $fillable = [
        'product_id', 'variant_id', 'branch_id', 'qty',
    ];

    protected function casts(): array
    {
        return [
            'product_id' => 'integer',
            'variant_id' => 'integer',
            'branch_id' => 'integer',
            'qty' => 'float',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
