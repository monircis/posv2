<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One material of a recipe: how much one batch uses. */
class RecipeItem extends Model
{
    protected $table = 'recipe_items';

    protected $hidden = ['created_at', 'updated_at'];

    protected $fillable = ['recipe_id', 'material_id', 'qty'];

    protected function casts(): array
    {
        return [
            'material_id' => 'integer',
            'qty' => 'float',
        ];
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }
}
