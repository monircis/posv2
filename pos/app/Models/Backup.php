<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Settings > System > Back up now (history). */
class Backup extends Model
{
    protected $table = 'backups';

    protected $fillable = [
        'status', 'file_name', 'size_bytes', 'requested_by',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
        ];
    }
}
