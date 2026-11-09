<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** HRM: a file kept for an employee (ID, visa, contract ...). Saved on the private disk, downloaded through the API. */
class EmployeeDocument extends Model
{
    protected $table = 'employee_documents';

    protected $hidden = ['file', 'updated_at'];

    protected $fillable = ['employee_id', 'title', 'type', 'file', 'file_name', 'expires_on', 'uploaded_by'];

    protected function casts(): array
    {
        return ['employee_id' => 'integer', 'expires_on' => 'date:Y-m-d'];
    }
}
