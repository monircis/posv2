<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingTable;
use App\Models\Branch;
use App\Models\PaymentAccount;
use App\Models\StockSetting;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Bookings screen: table bookings for one branch.
 * A user with a branch only sees and books their own branch; a user with no branch picks one (branch_id).
 */
#[Group('Bookings', 'Table bookings per branch.', weight: 60)]
class BookingController extends Controller
{
    /**
     * Bookings of one day
     *
     * The branch's tables and the bookings on `date` (default today), with `week` (bookings per day for
     * that week), `deposits_held` and the opening hours. `branches` is the list to pick from (empty for a
     * user with a branch).
     */
    #[QueryParameter('branch_id', 'For a user with no branch. Default: the default branch.', type: 'int')]
    #[QueryParameter('date', 'YYYY-MM-DD. Default: today.')]
    public function index(Request $request)
    {
        $request->validate([
            'branch_id' => 'nullable|integer|exists:branches,id',
            'date' => 'nullable|date_format:Y-m-d',
        ]);
        $branchId = $this->branchId($request, $request->input('branch_id'));
        $date = $request->input('date', Booking::today());

        $bookings = $this->onlyMine(Booking::where('branch_id', $branchId)->where('date', $date), $request, 'bookings')->orderBy('start_time')->get();

        // Monday to Sunday of that week, with how many bookings each day has.
        $monday = Carbon::parse($date)->startOfWeek();
        $counts = $this->onlyMine(Booking::where('branch_id', $branchId), $request, 'bookings')
            ->whereBetween('date', [$monday->toDateString(), $monday->copy()->addDays(6)->toDateString()])
            ->whereIn('status', ['pending', 'confirmed', 'seated', 'completed'])
            ->selectRaw('date, COUNT(*) as n')->groupBy('date')->pluck('n', 'date');
        $week = [];
        for ($i = 0; $i < 7; $i++) {
            $day = $monday->copy()->addDays($i)->toDateString();
            $week[] = ['date' => $day, 'count' => (int) ($counts[$day] ?? 0)];
        }

        // Tables: the active ones, plus any switched-off table that still has a booking that day.
        $usedIds = $bookings->pluck('booking_table_id')->all();
        $tables = BookingTable::where('branch_id', $branchId)
            ->where(function ($q) use ($usedIds) {
                $q->where('active', true)->orWhereIn('id', $usedIds);
            })
            ->orderBy('sort')->orderBy('id')->get();

        $depositsHeld = Booking::where('branch_id', $branchId)->whereNotNull('deposit_paid_at')
            ->whereIn('status', Booking::HOLDS_TABLE)->sum('deposit');

        return [
            'branch' => Branch::find($branchId, ['id', 'name']),
            'branches' => $request->user()->branch_id ? [] : Branch::where('active', true)->orderBy('name')->get(['id', 'name']),
            'date' => $date,
            'today' => Booking::today(),
            'now' => Booking::nowMinutes(),
            'open' => Booking::OPEN,
            'close' => Booking::CLOSE,
            'tables' => $tables,
            'bookings' => $bookings->map(fn ($b) => $this->row($b))->all(),
            'week' => $week,
            'deposits_held' => round((float) $depositsHeld, 2),
            'next_ref' => Booking::nextRef(),
        ];
    }

    /**
     * Show booking
     *
     * One booking with its table and history.
     */
    public function show(Request $request, Booking $booking)
    {
        if (! $this->isMine($request, $booking, 'bookings')) {
            return $this->notMine();
        }
        if (! $this->isAtBranch($request, $booking->branch_id)) {
            return $this->notYourBranch();
        }

        return $this->detail($booking);
    }

    /**
     * New booking
     *
     * Checks the table is at the branch, seats the party, is free at that time, and the time is within
     * opening hours. A booking with a deposit starts `pending` (confirmed when the deposit is paid);
     * otherwise it starts `confirmed`.
     */
    public function store(Request $request)
    {
        $data = $request->validate($this->rules());
        $branchId = $this->branchId($request, $data['branch_id'] ?? null);
        if (! $this->isAtBranch($request, $branchId)) {
            return $this->notYourBranch();
        }

        $problem = $this->problem($data, $branchId, null);
        if ($problem) {
            return response()->json(['message' => $problem], 422);
        }

        $user = $request->user();
        $booking = DB::transaction(function () use ($data, $branchId, $user) {
            $deposit = round((float) ($data['deposit'] ?? 0), 2);
            $booking = Booking::create([
                'ref' => Booking::nextRef(),
                'branch_id' => $branchId,
                'booking_table_id' => $data['booking_table_id'],
                'contact_id' => $data['contact_id'] ?? null,
                'customer_name' => trim($data['customer_name']),
                'customer_phone' => $data['customer_phone'] ?? null,
                'date' => $data['date'],
                'start_time' => $data['start_time'],
                'duration' => $data['duration'],
                'size' => $data['size'],
                'status' => $deposit > 0 ? 'pending' : 'confirmed',
                'deposit' => $deposit,
                'source' => $data['source'],
                'notes' => $data['notes'] ?? null,
                'created_by' => $user->name,
                'user_id' => (string) request()->user()->id,
            ]);
            $booking->log('Created by '.$user->name.' ('.strtolower($data['source']).')'.($deposit > 0 ? ', waiting on the deposit' : ''), 'neutral', $user->name);

            return $booking;
        });

        return response()->json($this->detail($booking), 201);
    }

    /**
     * Edit booking
     *
     * The same checks as a new booking. Completed, no-show and cancelled bookings cannot be changed.
     */
    public function update(Request $request, Booking $booking)
    {
        if (! $this->isMine($request, $booking, 'bookings')) {
            return $this->notMine();
        }
        if (! $this->isAtBranch($request, $booking->branch_id)) {
            return $this->notYourBranch();
        }
        if (! in_array($booking->status, Booking::HOLDS_TABLE, true)) {
            return response()->json(['message' => 'A '.$booking->status.' booking cannot be changed. Make a new booking instead.'], 422);
        }

        $data = $request->validate($this->rules());
        $problem = $this->problem($data, $booking->branch_id, $booking);
        if ($problem) {
            return response()->json(['message' => $problem], 422);
        }

        $user = $request->user();
        // A paid deposit cannot be changed.
        $deposit = $booking->depositPaid() ? $booking->deposit : round((float) ($data['deposit'] ?? 0), 2);
        $booking->update([
            'booking_table_id' => $data['booking_table_id'],
            'contact_id' => $data['contact_id'] ?? null,
            'customer_name' => trim($data['customer_name']),
            'customer_phone' => $data['customer_phone'] ?? null,
            'date' => $data['date'],
            'start_time' => $data['start_time'],
            'duration' => $data['duration'],
            'size' => $data['size'],
            'deposit' => $deposit,
            'source' => $data['source'],
            'notes' => $data['notes'] ?? null,
        ]);
        $booking->log('Edited by '.$user->name, 'neutral', $user->name);

        return $this->detail($booking);
    }

    /**
     * Change status
     *
     * pending -> confirmed or cancelled; confirmed -> seated (today only), noshow or cancelled;
     * seated -> completed. A booking with an unpaid deposit cannot be confirmed.
     */
    public function status(Request $request, Booking $booking)
    {
        if (! $this->isMine($request, $booking, 'bookings')) {
            return $this->notMine();
        }
        if (! $this->isAtBranch($request, $booking->branch_id)) {
            return $this->notYourBranch();
        }
        $data = $request->validate(['status' => 'required|in:confirmed,seated,completed,noshow,cancelled']);
        $new = $data['status'];

        if (! in_array($new, Booking::NEXT[$booking->status], true)) {
            return response()->json(['message' => 'A '.$booking->status.' booking cannot be marked '.$new.'.'], 422);
        }
        if ($new === 'confirmed' && $booking->deposit > 0 && ! $booking->depositPaid()) {
            return response()->json(['message' => 'Take the '.number_format($booking->deposit, 2).' deposit first.'], 422);
        }
        if ($new === 'seated' && $booking->date->toDateString() !== Booking::today()) {
            return response()->json(['message' => 'Guests can only be seated on the day of the booking.'], 422);
        }

        $user = $request->user();
        $booking->update(['status' => $new]);
        $words = ['confirmed' => 'Confirmed', 'seated' => 'Seated', 'completed' => 'Completed', 'noshow' => 'Marked no-show', 'cancelled' => 'Cancelled'];
        $tones = ['confirmed' => 'info', 'seated' => 'info', 'completed' => 'success', 'noshow' => 'error', 'cancelled' => 'neutral'];
        $booking->log($words[$new].' by '.$user->name, $tones[$new], $user->name);

        return $this->detail($booking);
    }

    /**
     * Take deposit
     *
     * The deposit is paid by `method` cash (into the branch's cash drawer) or card (into the branch's
     * POS card account). A pending booking becomes confirmed.
     */
    public function deposit(Request $request, Booking $booking)
    {
        if (! $this->isMine($request, $booking, 'bookings')) {
            return $this->notMine();
        }
        if (! $this->isAtBranch($request, $booking->branch_id)) {
            return $this->notYourBranch();
        }
        $data = $request->validate(['method' => 'required|in:cash,card']);

        if ($booking->deposit <= 0 || $booking->depositPaid()) {
            return response()->json(['message' => 'There is no deposit to take on '.$booking->ref.'.'], 422);
        }
        if (! in_array($booking->status, Booking::HOLDS_TABLE, true)) {
            return response()->json(['message' => 'This booking is '.$booking->status.'.'], 422);
        }

        $user = $request->user();
        DB::transaction(function () use ($booking, $data, $user) {
            $account = $data['method'] === 'cash'
                ? PaymentAccount::cashDrawerFor($booking->branch_id)
                : PaymentAccount::defaultFor('posCard', $booking->branch_id);
            if ($account) {
                $account->addTransaction($booking->deposit, now()->toDateString(), 'deposit', 'Booking deposit', $booking->customer_name, $booking->ref);
            }

            $wasPending = $booking->status === 'pending';
            $booking->update([
                'deposit_paid_at' => now(),
                'deposit_method' => $data['method'],
                'payment_account_id' => $account ? $account->id : null,
                'status' => $wasPending ? 'confirmed' : $booking->status,
            ]);
            $booking->log('Deposit '.number_format($booking->deposit, 2).' paid by '.$data['method'].($wasPending ? ', booking confirmed' : ''), 'success', $user->name);
        });

        return $this->detail($booking->fresh());
    }

    // ---------------------------------------------------------------------

    private function rules(): array
    {
        return [
            'branch_id' => 'nullable|integer|exists:branches,id',
            'booking_table_id' => 'required|integer|exists:booking_tables,id',
            'contact_id' => 'nullable|integer|exists:contacts,id',
            'customer_name' => 'required|string|max:120',
            'customer_phone' => 'nullable|string|max:40',
            'date' => 'required|date_format:Y-m-d',
            'start_time' => ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'duration' => 'required|integer|min:15|max:600',
            'size' => 'required|integer|min:1|max:200',
            'deposit' => 'nullable|numeric|min:0|max:100000',
            'source' => 'required|in:Phone,Online,Walk-in',
            'notes' => 'nullable|string|max:500',
        ];
    }

    /** Why this booking cannot be saved, or null. $current is the booking being edited. */
    private function problem(array $data, int $branchId, ?Booking $current): ?string
    {
        $table = BookingTable::find($data['booking_table_id']);
        if (! $table || $table->branch_id !== $branchId) {
            return 'That table is not at this branch.';
        }
        if (! $table->active && (! $current || $current->booking_table_id !== $table->id)) {
            return $table->name.' is switched off.';
        }
        if ($table->seats < $data['size']) {
            return $table->name.' seats '.$table->seats.', the party is '.$data['size'].'.';
        }

        $start = Booking::minutes($data['start_time']);
        $end = $start + (int) $data['duration'];
        if ($start < Booking::minutes(Booking::OPEN) || $end > Booking::minutes(Booking::CLOSE)) {
            return 'Bookings are between '.Booking::OPEN.' and '.Booking::CLOSE.'.';
        }

        // A new booking (or a moved one) cannot be in the past.
        $moved = ! $current || $current->date->toDateString() !== $data['date'] || $current->start_time !== $data['start_time'];
        if ($moved && ($data['date'] < Booking::today() || ($data['date'] === Booking::today() && $start < Booking::nowMinutes()))) {
            return 'That time has passed.';
        }

        // The table must be free: no other booking holding it that overlaps.
        $others = Booking::where('booking_table_id', $table->id)->where('date', $data['date'])
            ->whereIn('status', Booking::HOLDS_TABLE)
            ->when($current, fn ($q) => $q->where('id', '!=', $current->id))
            ->get();
        foreach ($others as $other) {
            $otherStart = Booking::minutes($other->start_time);
            if ($start < $otherStart + $other->duration && $otherStart < $end) {
                return $table->name.' is booked '.$other->start_time.'–'.$other->endTime().' by '.$other->customer_name.'.';
            }
        }

        return null;
    }

    /** A booking as the list and the timeline show it. */
    private function row(Booking $booking): array
    {
        return [
            'id' => $booking->id,
            'ref' => $booking->ref,
            'booking_table_id' => $booking->booking_table_id,
            'contact_id' => $booking->contact_id,
            'customer_name' => $booking->customer_name,
            'customer_phone' => $booking->customer_phone,
            'date' => $booking->date->toDateString(),
            'start_time' => $booking->start_time,
            'end_time' => $booking->endTime(),
            'duration' => $booking->duration,
            'size' => $booking->size,
            'status' => $booking->status,
            'deposit' => $booking->deposit,
            'deposit_paid' => $booking->depositPaid(),
            'deposit_method' => $booking->deposit_method,
            'source' => $booking->source,
            'notes' => $booking->notes,
        ];
    }

    /** A booking with its table, history and what it can change to. */
    private function detail(Booking $booking): array
    {
        $booking->load('table', 'logs', 'branch:id,name');

        return $this->row($booking) + [
            'branch_id' => $booking->branch_id,
            'branch' => $booking->branch ? $booking->branch->name : '',
            'table' => $booking->table,
            'next' => Booking::NEXT[$booking->status],
            'created_by' => $booking->created_by,
            'logs' => $booking->logs->map(fn ($l) => ['text' => $l->text, 'tone' => $l->tone, 'at' => $l->created_at])->all(),
        ];
    }

    /** The branch to work in: the user's own, else the one asked for, else the default branch. */
    private function branchId(Request $request, $asked): int
    {
        if ($request->user()->branch_id) {
            return (int) $request->user()->branch_id;
        }
        if ($asked) {
            return (int) $asked;
        }

        return (int) (StockSetting::current()->default_branch_id ?? Branch::where('active', true)->orderBy('id')->value('id'));
    }

    private function isAtBranch(Request $request, int $branchId): bool
    {
        $userBranch = $request->user()->branch_id;

        return ! $userBranch || (int) $userBranch === $branchId;
    }

    private function notYourBranch()
    {
        return response()->json(['message' => 'You can only work with bookings at your own branch.'], 403);
    }
}
