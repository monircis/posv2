<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

abstract class Controller
{
    // ---- "See everyone's records" -------------------------------------------
    // A user with "<screen>.view-all" (e.g. purchases.view-all) sees everyone's records on that screen.
    // Without it they only see, open and change the records they made (the record's user_id).

    protected function seesAll(Request $request, string $screen): bool
    {
        return $request->user()->hasPermission($screen.'.view-all');
    }

    /** Adds "only mine" to a list query when the user cannot see everyone's records. */
    protected function onlyMine($query, Request $request, string $screen)
    {
        if (! $this->seesAll($request, $screen)) {
            $query->where('user_id', (string) $request->user()->id);
        }

        return $query;
    }

    /** May this user open (and change) this record? */
    protected function isMine(Request $request, $record, string $screen): bool
    {
        return $this->seesAll($request, $screen) || (string) $record->user_id === (string) $request->user()->id;
    }

    protected function notMine()
    {
        return response()->json(['message' => 'You can only open the records you made.'], 403);
    }
}
