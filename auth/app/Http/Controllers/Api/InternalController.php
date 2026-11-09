<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\HeaderParameter;

/** Calls from other services (the POS backend), not from the browser. Needs X-Service-Key. */
#[Group('Internal', 'For other services only. Send the shared SERVICE_KEY in X-Service-Key; no user token.', weight: 4)]
class InternalController extends Controller
{
    /**
     * Branch user counts
     *
     * How many users each POS branch has, e.g. { "1": 2, "3": 6 }. A wrong key answers 401.
     */
    #[HeaderParameter('X-Service-Key', 'The SERVICE_KEY from the auth service .env', required: true)]
    public function branchUserCounts()
    {
        $counts = [];
        $users = User::whereNotNull('branch_id')->get();

        foreach ($users as $user) {
            if (! isset($counts[$user->branch_id])) {
                $counts[$user->branch_id] = 0;
            }
            $counts[$user->branch_id]++;
        }

        return response()->json($counts);
    }
}
