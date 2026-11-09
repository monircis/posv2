<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Products > Brands. */
class Brand extends Model
{
    protected $table = 'brands';

    protected $hidden = ['created_at', 'updated_at'];

    protected $fillable = [
        'name', 'description', 'active',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
