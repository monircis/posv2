<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One change to a material's stock: + in, - out, with the quantity after it. */
class MaterialMovement extends Model
{
    protected $table = 'material_movements';

    protected $hidden = ['updated_at'];

    protected $fillable = ['material_id', 'branch_id', 'change', 'balance', 'kind', 'ref', 'note', 'user_name'];

    protected function casts(): array
    {
        return [
            'change' => 'float',
            'balance' => 'float',
        ];
    }
}
