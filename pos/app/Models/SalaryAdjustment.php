<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** HRM: a one-off amount in one month's pay: bonus, incentive or leave encashment (added), or advance (taken off). */
class SalaryAdjustment extends Model
{
    public const TYPES = ['bonus', 'incentive', 'encashment', 'advance'];

    protected $table = 'salary_adjustments';

    protected $hidden = ['updated_at'];

    protected $fillable = ['employee_id', 'month', 'type', 'amount', 'note', 'pay_run_id', 'created_by'];

    protected function casts(): array
    {
        return ['employee_id' => 'integer', 'amount' => 'float', 'pay_run_id' => 'integer'];
    }
}
