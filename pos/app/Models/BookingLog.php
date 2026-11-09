<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One line of a booking's history, e.g. "Confirmed by Aminul". */
class BookingLog extends Model
{
    protected $table = 'booking_logs';

    protected $fillable = ['booking_id', 'text', 'tone', 'user_name'];
}
