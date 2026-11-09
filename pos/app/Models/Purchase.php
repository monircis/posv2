<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Purchases screen. A purchase belongs to one supplier and one branch.
 * status: ordered, pending, received. payment_status: paid, partial, due.
 */
class Purchase extends Model
{
    protected $table = 'purchases';

    protected $hidden = ['updated_at'];

    protected $fillable = [
        'user_id',
        'ref', 'date', 'supplier_id', 'branch_id', 'status', 'payment_status',
        'subtotal', 'order_discount', 'freight', 'tax_rate', 'tax', 'total',
        'paid', 'credit_applied', 'due', 'due_date', 'notes', 'invoice_file',
        'created_by', 'received_at', 'received_by',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'due_date' => 'date:Y-m-d',
            'supplier_id' => 'integer',
            'branch_id' => 'integer',
            'subtotal' => 'float',
            'order_discount' => 'float',
            'freight' => 'float',
            'tax_rate' => 'float',
            'tax' => 'float',
            'total' => 'float',
            'paid' => 'float',
            'credit_applied' => 'float',
            'due' => 'float',
            'received_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'supplier_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PurchasePayment::class);
    }

    public function returns(): HasMany
    {
        return $this->hasMany(PurchaseReturn::class);
    }

    /** Work out due and payment_status from total, paid and credit_applied, and save. */
    public function updateDue(): void
    {
        $this->due = max(0, round($this->total - $this->paid - $this->credit_applied, 2));

        if ($this->due <= 0) {
            $this->payment_status = 'paid';
        } elseif ($this->paid + $this->credit_applied > 0) {
            $this->payment_status = 'partial';
        } else {
            $this->payment_status = 'due';
        }

        $this->save();
    }

    /**
     * Pay the supplier: saves the payment, takes the money out of the payment account,
     * and lowers what we owe the supplier.
     */
    public function addPayment(float $amount, string $accountId, string $date, ?string $note, ?string $userName): void
    {
        $account = PaymentAccount::findOrFail($accountId);
        $supplier = $this->supplier;

        $this->payments()->create([
            'payment_account_id' => $account->id,
            'account_name' => $account->name,
            'amount' => $amount,
            'date' => $date,
            'note' => $note,
            'user_name' => $userName,
        ]);

        $this->paid = round($this->paid + $amount, 2);
        $this->updateDue();

        // The account keeps its transactions in a JSON list, newest first.
        $tx = $account->tx ?? [];
        array_unshift($tx, [
            'date' => date('d/m', strtotime($date)),
            'type' => 'purchase',
            'desc' => 'Supplier payment',
            'party' => $supplier->name,
            'ref' => $this->ref,
            'amt' => -$amount,
        ]);
        $account->tx = $tx;
        $account->bal = round($account->bal - $amount, 2);
        $account->save();

        $supplier->balance = round($supplier->balance + $amount, 2);
        $supplier->save();
    }
}
