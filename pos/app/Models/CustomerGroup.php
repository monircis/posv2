<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Contacts > Customer groups. A group sets price group, default discount and payment terms. */
class CustomerGroup extends Model
{
    protected $table = 'customer_groups';

    protected $hidden = ['created_at', 'updated_at'];

    protected $fillable = [
        'name', 'branch_id', 'description', 'price_group', 'discount', 'terms',
    ];

    protected function casts(): array
    {
        return [
            'discount' => 'float',
            'branch_id' => 'integer',
        ];
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
