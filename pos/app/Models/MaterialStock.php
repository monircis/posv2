<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** How much of a material is at a branch. Changed through Material::record(). */
class MaterialStock extends Model
{
    protected $table = 'material_stocks';

    protected $hidden = ['created_at', 'updated_at'];

    protected $fillable = ['material_id', 'branch_id', 'qty'];

    protected function casts(): array
    {
        return [
            'material_id' => 'integer',
            'branch_id' => 'integer',
            'qty' => 'float',
        ];
    }
}
