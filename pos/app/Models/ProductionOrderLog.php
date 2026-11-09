<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One line of an order's Activity list. */
class ProductionOrderLog extends Model
{
    protected $table = 'production_order_logs';

    protected $hidden = ['updated_at'];

    protected $fillable = ['production_order_id', 'what', 'kind'];
}
