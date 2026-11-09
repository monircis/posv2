<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One cashier's shift on the POS at one branch. It is open while closed_at is null.
 * Expected cash in the drawer = opening_cash + the sum of its moves.
 */
class CashRegister extends Model
{
    protected $table = 'cash_registers';

    protected $fillable = [
        'branch_id', 'user_id', 'user_name', 'opened_at', 'opening_cash', 'opening_note',
        'closed_at', 'closed_by', 'expected_cash', 'counted_cash', 'difference', 'kept_cash', 'close_note',
    ];

    protected function casts(): array
    {
        return [
            'branch_id' => 'integer',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'opening_cash' => 'float',
            'expected_cash' => 'float',
            'counted_cash' => 'float',
            'difference' => 'float',
            'kept_cash' => 'float',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function moves(): HasMany
    {
        return $this->hasMany(CashRegisterMove::class);
    }

    /**
     * The open register of this user at this branch, or null.
     * A register is for one day: one still open from an earlier day is closed here (not counted),
     * so a new opening count is needed each day.
     */
    public static function openFor($userId, int $branchId): ?CashRegister
    {
        $register = static::where('user_id', (string) $userId)
            ->where('branch_id', $branchId)
            ->whereNull('closed_at')
            ->first();

        if ($register && $register->isFromEarlierDay()) {
            $register->closeAtDayEnd();

            return null;
        }

        return $register;
    }

    /** All open registers of this user (any branch), after closing those left open from an earlier day. */
    public static function openForUser($userId)
    {
        $open = static::with('branch')->where('user_id', (string) $userId)->whereNull('closed_at')->orderBy('opened_at')->get();

        return $open->filter(function (CashRegister $register) {
            if ($register->isFromEarlierDay()) {
                $register->closeAtDayEnd();

                return false;
            }

            return true;
        })->values();
    }

    /** Today's date in the shop's time zone (config app.shop_timezone). */
    public static function shopToday(): string
    {
        return now(config('app.shop_timezone'))->toDateString();
    }

    /** The shop day this register was opened on. */
    public function shopDay(): string
    {
        return $this->opened_at->copy()->setTimezone(config('app.shop_timezone'))->toDateString();
    }

    public function isFromEarlierDay(): bool
    {
        return $this->shopDay() < static::shopToday();
    }

    /**
     * Closes a register that was not closed on its day. Nobody counted it, so there is no over/short:
     * the expected cash is taken as what is left in the drawer for the next opening count.
     */
    public function closeAtDayEnd(): void
    {
        $expected = $this->expected();
        $this->update([
            'closed_at' => now(),
            'closed_by' => 'Day end',
            'expected_cash' => $expected,
            'counted_cash' => null,
            'difference' => null,
            'kept_cash' => $expected,
            'close_note' => 'Not closed on '.$this->shopDay().'. Closed when a new day started, without a count.',
        ]);
    }

    /**
     * Note cash going into (+) or out of (-) the drawer, if this user has a register open at this branch.
     * Without an open register nothing is noted (for example a manager cancelling a sale from the Sell page).
     */
    public static function noteCash($user, int $branchId, string $type, float $amount, ?string $ref, ?string $note = null): void
    {
        if (abs($amount) < 0.005) {
            return;
        }
        $register = static::openFor($user->id, $branchId);
        if (! $register) {
            return;
        }
        $register->moves()->create([
            'type' => $type,
            'amount' => round($amount, 2),
            'ref' => $ref,
            'note' => $note,
            'user_name' => $user->name,
        ]);
    }

    /** What should be in the drawer now. */
    public function expected(): float
    {
        return round($this->opening_cash + (float) $this->moves()->sum('amount'), 2);
    }

    /** Opening cash and the moves added up by type, for the POS panel and the close report. */
    public function summary(): array
    {
        $byType = $this->moves()->selectRaw('type, SUM(amount) as total, COUNT(*) as n')->groupBy('type')->get()->keyBy('type');
        $get = function ($type) use ($byType) {
            return isset($byType[$type]) ? round((float) $byType[$type]->total, 2) : 0;
        };

        return [
            'id' => $this->id,
            'branch_id' => $this->branch_id,
            'branch' => $this->branch ? $this->branch->name : '',
            'user_name' => $this->user_name,
            'opened_at' => $this->opened_at,
            'closed_at' => $this->closed_at,
            'opening_cash' => $this->opening_cash,
            'cash_sales' => $get('sale'),
            'sales_count' => isset($byType['sale']) ? (int) $byType['sale']->n : 0,
            'received' => $get('received'),
            'tips' => $get('tip'),
            'refunds' => $get('refund'),
            'cash_in' => $get('cash_in'),
            'cash_out' => $get('cash_out'),
            'expected_cash' => $this->closed_at ? $this->expected_cash : $this->expected(),
            'counted_cash' => $this->counted_cash,
            'difference' => $this->difference,
            'kept_cash' => $this->kept_cash,
            'opening_note' => $this->opening_note,
            'close_note' => $this->close_note,
            'closed_by' => $this->closed_by,
            // Closed by the day ending, without a count.
            'auto_closed' => $this->closed_at !== null && $this->counted_cash === null,
            'shop_day' => $this->shopDay(),
        ];
    }
}
