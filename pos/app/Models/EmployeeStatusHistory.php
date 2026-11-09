<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** HRM: one status change of an employee (joined, confirmed, resigned ...). */
class EmployeeStatusHistory extends Model
{
    protected $table = 'employee_status_histories';

    protected $hidden = ['updated_at'];

    protected $fillable = ['employee_id', 'from_status', 'to_status', 'date', 'note', 'changed_by'];

    protected function casts(): array
    {
        return ['employee_id' => 'integer', 'date' => 'date:Y-m-d'];
    }
}
