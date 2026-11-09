<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One + or - on a customer's loyalty coins. Always written through record(),
 * which also changes contacts.coins, so the two never disagree.
 */
class CoinTransaction extends Model
{
    protected $fillable = ['contact_id', 'sale_id', 'change', 'balance', 'kind', 'note', 'user_name'];

    protected $hidden = ['updated_at'];

    protected function casts(): array
    {
        return [
            'contact_id' => 'integer',
            'sale_id' => 'integer',
            'change' => 'integer',
            'balance' => 'integer',
        ];
    }

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    /** Change the contact's coins and write the history row. Lock the contact row before calling this. */
    public static function record(Contact $contact, int $change, string $kind, ?Sale $sale, ?string $note, ?string $userName): self
    {
        $contact->coins = $contact->coins + $change;
        $contact->save();

        return self::create([
            'contact_id' => $contact->id,
            'sale_id' => $sale ? $sale->id : null,
            'change' => $change,
            'balance' => $contact->coins,
            'kind' => $kind,
            'note' => $note,
            'user_name' => $userName,
        ]);
    }
}
