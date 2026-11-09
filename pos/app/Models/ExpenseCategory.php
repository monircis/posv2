<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Expenses > Categories. Switched off categories stay on old expenses but are not offered on the form. */
class ExpenseCategory extends Model
{
    /** The colours a category can have. */
    public const COLORS = [
        'var(--c-primary)', '#0f8b8d', '#7a4fc9', '#d98c1a', '#c2410c', '#b02020',
        '#c43d86', '#2f9e63', '#5d6b7d', '#8fa3b6',
    ];

    protected $table = 'expense_categories';

    protected $hidden = ['created_at', 'updated_at'];

    protected $fillable = ['name', 'color', 'active'];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }
}
