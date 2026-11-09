<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A shop or store: Settings > Branches & locations. Users (in the auth service) belong to one branch by branch_id. */
class Branch extends Model
{
    protected $fillable = ['name', 'code', 'address', 'invoice_prefix', 'active'];

    protected $hidden = ['created_at', 'updated_at'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
