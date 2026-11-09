<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** HRM: one line of an employee's education. */
class EmployeeEducation extends Model
{
    protected $table = 'employee_educations';

    protected $hidden = ['created_at', 'updated_at'];

    protected $fillable = ['employee_id', 'degree', 'institution', 'year', 'result'];

    protected function casts(): array
    {
        return ['employee_id' => 'integer'];
    }
}
