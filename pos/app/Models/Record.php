<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Base for the ERP records. Each table's primary key is the key the frontend
 * already uses (ref, no, sku, code ...), so it is a string and not auto-incremented.
 */
abstract class Record extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $hidden = ['created_at', 'updated_at'];

    /** Empty strings in date columns are saved as null (an unpaid expense has no due date). */
    public function setAttribute($key, $value)
    {
        if ($value === '' && in_array($this->getCasts()[$key] ?? null, ['date:Y-m-d', 'date'], true)) {
            $value = null;
        }

        return parent::setAttribute($key, $value);
    }
}
