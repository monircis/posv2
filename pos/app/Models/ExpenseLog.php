<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One line of an expense's Activity list. */
class ExpenseLog extends Model
{
    protected $table = 'expense_logs';

    protected $hidden = ['updated_at'];

    protected $fillable = ['expense_id', 'what', 'kind'];
}
