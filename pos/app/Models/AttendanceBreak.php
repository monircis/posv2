<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** HRM: one break in a day, worked out from the break punches. */
class AttendanceBreak extends Model
{
    protected $table = 'attendance_breaks';

    protected $hidden = ['created_at', 'updated_at'];

    protected $fillable = ['attendance_id', 'start_at', 'end_at', 'minutes'];

    protected function casts(): array
    {
        return ['attendance_id' => 'integer', 'start_at' => 'datetime:Y-m-d H:i', 'end_at' => 'datetime:Y-m-d H:i', 'minutes' => 'integer'];
    }
}
