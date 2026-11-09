<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingTable;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;

/**
 * The tables and rooms that can be booked, per branch (Bookings > Tables).
 * A user with a branch only manages their own branch's tables.
 */
#[Group('Bookings', 'Table bookings per branch.', weight: 60)]
class BookingTableController extends Controller
{
    /**
     * List tables
     *
     * All tables of a branch, switched-off ones too.
     */
    public function index(Request $request)
    {
        $request->validate(['branch_id' => 'required|integer|exists:branches,id']);
        $branchId = (int) $request->input('branch_id');
        if (! $this->isAtBranch($request, $branchId)) {
            return $this->notYourBranch();
        }

        return BookingTable::where('branch_id', $branchId)->orderBy('sort')->orderBy('id')->get();
    }

    /**
     * Add table
     */
    public function store(Request $request)
    {
        $data = $request->validate($this->rules() + ['branch_id' => 'required|integer|exists:branches,id']);
        if (! $this->isAtBranch($request, (int) $data['branch_id'])) {
            return $this->notYourBranch();
        }
        if (BookingTable::where('branch_id', $data['branch_id'])->where('name', $data['name'])->exists()) {
            return response()->json(['message' => $data['name'].' already exists at this branch.'], 422);
        }

        $data['deposit_per_head'] = $data['deposit_per_head'] ?? 0;
        $data['sort'] = (int) BookingTable::where('branch_id', $data['branch_id'])->max('sort') + 1;
        $table = BookingTable::create($data);

        return response()->json($table, 201);
    }

    /**
     * Edit table
     *
     * Name, seats, zone, deposit per guest, and `active` (a switched-off table cannot take new bookings).
     */
    public function update(Request $request, BookingTable $bookingTable)
    {
        if (! $this->isAtBranch($request, $bookingTable->branch_id)) {
            return $this->notYourBranch();
        }
        $data = $request->validate($this->rules() + ['active' => 'required|boolean']);
        if (BookingTable::where('branch_id', $bookingTable->branch_id)->where('name', $data['name'])->where('id', '!=', $bookingTable->id)->exists()) {
            return response()->json(['message' => $data['name'].' already exists at this branch.'], 422);
        }

        $data['deposit_per_head'] = $data['deposit_per_head'] ?? 0;
        $bookingTable->update($data);

        return $bookingTable;
    }

    /**
     * Delete table
     *
     * Only a table that was never booked. Otherwise switch it off.
     */
    public function destroy(Request $request, BookingTable $bookingTable)
    {
        if (! $this->isAtBranch($request, $bookingTable->branch_id)) {
            return $this->notYourBranch();
        }
        if (Booking::where('booking_table_id', $bookingTable->id)->exists()) {
            return response()->json(['message' => $bookingTable->name.' has bookings, so it cannot be deleted. Switch it off instead.'], 422);
        }
        $bookingTable->delete();

        return response()->noContent();
    }

    private function rules(): array
    {
        return [
            'name' => 'required|string|max:60',
            'seats' => 'required|integer|min:1|max:200',
            'zone' => 'nullable|string|max:60',
            'deposit_per_head' => 'nullable|numeric|min:0|max:1000',
        ];
    }

    private function isAtBranch(Request $request, int $branchId): bool
    {
        $userBranch = $request->user()->branch_id;

        return ! $userBranch || (int) $userBranch === $branchId;
    }

    private function notYourBranch()
    {
        return response()->json(['message' => 'You can only manage tables at your own branch.'], 403);
    }
}
