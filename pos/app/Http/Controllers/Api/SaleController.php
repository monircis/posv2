<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BusinessSetting;
use App\Models\CoinSetting;
use App\Models\CashRegister;
use App\Models\InvoiceSetting;
use App\Models\KitchenTicket;
use App\Models\CoinTransaction;
use App\Models\Contact;
use App\Models\PaymentAccount;
use App\Models\PaymentSetting;
use App\Models\PosSetting;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\Tip;
use Carbon\Carbon;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Sales: the POS saves them here, and the Sell screens list and manage them.
 * Saving a sale takes the stock and adds the money to the payment accounts. All prices are worked out here.
 * A user with a branch only sees and changes sales of that branch.
 */
#[Group('Sales', 'POS sales: cash, card, split or on account. Status is paid, partial, due or cancelled.', weight: 16)]
class SaleController extends Controller
{
    /**
     * List sales
     *
     * 25 per page, newest first. Also returns `counts` for the tabs and the totals for the current filter.
     * Totals leave out cancelled sales, drafts and quotations. Needs `sell.view`.
     */
    #[QueryParameter('tab', 'paid, due (also part paid), draft, quote or cancelled.', type: 'string')]
    #[QueryParameter('search', 'Invoice, customer or phone.', type: 'string')]
    #[QueryParameter('branch_id', 'Only this branch.', type: 'int')]
    #[QueryParameter('channel', 'POS, Online or Order.', type: 'string')]
    #[QueryParameter('from', 'From this moment, e.g. 2026-09-01T00:00:00Z.', type: 'string')]
    #[QueryParameter('to', 'Up to this moment.', type: 'string')]
    public function index(Request $request)
    {
        $query = $this->filtered($request);
        $this->applyTab($query, $request->input('tab'));

        $counted = (clone $query)->whereIn('status', ['paid', 'partial', 'due']);
        $salesCount = (clone $counted)->count();
        $totalAmount = (float) (clone $counted)->sum('total');
        $totalPaid = (float) (clone $counted)->sum('paid');
        $totalDue = (float) (clone $counted)->sum('due');

        $page = $query->with('branch:id,name')
            ->orderBy('sold_at', 'desc')
            ->orderBy('id', 'desc')
            ->paginate(25);

        $counts = [];
        foreach (['all', 'paid', 'due', 'draft', 'quote', 'cancelled'] as $tab) {
            $countQuery = $this->filtered($request);
            $this->applyTab($countQuery, $tab);
            $counts[$tab] = $countQuery->count();
        }

        return [
            'data' => $page->items(),
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'total' => $page->total(),
            'counts' => $counts,
            'sales_count' => $salesCount,
            'total_amount' => round($totalAmount, 2),
            'total_paid' => round($totalPaid, 2),
            'total_due' => round($totalDue, 2),
        ];
    }

    /**
     * Show sale
     *
     * With the lines, the payments, the branch and the customer. Needs `sell.view`
     * (a user with a branch: only that branch).
     */
    public function show(Request $request, Sale $sale)
    {
        if (! $this->isAtBranch($request, $sale->branch_id)) {
            return $this->notYourBranch();
        }
        if (! $this->canSeeSale($request, $sale)) {
            return $this->notYourSale();
        }

        return $this->details($sale);
    }

    /**
     * Save sale
     *
     * Send the lines and the money taken; prices and totals are worked out here. `total` is the total the
     * cashier saw: if it does not match, nothing is saved. `payments` can be cash, card or both. Whatever is
     * not paid stays owing, which needs a customer with room on their credit limit. A discount over the POS
     * limit needs `sell.edit`. `coins_used` spends the customer's loyalty coins (Settings > Loyalty coins);
     * the customer also earns coins on the goods. Needs `sell.create` (a user with a branch: only that branch).
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'contact_id' => 'nullable|exists:contacts,id',
            'order_type' => 'required|in:Dine in,Takeaway,Delivery',
            'table_no' => 'nullable|integer|min:1',
            'discount_percent' => 'required|numeric|min:0|max:100',
            // The order discount as a fixed amount instead of a percentage.
            'discount_type' => 'nullable|in:percent,amount',
            'discount_amount' => 'nullable|numeric|min:0',
            'total' => 'required|numeric',
            'tendered' => 'nullable|numeric|min:0',
            'coins_used' => 'nullable|integer|min:0',
            'lines' => 'required|array|min:1',
            'lines.*.product_id' => 'required|exists:products,id',
            'lines.*.variant_id' => 'nullable|integer',
            'lines.*.qty' => 'required|numeric|gt:0',
            'lines.*.discount_percent' => 'required|numeric|min:0|max:100',
            'lines.*.note' => 'nullable|string|max:190',
            'payments' => 'present|array',
            'payments.*.method' => 'required|in:cash,card',
            'payments.*.amount' => 'required|numeric|gt:0',
            // A tip is kept apart from the sale: it is not in `total` and is paid out to the staff member later.
            'tip' => 'nullable|numeric|min:0|max:1000',
            'tip_method' => 'nullable|in:cash,card',
            'tip_employee_id' => 'nullable|integer|exists:employees,id',
        ]);
        $tip = Sale::cents((float) ($data['tip'] ?? 0));
        if ($tip > 0 && (empty($data['tip_method']) || empty($data['tip_employee_id']))) {
            return response()->json(['message' => 'Choose who the tip is for and how it was paid.'], 422);
        }

        $user = $request->user();
        if (! $this->isAtBranch($request, $data['branch_id'])) {
            return $this->notYourBranch();
        }

        // Settings > Sales & POS > Count cash before selling: the cashier needs an open register here.
        if (PosSetting::current()->require_register && ! CashRegister::openFor($user->id, (int) $data['branch_id'])) {
            return response()->json(['message' => 'Open the cash register first: count the cash in the drawer.'], 422);
        }

        $data['discount_type'] = $data['discount_type'] ?? 'percent';
        if ($data['discount_type'] === 'amount') {
            $data['discount_percent'] = 0;
        } else {
            $data['discount_amount'] = 0;
        }

        $limit = PosSetting::current()->max_discount_percent;
        $canOverLimit = $user->hasPermission('sell.edit');
        $biggestDiscount = max(array_merge([$data['discount_percent']], array_column($data['lines'], 'discount_percent')));
        $overLimit = $biggestDiscount > $limit;
        if ($overLimit && ! $canOverLimit) {
            return response()->json(['message' => 'A discount over '.$limit.'% needs a manager.'], 422);
        }

        $result = DB::transaction(function () use ($data, $user, $overLimit, $tip, $limit, $canOverLimit) {
            $items = $this->buildItems($data);
            if (is_string($items)) {
                return $items;
            }

            $contact = null;
            if (! empty($data['contact_id'])) {
                $contact = Contact::with('customerGroup')->lockForUpdate()->find($data['contact_id']);
                if ($contact->type === 'Supplier') {
                    return $contact->name.' is a supplier, not a customer.';
                }
                if (! $contact->isAtBranch((int) $data['branch_id'])) {
                    return $contact->name.' is a customer of another branch.';
                }
            }

            $coins = (int) ($data['coins_used'] ?? 0);
            $coinSettings = CoinSetting::current();

            $totals = $this->totals($items, $contact, $data, $coins, $coinSettings);
            // A fixed amount counts against the POS limit by the percentage it works out to.
            if ($data['discount_type'] === 'amount' && $totals['discount_percent'] > $limit) {
                if (! $canOverLimit) {
                    return 'A discount over '.$limit.'% needs a manager.';
                }
                $overLimit = true;
            }
            if ($coins > 0) {
                $problem = $this->coinProblem($contact, $coins, $totals, $coinSettings);
                if ($problem) {
                    return $problem;
                }
            }
            // Coins may pay for the whole sale; otherwise there must be something to charge.
            if ($totals['total'] <= 0 && $totals['coin_discount'] <= 0) {
                return 'There is nothing to charge on this sale.';
            }
            if (abs($totals['total'] - $data['total']) > 0.005) {
                return 'A price has changed since this screen loaded. The new total is '.number_format($totals['total'], 2).'. Please check the order.';
            }

            $cashTip = $tip > 0 && $data['tip_method'] === 'cash' ? $tip : 0;
            $money = $this->money($totals, $data['payments'], $data['tendered'] ?? null, $cashTip);
            if (is_string($money)) {
                return $money;
            }
            if ($coins > 0) {
                $money['method'] = $money['paid'] > 0 || $money['due'] > 0 ? $money['method'].' + Coins' : 'Coins';
            }

            if ($money['due'] > 0) {
                $problem = $this->creditProblem($contact, $money['due']);
                if ($problem) {
                    return $problem;
                }
            }

            // $money comes first: its total includes the cash rounding and the card surcharge.
            $sale = Sale::create($money + $totals + [
                'ref' => Sale::takeRef((int) $data['branch_id']),
                'sold_at' => now(),
                'branch_id' => $data['branch_id'],
                'contact_id' => $contact ? $contact->id : null,
                'customer_name' => $contact ? $contact->name : 'Walk in',
                'channel' => 'POS',
                'order_type' => $data['order_type'],
                'table_no' => $data['order_type'] === 'Dine in' ? ($data['table_no'] ?? null) : null,
                'item_count' => count($items),
                'discount_approved_by' => $overLimit ? $user->name : null,
                'due_date' => $money['due'] > 0 ? now()->addDays($this->payDays($contact))->toDateString() : null,
                'created_by' => $user->name,
                'user_id' => (string) $user->id,
            ]);

            $sale->items()->createMany($items);

            // Items marked "Send to kitchen" go to this branch's Kitchen board.
            KitchenTicket::createForSale($sale);

            foreach ($items as $item) {
                if ($item['stock_taken']) {
                    StockMovement::record($item['product_id'], $item['variant_id'], $sale->branch_id, -$item['qty'], 'Sale', $sale->ref, null, $user->name);
                }
            }

            foreach ($this->combined($data['payments']) as $method => $amount) {
                // The card payment also carries the surcharge.
                $charged = $method === 'card' ? Sale::cents($amount + $money['card_surcharge']) : $amount;
                $sale->addPayment($method, $charged, $user->name, 'POS sale');
                if ($method === 'cash') {
                    CashRegister::noteCash($user, $sale->branch_id, 'sale', $charged, $sale->ref);
                }
            }

            if ($tip > 0) {
                $this->saveTip($sale, $tip, $data['tip_method'], (int) $data['tip_employee_id'], $user->name);
                if ($data['tip_method'] === 'cash') {
                    CashRegister::noteCash($user, $sale->branch_id, 'tip', $tip, $sale->ref);
                }
            }

            if ($contact && $money['due'] > 0) {
                $contact->balance = Sale::cents($contact->balance + $money['due']);
                $contact->save();
            }

            if ($coins > 0) {
                CoinTransaction::record($contact, -$coins, 'Spent', $sale, null, $user->name);
            }
            if ($contact && $totals['coins_earned'] > 0) {
                CoinTransaction::record($contact, $totals['coins_earned'], 'Earned', $sale, null, $user->name);
            }

            return $sale;
        });

        if (is_string($result)) {
            return response()->json(['message' => $result], 422);
        }

        return response()->json($this->details($result) + ['next_ref' => Sale::nextRef($result->branch_id)], 201);
    }

    /**
     * Receive payment
     *
     * For a sale that still has money owing. Lowers what the customer owes. It cannot be more than what is due.
     * Needs `sell.create` or `sell.edit` (a user with a branch: only that branch).
     */
    public function receive(Request $request, Sale $sale)
    {
        if (! $this->isAtBranch($request, $sale->branch_id)) {
            return $this->notYourBranch();
        }
        if (! $this->canSeeSale($request, $sale)) {
            return $this->notYourSale();
        }

        $data = $request->validate([
            'method' => 'required|in:cash,card',
            'amount' => 'required|numeric|gt:0',
        ]);
        $user = $request->user();
        $userName = $user->name;

        $result = DB::transaction(function () use ($data, $sale, $user, $userName) {
            $locked = Sale::with('branch')->lockForUpdate()->find($sale->id);
            $amount = Sale::cents($data['amount']);

            if (! in_array($locked->status, ['partial', 'due'], true)) {
                return $locked->ref.' has nothing owing.';
            }
            if ($amount > $locked->due) {
                return 'Only '.number_format($locked->due, 2).' is due on '.$locked->ref.'.';
            }

            // A card payment also pays the surcharge from Settings > Payments.
            $surcharge = $data['method'] === 'card' ? Sale::cents($amount * PaymentSetting::current()->card_surcharge / 100) : 0;

            $locked->addPayment($data['method'], Sale::cents($amount + $surcharge), $userName, 'Payment received');
            if ($data['method'] === 'cash') {
                CashRegister::noteCash($user, $locked->branch_id, 'received', $amount, $locked->ref);
            }
            $locked->card_surcharge = Sale::cents($locked->card_surcharge + $surcharge);
            $locked->gst = Sale::cents($locked->gst + $surcharge / 11);
            $locked->total = Sale::cents($locked->total + $surcharge);
            $locked->paid = Sale::cents($locked->paid + $amount + $surcharge);
            $locked->due = Sale::cents($locked->due - $amount);
            $locked->status = $locked->due <= 0 ? 'paid' : 'partial';
            $locked->method = $this->methodLabel($locked);
            $locked->save();

            $this->lowerContactBalance($locked, $amount);

            return $locked;
        });

        if (is_string($result)) {
            return response()->json(['message' => $result], 422);
        }

        return $this->details($result);
    }

    /**
     * Cancel sale
     *
     * Puts the stock back, takes the money back out of the payment accounts and clears what the customer owes.
     * The sale stays in the list, marked as cancelled. Needs `sell.edit` (a user with a branch: only that branch).
     */
    public function cancel(Request $request, Sale $sale)
    {
        if (! $this->isAtBranch($request, $sale->branch_id)) {
            return $this->notYourBranch();
        }

        $data = $request->validate(['reason' => 'required|string|max:190']);
        $user = $request->user();
        $userName = $user->name;

        $result = DB::transaction(function () use ($data, $sale, $user, $userName) {
            // Locking the row stops the same sale being cancelled twice at the same moment.
            $locked = Sale::lockForUpdate()->find($sale->id);

            if (! in_array($locked->status, ['paid', 'partial', 'due'], true)) {
                return $locked->ref.' cannot be cancelled.';
            }

            foreach ($locked->items as $item) {
                if ($item->stock_taken) {
                    StockMovement::record($item->product_id, $item->variant_id, $locked->branch_id, $item->qty, 'Sale cancelled', $locked->ref, $data['reason'], $userName);
                }
            }

            foreach ($locked->payments as $payment) {
                if ($payment->payment_account_id) {
                    PaymentAccount::lockForUpdate()->find($payment->payment_account_id)
                        ->addTransaction(-$payment->amount, now()->toDateString(), 'refund', 'Sale cancelled', $locked->customer_name, $locked->ref);
                }
                // Cash handed back comes out of the drawer of whoever cancels, if they have a register open.
                if ($payment->method === 'cash') {
                    CashRegister::noteCash($user, $locked->branch_id, 'refund', -$payment->amount, $locked->ref, 'Sale cancelled');
                }
            }

            $this->lowerContactBalance($locked, $locked->due);

            // A tip not yet paid out goes back too. One already paid to the staff member stays.
            $tip = Tip::where('sale_id', $locked->id)->whereNull('paid_at')->first();
            if ($tip) {
                if ($tip->payment_account_id && $account = PaymentAccount::lockForUpdate()->find($tip->payment_account_id)) {
                    $account->addTransaction(-$tip->amount, now()->toDateString(), 'refund', 'Tip given back · sale cancelled', $locked->customer_name, $locked->ref);
                }
                if ($tip->method === 'cash') {
                    CashRegister::noteCash($user, $locked->branch_id, 'refund', -$tip->amount, $locked->ref, 'Tip given back');
                }
                $tip->delete();
            }

            // Coins spent go back to the customer; coins earned are taken off (this can leave them below 0).
            if ($locked->contact_id && ($locked->coins_used > 0 || $locked->coins_earned > 0)) {
                $contact = Contact::lockForUpdate()->find($locked->contact_id);
                if ($locked->coins_used > 0) {
                    CoinTransaction::record($contact, $locked->coins_used, 'Sale cancelled', $locked, 'Coins spent given back', $userName);
                }
                if ($locked->coins_earned > 0) {
                    CoinTransaction::record($contact, -$locked->coins_earned, 'Sale cancelled', $locked, 'Coins earned taken off', $userName);
                }
            }

            // Take the order off the Kitchen board if it was not served yet.
            KitchenTicket::where('sale_id', $locked->id)->whereIn('status', ['new', 'prep', 'ready'])->update(['status' => 'cancelled']);

            $locked->status = 'cancelled';
            $locked->due = 0;
            $locked->cancelled_at = now();
            $locked->cancelled_by = $userName;
            $locked->cancel_reason = $data['reason'];
            $locked->save();

            return $locked;
        });

        if (is_string($result)) {
            return response()->json(['message' => $result], 422);
        }

        return $this->details($result);
    }

    // ---------------------------------------------------------------------

    /** The sale with everything the details page and the receipt need. */
    private function details(Sale $sale): array
    {
        $sale->load('branch:id,name,address', 'contact.customerGroup:id,name', 'items', 'payments', 'tip.employee:id,first,last');

        $row = $sale->toArray();
        $row['business'] = BusinessSetting::current()->only('business_name', 'legal_name', 'abn', 'address', 'phone', 'email', 'logo_url');
        // Settings > Invoice & receipt: how the printed invoice looks.
        $row['invoice'] = InvoiceSetting::current()->only('receipt_prefix', 'paper', 'show_logo', 'show_abn', 'payment_terms', 'footer_text');

        return $row;
    }

    /** Sales this user may see, with the search filters. */
    private function filtered(Request $request)
    {
        $query = Sale::query();

        $userBranch = $request->user()->branch_id;
        if ($userBranch) {
            $query->where('branch_id', $userBranch);
        }
        // Without "see everyone's sales", a user only sees the sales they made.
        if (! $this->seesAllSales($request)) {
            $query->where('user_id', (string) $request->user()->id);
        }

        $search = trim((string) $request->input('search'));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('ref', 'like', "%$search%")
                    ->orWhere('customer_name', 'like', "%$search%")
                    ->orWhereHas('contact', function ($c) use ($search) {
                        $c->where('phone', 'like', "%$search%");
                    });
            });
        }

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->input('branch_id'));
        }
        if ($request->filled('channel')) {
            $query->where('channel', $request->input('channel'));
        }
        if ($request->filled('from')) {
            $query->where('sold_at', '>=', $this->moment($request->input('from')));
        }
        if ($request->filled('to')) {
            $query->where('sold_at', '<=', $this->moment($request->input('to')));
        }

        return $query;
    }

    private function applyTab($query, ?string $tab): void
    {
        if ($tab === 'paid') {
            $query->where('status', 'paid');
        } elseif ($tab === 'due') {
            $query->whereIn('status', ['partial', 'due']);
        } elseif (in_array($tab, ['draft', 'quote', 'cancelled'], true)) {
            $query->where('status', $tab);
        }
    }

    /** The browser sends the start of its own day, so "today" is right wherever the shop is. */
    private function moment(string $value): Carbon
    {
        return Carbon::parse($value)->setTimezone(config('app.timezone'));
    }

    /**
     * Turn the POS lines into sale_items rows, with the price from the database.
     * Returns the rows, or a message (string) if a line is not allowed.
     */
    private function buildItems(array $data)
    {
        $items = [];
        $used = [];
        $allowBelow = PosSetting::current()->allow_below_stock;

        foreach ($data['lines'] as $line) {
            $product = Product::with('unit:id,short_name,allow_decimals', 'taxRate:id,rate')->find($line['product_id']);

            if (! $product->active || ! $product->available_at_pos) {
                return $product->name.' is not for sale on the POS.';
            }
            if (! $product->branches()->where('branches.id', $data['branch_id'])->exists()) {
                return $product->name.' is not sold at this branch.';
            }

            $variant = null;
            if ($product->type === 'variable') {
                $variant = ProductVariant::where('product_id', $product->id)->where('id', $line['variant_id'] ?? 0)->first();
                if (! $variant) {
                    return 'Pick one of the variants of '.$product->name.'.';
                }
            }

            $name = $variant ? $product->name.' · '.$variant->name : $product->name;
            $qty = round((float) $line['qty'], 3);

            if ($product->unit && ! $product->unit->allow_decimals && floor($qty) != $qty) {
                return $name.': whole units only.';
            }

            // The same product can be on two lines, e.g. with different notes.
            $key = $product->id.'-'.($variant ? $variant->id : 0);
            $used[$key] = round(($used[$key] ?? 0) + $qty, 3);

            if ($product->manage_stock && ! $product->allow_negative_stock && ! $allowBelow) {
                // Locking the row stops two sales at the same moment selling the same stock.
                $onHand = (float) ProductStock::where('product_id', $product->id)
                    ->where('variant_id', $variant ? $variant->id : null)
                    ->where('branch_id', $data['branch_id'])
                    ->lockForUpdate()
                    ->value('qty');
                if ($used[$key] > $onHand) {
                    return 'Only '.$onHand.' of '.$name.' left.';
                }
            }

            $price = $product->salePrice($variant, (int) $data['branch_id']); // with today's price rule for this branch

            $items[] = [
                'product_id' => $product->id,
                'variant_id' => $variant ? $variant->id : null,
                'name' => $name,
                'sku' => $variant ? $variant->sku : $product->sku,
                'unit' => $product->unit ? $product->unit->short_name : 'ea',
                'qty' => $qty,
                'price' => $price,
                'tax_rate' => $product->taxRate ? (float) $product->taxRate->rate : 0,
                'discount_percent' => (float) $line['discount_percent'],
                'total' => Sale::cents($price * $qty * (1 - $line['discount_percent'] / 100)),
                'note' => $line['note'] ?? null,
                'stock_taken' => (bool) $product->manage_stock,
            ];
        }

        return $items;
    }

    /**
     * The same sums as calc() in the POS screen. GST comes from each line's own tax rate.
     * Coins spent come off the goods like a discount; coins are earned on the goods left after that.
     */
    private function totals(array $items, ?Contact $contact, array $data, int $coins, CoinSetting $coinSettings): array
    {
        $subtotal = 0;
        $taxPart = 0;
        foreach ($items as $item) {
            $subtotal += $item['total'];
            $taxPart += $item['total'] * $item['tax_rate'] / (100 + $item['tax_rate']);
        }
        $subtotal = Sale::cents($subtotal);

        $groupPercent = $contact && $contact->customerGroup ? (float) $contact->customerGroup->discount : 0;
        $groupDiscount = Sale::cents($subtotal * $groupPercent / 100);
        $base = Sale::cents($subtotal - $groupDiscount);
        if ($data['discount_type'] === 'amount') {
            // A fixed amount, never more than the goods; also kept as the percentage it works out to.
            $discount = Sale::cents(min((float) ($data['discount_amount'] ?? 0), $base));
            $discountPercent = $base > 0 ? round($discount / $base * 100, 2) : 0;
        } else {
            $discount = Sale::cents($base * $data['discount_percent'] / 100);
            $discountPercent = (float) $data['discount_percent'];
        }
        $fee = $data['order_type'] === 'Delivery' ? Sale::DELIVERY_FEE : 0;
        $coinDiscount = Sale::cents($coins * $coinSettings->coin_value);
        $goods = Sale::cents($subtotal - $groupDiscount - $discount - $coinDiscount);
        $total = Sale::cents($goods + $fee);
        $share = $subtotal > 0 ? $goods / $subtotal : 0;

        return [
            'subtotal' => $subtotal,
            'group_percent' => $groupPercent,
            'group_discount' => $groupDiscount,
            'discount_percent' => $discountPercent,
            'discount_type' => $data['discount_type'],
            'discount' => $discount,
            'coins_used' => $coins,
            'coin_discount' => $coinDiscount,
            'coins_earned' => $contact ? $coinSettings->earned($goods) : 0,
            'delivery_fee' => $fee,
            'gst' => Sale::cents($taxPart * $share + $fee / 11),
            'total' => $total,
        ];
    }

    /** Why these coins cannot be spent on this sale, or null if they can. */
    private function coinProblem(?Contact $contact, int $coins, array $totals, CoinSetting $settings): ?string
    {
        if (! $settings->enabled) {
            return 'Loyalty coins are switched off in Settings.';
        }
        if (! $contact) {
            return 'Pick a customer to spend coins.';
        }
        if ($coins > $contact->coins) {
            return $contact->name.' only has '.$contact->coins.' coins.';
        }
        if ($coins < $settings->min_redeem) {
            return 'At least '.$settings->min_redeem.' coins must be spent at a time.';
        }

        $goods = $totals['subtotal'] - $totals['group_discount'] - $totals['discount'];
        $most = $settings->mostCoins($goods);
        if ($coins > $most) {
            return 'Coins can pay at most '.$settings->max_redeem_percent.'% of the goods: '.$most.' coins on this sale.';
        }

        return null;
    }

    /**
     * What was paid and what stays owing, following Settings > Payments: only a sale paid fully in cash
     * is rounded to 5c, and a card payment adds the surcharge on top.
     * Returns the fields, or a message (string) if the payments are not right.
     */
    /** The tip money goes into the drawer or the card account, as its own line, and the tip is kept for the staff member. */
    private function saveTip(Sale $sale, float $tip, string $method, int $employeeId, string $userName): void
    {
        $account = $sale->accountFor($method);
        if ($account) {
            $account->addTransaction($tip, now()->toDateString(), 'tip', 'Tip', $sale->customer_name, $sale->ref);
        }

        Tip::create([
            'sale_id' => $sale->id,
            'branch_id' => $sale->branch_id,
            'employee_id' => $employeeId,
            'amount' => $tip,
            'method' => $method,
            'payment_account_id' => $account ? $account->id : null,
            'created_by' => $userName,
        ]);
    }

    /** $cashTip: a tip paid in cash, handed over with the cash for the sale. */
    private function money(array $totals, array $payments, ?float $tendered, float $cashTip = 0)
    {
        $settings = PaymentSetting::current();
        $total = $totals['total'];
        $amounts = $this->combined($payments);
        $paid = Sale::cents(array_sum($amounts));

        if (count($amounts) > 1 && ! $settings->allow_split_payments) {
            return 'Split payments are switched off in Settings.';
        }

        $rounding = 0;
        $cashTotal = Sale::cents(Sale::cashRounded($total));
        if ($settings->round_cash_5c && array_keys($amounts) === ['cash'] && abs($paid - $cashTotal) < 0.005) {
            $rounding = Sale::cents($cashTotal - $total);
            $total = $cashTotal;
        }

        if ($paid > $total + 0.005) {
            return 'The payments are more than the total of '.number_format($total, 2).'.';
        }

        $cash = $amounts['cash'] ?? 0;
        $change = 0;
        if ($tendered !== null && $cash > 0) {
            if ($tendered < $cash + $cashTip - 0.005) {
                return $cashTip > 0 ? 'The cash handed over is less than the cash amount plus the tip.' : 'The cash handed over is less than the cash amount.';
            }
            $change = Sale::cents($tendered - $cash - $cashTip);
        }

        $due = Sale::cents($total - $paid);
        $surcharge = Sale::cents(($amounts['card'] ?? 0) * $settings->card_surcharge / 100);

        return [
            'total' => Sale::cents($total + $surcharge),
            'rounding' => $rounding,
            'card_surcharge' => $surcharge,
            'gst' => Sale::cents($totals['gst'] + $surcharge / 11),
            'paid' => Sale::cents($paid + $surcharge),
            'due' => $due,
            'tendered' => $cash > 0 ? ($tendered ?? $cash) : null,
            'change_given' => $change,
            'method' => $this->label(array_keys($amounts)),
            'status' => $due <= 0 ? 'paid' : ($paid > 0 ? 'partial' : 'due'),
        ];
    }

    /** Two cash rows on a split payment become one. ['cash' => 20, 'card' => 30] */
    private function combined(array $payments): array
    {
        $amounts = [];
        foreach ($payments as $payment) {
            $amounts[$payment['method']] = Sale::cents(($amounts[$payment['method']] ?? 0) + $payment['amount']);
        }

        return $amounts;
    }

    private function label(array $methods): string
    {
        if (! $methods) {
            return 'On account';
        }

        sort($methods); // card, cash

        return implode(' + ', array_map('ucfirst', $methods));
    }

    private function methodLabel(Sale $sale): string
    {
        $label = $this->label($sale->payments()->pluck('method')->unique()->values()->all());

        return $sale->coins_used > 0 ? $label.' + Coins' : $label;
    }

    /** Why this customer cannot owe this much, or null if they can. */
    private function creditProblem(?Contact $contact, float $due): ?string
    {
        if (! $contact) {
            return 'A walk-in customer has to pay in full. Pick a customer to leave money owing.';
        }
        if ($contact->credit_limit <= 0) {
            return $contact->name.' has no credit limit, so they have to pay in full.';
        }
        if ($contact->balance + $due > $contact->credit_limit + 0.005) {
            return 'This would take '.$contact->name.' over their credit limit of '.number_format($contact->credit_limit, 2).'.';
        }

        return null;
    }

    /** "30 days" on the contact, or else on their customer group. Otherwise 30. */
    private function payDays(?Contact $contact): int
    {
        $terms = $contact->pay_term ?: ($contact->customerGroup ? $contact->customerGroup->terms : '');

        return preg_match('/\d+/', (string) $terms, $match) ? (int) $match[0] : 30;
    }

    private function lowerContactBalance(Sale $sale, float $amount): void
    {
        if ($sale->contact_id && $amount > 0) {
            $contact = Contact::lockForUpdate()->find($sale->contact_id);
            $contact->balance = Sale::cents($contact->balance - $amount);
            $contact->save();
        }
    }

    /** A user with no branch works everywhere; otherwise only at their own branch. */
    private function isAtBranch(Request $request, int $branchId): bool
    {
        $userBranch = $request->user()->branch_id;

        return ! $userBranch || (int) $userBranch === $branchId;
    }

    private function notYourBranch()
    {
        return response()->json(['message' => 'You can only work with sales of your own branch.'], 403);
    }

    /** "See everyone's sales" (sell.view-all). Without it a user only sees the sales they made. */
    private function seesAllSales(Request $request): bool
    {
        return $request->user()->hasPermission('sell.view-all');
    }

    private function canSeeSale(Request $request, Sale $sale): bool
    {
        return $this->seesAllSales($request) || $sale->user_id === (string) $request->user()->id;
    }

    private function notYourSale()
    {
        return response()->json(['message' => 'You can only open the sales you made.'], 403);
    }
}
