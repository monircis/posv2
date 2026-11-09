<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/**
 * Expenses screen. An expense belongs to one branch.
 * With a payment account it is paid; without one it is an unpaid bill with a due date.
 */
class Expense extends Model
{
    use SoftDeletes;

    protected $table = 'expenses';

    protected $hidden = ['updated_at', 'deleted_at', 'receipt_file'];

    protected $appends = ['receipt_url'];

    protected $fillable = [
        'user_id',
        'ref', 'date', 'branch_id', 'expense_category_id', 'payee', 'amount', 'has_gst', 'gst_amount',
        'payment_account_id', 'paid_date', 'due_date', 'invoice_no', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'paid_date' => 'date:Y-m-d',
            'due_date' => 'date:Y-m-d',
            'branch_id' => 'integer',
            'expense_category_id' => 'integer',
            'amount' => 'float',
            'has_gst' => 'boolean',
            'gst_amount' => 'float',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function paymentAccount(): BelongsTo
    {
        return $this->belongsTo(PaymentAccount::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ExpenseLog::class);
    }

    public function getReceiptUrlAttribute(): ?string
    {
        return $this->receipt_file ? Storage::disk('public')->url($this->receipt_file) : null;
    }

    /** Take the amount out of a payment account. */
    public function payFrom(string $accountId, string $date): void
    {
        PaymentAccount::lockForUpdate()->findOrFail($accountId)
            ->addTransaction(-$this->amount, $date, 'expense', $this->category->name, $this->payee, $this->ref);
    }

    /** Put the amount back into a payment account. */
    public function refundTo(string $accountId, float $amount, string $date): void
    {
        PaymentAccount::lockForUpdate()->findOrFail($accountId)
            ->addTransaction($amount, $date, 'expense', 'Expense changed or deleted', $this->payee, $this->ref);
    }
}
