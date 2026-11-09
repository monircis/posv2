<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** HRM: a loan to an employee, paid back by `installment` each month from start_month (taken in payroll). */
class EmployeeLoan extends Model
{
    protected $table = 'employee_loans';

    protected $hidden = ['updated_at'];

    protected $appends = ['left'];

    protected $fillable = ['employee_id', 'amount', 'installment', 'start_month', 'paid', 'status', 'note', 'created_by'];

    protected function casts(): array
    {
        return ['employee_id' => 'integer', 'amount' => 'float', 'installment' => 'float', 'paid' => 'float'];
    }

    public function getLeftAttribute(): float
    {
        return round($this->amount - $this->paid, 2);
    }

    /** What payroll takes this month. */
    public function installmentFor(string $month): float
    {
        if ($this->status !== 'active' || $month < $this->start_month) {
            return 0;
        }

        return round(min($this->installment, $this->left), 2);
    }
}
