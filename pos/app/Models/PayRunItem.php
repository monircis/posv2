<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One employee's hours and pay in a pay run (= their payslip).
 * Fortnight runs use ord_hours / ot_hours / gross / tax / super / net.
 * Monthly runs use basic, allowances, overtime, bonus, deductions, advance, loan, gross, net and `lines` (the payslip lines).
 */
class PayRunItem extends Model
{
    protected $table = 'pay_run_items';

    protected $hidden = ['created_at', 'updated_at'];

    protected $fillable = [
        'pay_run_id', 'employee_id', 'ord_hours', 'ot_hours', 'basic', 'allowances', 'overtime', 'bonus', 'deductions',
        'advance', 'loan', 'gross', 'tax', 'super', 'net', 'lines',
    ];

    protected function casts(): array
    {
        return [
            'pay_run_id' => 'integer',
            'employee_id' => 'integer',
            'ord_hours' => 'float',
            'ot_hours' => 'float',
            'basic' => 'float',
            'allowances' => 'float',
            'overtime' => 'float',
            'bonus' => 'float',
            'deductions' => 'float',
            'advance' => 'float',
            'loan' => 'float',
            'gross' => 'float',
            'tax' => 'float',
            'super' => 'float',
            'net' => 'float',
            'lines' => 'array',
        ];
    }

    public function payRun(): BelongsTo
    {
        return $this->belongsTo(PayRun::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class)->withTrashed();
    }
}
