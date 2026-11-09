<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** HRM: the approval levels, in order, for one kind of request (leave, overtime, correction, deduction, payroll). */
class ApprovalWorkflow extends Model
{
    protected $table = 'approval_workflows';

    protected $hidden = ['created_at'];

    protected $fillable = ['module', 'levels', 'active'];

    protected function casts(): array
    {
        return ['levels' => 'array', 'active' => 'boolean'];
    }
}
