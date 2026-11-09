<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\KitchenTicket;
use App\Models\KitchenTicketItem;
use App\Models\StockSetting;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;

/**
 * Kitchen board. Tickets come from POS sales with "Send to kitchen" items (KitchenTicket::createForSale).
 * A user with a branch only sees their own kitchen, unless they have `kitchen.all-locations`.
 * The board asks for GET /api/kitchen every few seconds.
 */
#[Group('Kitchen', 'The kitchen board, per branch.', weight: 62)]
class KitchenController extends Controller
{
    /**
     * Kitchen board
     *
     * Open tickets (new, preparing, ready) and the ones served in the last 2 hours, oldest first, with their items.
     * `now` is the server time, so the board's timers are right even if the tablet's clock is not.
     */
    public function index(Request $request)
    {
        $request->validate(['branch_id' => 'nullable|integer|exists:branches,id']);
        $branchId = $this->branchId($request, $request->input('branch_id'));

        $tickets = KitchenTicket::with('items')->where('branch_id', $branchId)
            ->where(function ($q) {
                $q->whereIn('status', ['new', 'prep', 'ready'])
                    ->orWhere(fn ($q2) => $q2->where('status', 'served')->where('served_at', '>=', now()->subHours(2)));
            })
            ->orderBy('placed_at')->get();

        return [
            'now' => now(),
            'branch' => Branch::find($branchId, ['id', 'name']),
            'branches' => $this->ownBranchOnly($request) ? [] : Branch::where('active', true)->orderBy('name')->get(['id', 'name']),
            'tickets' => $tickets,
        ];
    }

    /**
     * Move a ticket
     *
     * new -> prep (Start), prep -> ready (Ready, ticks every item), ready -> served (Served / Picked up);
     * Recall moves back one step (ready -> prep, served -> ready). The status before is kept for Undo.
     */
    public function status(Request $request, KitchenTicket $ticket)
    {
        if (! $this->canUse($request, $ticket)) {
            return $this->notYourKitchen();
        }
        $data = $request->validate(['status' => 'required|in:new,prep,ready,served']);
        $new = $data['status'];
        if (! in_array($new, KitchenTicket::NEXT[$ticket->status], true)) {
            return response()->json(['message' => $ticket->ref.' is '.$ticket->status.' and cannot be moved to '.$new.'.'], 422);
        }

        $this->move($ticket, $new);

        return $ticket->load('items');
    }

    /**
     * Undo
     *
     * Puts the ticket back to the status it had before the last move.
     */
    public function undo(Request $request, KitchenTicket $ticket)
    {
        if (! $this->canUse($request, $ticket)) {
            return $this->notYourKitchen();
        }
        if (! $ticket->previous_status || $ticket->status === 'cancelled') {
            return response()->json(['message' => 'Nothing to undo on '.$ticket->ref.'.'], 422);
        }

        $ticket->update(['status' => $ticket->previous_status, 'previous_status' => null]);

        return $ticket->load('items');
    }

    /**
     * Tick an item
     *
     * Marks one line done or not done. Ticking an item on a new ticket starts it.
     */
    public function toggleItem(Request $request, KitchenTicket $ticket, KitchenTicketItem $item)
    {
        if (! $this->canUse($request, $ticket)) {
            return $this->notYourKitchen();
        }
        if ($item->kitchen_ticket_id !== $ticket->id) {
            return response()->json(['message' => 'That item is not on '.$ticket->ref.'.'], 422);
        }
        if (! in_array($ticket->status, ['new', 'prep'], true)) {
            return response()->json(['message' => $ticket->ref.' is '.$ticket->status.'.'], 422);
        }

        $item->update(['done' => ! $item->done]);
        if ($ticket->status === 'new') {
            $this->move($ticket, 'prep');
        }

        return $ticket->load('items');
    }

    /**
     * Rush
     *
     * Marks a ticket as rush (it goes to the top of its column), or takes the rush off.
     */
    public function rush(Request $request, KitchenTicket $ticket)
    {
        if (! $this->canUse($request, $ticket)) {
            return $this->notYourKitchen();
        }
        $ticket->update(['rush' => ! $ticket->rush]);

        return $ticket->load('items');
    }

    // ---------------------------------------------------------------------

    /** Changes the status and sets the time it happened. */
    private function move(KitchenTicket $ticket, string $new): void
    {
        $changes = ['status' => $new, 'previous_status' => $ticket->status];
        if ($new === 'prep' && ! $ticket->started_at) {
            $changes['started_at'] = now();
        }
        if ($new === 'ready') {
            $changes['ready_at'] = now();
            $ticket->items()->update(['done' => true]);
        }
        if ($new === 'served') {
            $changes['served_at'] = now();
        }
        $ticket->update($changes);
    }

    private function ownBranchOnly(Request $request): bool
    {
        $user = $request->user();

        return $user->branch_id && ! $user->hasPermission('kitchen.all-locations');
    }

    /** The kitchen to show: the user's own, else the one asked for, else the default branch. */
    private function branchId(Request $request, $asked): int
    {
        if ($this->ownBranchOnly($request)) {
            return (int) $request->user()->branch_id;
        }
        if ($asked) {
            return (int) $asked;
        }

        return (int) (StockSetting::current()->default_branch_id ?? Branch::where('active', true)->orderBy('id')->value('id'));
    }

    private function canUse(Request $request, KitchenTicket $ticket): bool
    {
        return ! $this->ownBranchOnly($request) || (int) $request->user()->branch_id === $ticket->branch_id;
    }

    private function notYourKitchen()
    {
        return response()->json(['message' => 'This ticket is in another branch\'s kitchen.'], 403);
    }
}
