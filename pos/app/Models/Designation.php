<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** HRM: a job title (designation), optionally inside a department. */
class Designation extends Model
{
    protected $table = 'designations';

    protected $hidden = ['created_at', 'updated_at'];

    protected $fillable = ['name', 'department_id', 'active'];

    protected function casts(): array
    {
        return ['department_id' => 'integer', 'active' => 'boolean'];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}
