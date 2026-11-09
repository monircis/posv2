<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A kitchen ticket at one branch: the "Send to kitchen" items of one POS sale.
 * Status goes new -> prep -> ready -> served (and back with Recall); a cancelled sale makes it `cancelled`.
 */
class KitchenTicket extends Model
{
    protected $table = 'kitchen_tickets';

    // What each status can change to on the Kitchen board (Start, Ready, Served, Recall).
    public const NEXT = [
        'new' => ['prep'],
        'prep' => ['ready', 'new'],
        'ready' => ['served', 'prep'],
        'served' => ['ready'],
        'cancelled' => [],
    ];

    // Target time when the products have no prep minutes.
    public const DEFAULT_MINUTES = 12;

    protected $fillable = [
        'ref', 'branch_id', 'sale_id', 'source', 'where_text', 'status', 'previous_status', 'rush', 'note',
        'target_minutes', 'placed_at', 'started_at', 'ready_at', 'served_at',
    ];

    protected function casts(): array
    {
        return [
            'branch_id' => 'integer',
            'sale_id' => 'integer',
            'rush' => 'boolean',
            'target_minutes' => 'integer',
            'placed_at' => 'datetime',
            'started_at' => 'datetime',
            'ready_at' => 'datetime',
            'served_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(KitchenTicketItem::class);
    }

    /**
     * Makes the ticket for a POS sale, from its items whose product is marked "Send to kitchen".
     * Does nothing when the sale has no such items.
     */
    public static function createForSale(Sale $sale): ?KitchenTicket
    {
        $sale->loadMissing('items');
        $products = Product::with('category.parent')->whereIn('id', $sale->items->pluck('product_id'))
            ->where('send_to_kitchen', true)->get()->keyBy('id');

        $lines = $sale->items->filter(fn ($item) => isset($products[$item->product_id]));
        if ($lines->isEmpty()) {
            return null;
        }

        // The longest prep time on the ticket, plus a little for big tickets.
        $minutes = 0;
        foreach ($lines as $item) {
            $minutes = max($minutes, (int) ($products[$item->product_id]->prep_minutes ?: self::DEFAULT_MINUTES));
        }
        if ($lines->count() > 3) {
            $minutes += 3;
        }

        $sources = ['Dine in' => 'dine', 'Takeaway' => 'take', 'Delivery' => 'deliv'];
        $where = $sale->order_type === 'Dine in' ? 'Table '.($sale->table_no ?: '?') : ($sale->order_type === 'Delivery' ? 'Delivery' : 'Counter');
        if ($sale->customer_name && $sale->customer_name !== 'Walk in') {
            $where .= ' · '.$sale->customer_name;
        }

        $ticket = static::create([
            'ref' => $sale->ref,
            'branch_id' => $sale->branch_id,
            'sale_id' => $sale->id,
            'source' => $sale->channel === 'Online' ? 'online' : ($sources[$sale->order_type] ?? 'take'),
            'where_text' => $where,
            'status' => 'new',
            'target_minutes' => $minutes,
            'placed_at' => now(),
        ]);

        foreach ($lines as $item) {
            $product = $products[$item->product_id];
            $category = $product->category;
            $ticket->items()->create([
                'product_id' => $item->product_id,
                'name' => $item->name,
                'qty' => $item->qty,
                'unit' => $item->unit,
                'station' => $category ? ($category->parent ? $category->parent->name : $category->name) : 'Kitchen',
                'note' => $item->note,
            ]);
        }

        return $ticket;
    }
}
