<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Manufacturing: a batch to make at one branch.
 * planned: nothing has moved. progress: materials taken. done: output added to product stock. cancelled.
 */
class ProductionOrder extends Model
{
    protected $table = 'production_orders';

    protected $hidden = ['updated_at'];

    protected $fillable = [
        'user_id',
        'ref', 'recipe_id', 'branch_id', 'recipe_name', 'unit', 'batches', 'date', 'status',
        'expected_qty', 'actual_qty', 'material_cost', 'materials_taken', 'created_by', 'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'recipe_id' => 'integer',
            'branch_id' => 'integer',
            'batches' => 'integer',
            'date' => 'date:Y-m-d',
            'expected_qty' => 'float',
            'actual_qty' => 'float',
            'material_cost' => 'float',
            'materials_taken' => 'boolean',
            'finished_at' => 'datetime',
        ];
    }

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProductionOrderItem::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ProductionOrderLog::class);
    }

    /** "MO-0117" gives "MO-0118". */
    public static function nextRef(): string
    {
        $max = 0;
        foreach (self::where('ref', 'like', 'MO-%')->pluck('ref') as $ref) {
            $number = substr($ref, 3);
            if (ctype_digit($number) && (int) $number > $max) {
                $max = (int) $number;
            }
        }

        return 'MO-'.str_pad((string) ($max + 1), 4, '0', STR_PAD_LEFT);
    }
}
