<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Products > Units, e.g. Kilogram (kg), Each (ea). */
class Unit extends Model
{
    protected $table = 'units';

    protected $hidden = ['created_at', 'updated_at'];

    protected $fillable = [
        'name', 'short_name', 'allow_decimals', 'active',
    ];

    protected function casts(): array
    {
        return [
            'allow_decimals' => 'boolean',
            'active' => 'boolean',
        ];
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
