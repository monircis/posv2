<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * HRM: an employee's monthly pay from effective_from on. A new row is a pay change; old rows are the salary history.
 * other_allowances / fixed_deductions: [{"name": "Phone", "amount": 50}]
 */
class SalaryStructure extends Model
{
    protected $table = 'salary_structures';

    protected $hidden = ['updated_at'];

    protected $appends = ['allowances_total', 'fixed_deductions_total', 'gross'];

    protected $fillable = ['employee_id', 'effective_from', 'basic', 'house_rent', 'medical', 'transport', 'other_allowances', 'fixed_deductions', 'note', 'created_by'];

    protected function casts(): array
    {
        return [
            'employee_id' => 'integer',
            'effective_from' => 'date:Y-m-d',
            'basic' => 'float',
            'house_rent' => 'float',
            'medical' => 'float',
            'transport' => 'float',
            'other_allowances' => 'array',
            'fixed_deductions' => 'array',
        ];
    }

    public function getAllowancesTotalAttribute(): float
    {
        $other = array_sum(array_map(fn ($a) => (float) ($a['amount'] ?? 0), $this->other_allowances ?? []));

        return round($this->house_rent + $this->medical + $this->transport + $other, 2);
    }

    public function getFixedDeductionsTotalAttribute(): float
    {
        return round(array_sum(array_map(fn ($a) => (float) ($a['amount'] ?? 0), $this->fixed_deductions ?? [])), 2);
    }

    public function getGrossAttribute(): float
    {
        return round($this->basic + $this->allowances_total, 2);
    }
}
