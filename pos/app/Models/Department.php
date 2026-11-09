<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** HRM: a department. branch_id null = used at every branch. manager_id is the department manager (an employee). */
class Department extends Model
{
    protected $table = 'departments';

    protected $hidden = ['created_at', 'updated_at'];

    protected $fillable = ['name', 'branch_id', 'manager_id', 'active'];

    protected function casts(): array
    {
        return ['branch_id' => 'integer', 'manager_id' => 'integer', 'active' => 'boolean'];
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'manager_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function designations(): HasMany
    {
        return $this->hasMany(Designation::class);
    }
}
