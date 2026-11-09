<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Manufacturing: raw materials, with stock per branch. They are separate from products. */
class Material extends Model
{
    protected $table = 'materials';

    protected $hidden = ['created_at', 'updated_at'];

    protected $fillable = ['name', 'unit', 'cost'];

    protected function casts(): array
    {
        return [
            'cost' => 'float',
        ];
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(MaterialStock::class);
    }

    /**
     * Change the stock of a material at a branch and log it.
     * Example: Material::record(3, $branchId, -18, 'Production', 'MO-0118', null, 'Sara');
     */
    public static function record(int $materialId, int $branchId, float $change, string $kind, ?string $ref = null, ?string $note = null, ?string $userName = null): void
    {
        $stock = MaterialStock::firstOrCreate(['material_id' => $materialId, 'branch_id' => $branchId], ['qty' => 0]);
        $stock->qty = round($stock->qty + $change, 3);
        $stock->save();

        MaterialMovement::create([
            'material_id' => $materialId,
            'branch_id' => $branchId,
            'change' => $change,
            'balance' => $stock->qty,
            'kind' => $kind,
            'ref' => $ref,
            'note' => $note,
            'user_name' => $userName,
        ]);
    }
}
