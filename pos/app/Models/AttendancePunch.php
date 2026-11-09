<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** HRM: one clock-in, clock-out or break start/end. */
class AttendancePunch extends Model
{
    protected $table = 'attendance_punches';

    protected $hidden = ['updated_at'];

    protected $fillable = ['attendance_id', 'employee_id', 'type', 'punched_at', 'source', 'remote', 'created_by'];

    protected function casts(): array
    {
        return ['attendance_id' => 'integer', 'employee_id' => 'integer', 'punched_at' => 'datetime:Y-m-d H:i', 'remote' => 'boolean'];
    }
}
