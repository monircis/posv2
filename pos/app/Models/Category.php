<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Products > Categories. parent_id = null is a top category; otherwise a sub-category (any depth). */
class Category extends Model
{
    protected $table = 'categories';

    protected $hidden = ['created_at', 'updated_at'];

    protected $fillable = [
        'name', 'parent_id', 'description', 'active',
    ];

    protected function casts(): array
    {
        return [
            'parent_id' => 'integer',
            'active' => 'boolean',
        ];
    }

    /** The category one level up (null for a top category). */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /** The categories one level down. */
    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
