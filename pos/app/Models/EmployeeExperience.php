<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** HRM: one earlier job of an employee. */
class EmployeeExperience extends Model
{
    protected $table = 'employee_experiences';

    protected $hidden = ['created_at', 'updated_at'];

    protected $fillable = ['employee_id', 'company', 'position', 'date_from', 'date_to', 'notes'];

    protected function casts(): array
    {
        return ['employee_id' => 'integer', 'date_from' => 'date:Y-m-d', 'date_to' => 'date:Y-m-d'];
    }
}
