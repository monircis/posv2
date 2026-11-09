<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One row of a transfer's activity list. Add one with $transfer->addLog(). */
class StockTransferLog extends Model
{
    protected $table = 'stock_transfer_logs';

    public $timestamps = false;

    protected $fillable = ['stock_transfer_id', 'message', 'tone', 'logged_at'];

    protected function casts(): array
    {
        return [
            'stock_transfer_id' => 'integer',
            'logged_at' => 'datetime',
        ];
    }
}
