<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A table booking at one branch. Times are "HH:MM" on `date`, `duration` is in minutes.
 * Status goes: pending -> confirmed -> seated -> completed, or to noshow / cancelled.
 */
class Booking extends Model
{
    protected $table = 'bookings';

    // Opening hours for bookings.
    public const OPEN = '11:00';

    public const CLOSE = '22:00';

    // What each status can change to (Bookings screen buttons).
    public const NEXT = [
        'pending' => ['confirmed', 'cancelled'],
        'confirmed' => ['seated', 'noshow', 'cancelled'],
        'seated' => ['completed'],
        'completed' => [],
        'noshow' => [],
        'cancelled' => [],
    ];

    // Bookings that still hold their table.
    public const HOLDS_TABLE = ['pending', 'confirmed', 'seated'];

    protected $fillable = [
        'user_id',
        'ref', 'branch_id', 'booking_table_id', 'contact_id', 'customer_name', 'customer_phone',
        'date', 'start_time', 'duration', 'size', 'status', 'deposit', 'deposit_paid_at', 'deposit_method',
        'payment_account_id', 'source', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'branch_id' => 'integer',
            'booking_table_id' => 'integer',
            'contact_id' => 'integer',
            'date' => 'date:Y-m-d',
            'duration' => 'integer',
            'size' => 'integer',
            'deposit' => 'float',
            'deposit_paid_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(BookingTable::class, 'booking_table_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(BookingLog::class)->orderByDesc('id');
    }

    /** "19:30" -> 1170 */
    public static function minutes(string $time): int
    {
        [$h, $m] = explode(':', $time);

        return (int) $h * 60 + (int) $m;
    }

    /** 1170 -> "19:30" */
    public static function time(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }

    /** Today in the shop's time zone (config app.shop_timezone). */
    public static function today(): string
    {
        return now(config('app.shop_timezone'))->toDateString();
    }

    /** Minutes since midnight now, in the shop's time zone. */
    public static function nowMinutes(): int
    {
        $now = now(config('app.shop_timezone'));

        return $now->hour * 60 + $now->minute;
    }

    public function endTime(): string
    {
        return static::time(static::minutes($this->start_time) + $this->duration);
    }

    public function depositPaid(): bool
    {
        return $this->deposit_paid_at !== null;
    }

    /** The next booking number: BK-2050. */
    public static function nextRef(): string
    {
        $last = static::orderByDesc('id')->value('ref');
        $number = $last ? (int) substr($last, 3) + 1 : 1001;

        return 'BK-'.$number;
    }

    /** Adds a line to the history. */
    public function log(string $text, string $tone, ?string $userName): void
    {
        $this->logs()->create(['text' => $text, 'tone' => $tone, 'user_name' => $userName]);
    }
}
