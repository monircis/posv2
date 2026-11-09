<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Sell and POS screens.
 * status: paid, partial (some paid, the rest owing), due (nothing paid), cancelled.
 * Old demo rows can also be draft or quote.
 */
class Sale extends Model
{
    public const DELIVERY_FEE = 15;

    protected $table = 'sales';

    protected $hidden = ['updated_at'];

    protected $fillable = [
        'ref', 'sold_at', 'branch_id', 'contact_id', 'customer_name', 'channel', 'order_type', 'table_no', 'item_count',
        'subtotal', 'group_percent', 'group_discount', 'discount_percent', 'discount_type', 'discount', 'discount_approved_by',
        'coins_used', 'coin_discount', 'coins_earned',
        'delivery_fee', 'card_surcharge', 'rounding', 'gst', 'total', 'paid', 'due', 'due_date', 'tendered', 'change_given',
        'method', 'status', 'created_by', 'user_id', 'cancelled_at', 'cancelled_by', 'cancel_reason',
    ];

    protected function casts(): array
    {
        return [
            'sold_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'due_date' => 'date:Y-m-d',
            'branch_id' => 'integer',
            'contact_id' => 'integer',
            'table_no' => 'integer',
            'item_count' => 'integer',
            'subtotal' => 'float',
            'group_percent' => 'float',
            'group_discount' => 'float',
            'discount_percent' => 'float',
            'discount' => 'float',
            'delivery_fee' => 'float',
            'card_surcharge' => 'float',
            'coins_used' => 'integer',
            'coin_discount' => 'float',
            'coins_earned' => 'integer',
            'rounding' => 'float',
            'gst' => 'float',
            'total' => 'float',
            'paid' => 'float',
            'due' => 'float',
            'tendered' => 'float',
            'change_given' => 'float',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SalePayment::class);
    }

    /** Rounds to cents the same way as Math.round in the POS screen, so both get the same total. */
    public static function cents(float $amount): float
    {
        return floor($amount * 100 + 0.5) / 100;
    }

    /** Cash is rounded to the nearest 5c. */
    public static function cashRounded(float $amount): float
    {
        return floor($amount * 20 + 0.5) / 20;
    }

    /**
     * The next invoice number from Settings > Invoice & receipt: the branch's prefix (or else the
     * general one) and the next number, e.g. MB-3311. A number already used is skipped.
     */
    public static function nextRef(int $branchId, ?InvoiceSetting $setting = null): string
    {
        return self::refAt($branchId, $setting ?? InvoiceSetting::current())[0];
    }

    /** Use up the next invoice number. Call inside a transaction: the settings row is locked until it ends. */
    public static function takeRef(int $branchId): string
    {
        InvoiceSetting::current();
        $setting = InvoiceSetting::lockForUpdate()->first();
        [$ref, $number] = self::refAt($branchId, $setting);

        $setting->next_invoice_number = $number + 1;
        $setting->save();

        return $ref;
    }

    /** [the number as text, the number] */
    private static function refAt(int $branchId, InvoiceSetting $setting): array
    {
        $prefix = Branch::where('id', $branchId)->value('invoice_prefix') ?: $setting->invoice_prefix;
        $number = (int) $setting->next_invoice_number;
        while (self::where('ref', $prefix.$number)->exists()) {
            $number++;
        }

        return [$prefix.$number, $number];
    }

    /** The tip the customer gave with this sale, if any. Not part of the total. */
    public function tip(): HasOne
    {
        return $this->hasOne(Tip::class);
    }

    /** Save the money taken and add it to the payment account. */
    public function addPayment(string $method, float $amount, string $userName, string $desc): void
    {
        $account = $this->accountFor($method);

        $this->payments()->create([
            'method' => $method,
            'amount' => $amount,
            'payment_account_id' => $account ? $account->id : null,
            'account_name' => $account ? $account->name : null,
            'user_name' => $userName,
        ]);

        if ($account) {
            $account->addTransaction($amount, now()->toDateString(), 'sale', $desc, $this->customer_name, $this->ref);
        }
    }

    /** Cash goes to this branch's drawer; card to this branch's POS card account, else the whole business's (Payment Accounts). */
    public function accountFor(string $method): ?PaymentAccount
    {
        if ($method === 'cash') {
            return PaymentAccount::cashDrawerFor($this->branch_id);
        }

        return PaymentAccount::defaultFor('posCard', $this->branch_id);
    }
}
