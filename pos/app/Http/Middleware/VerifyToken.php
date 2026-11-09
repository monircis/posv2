<?php

namespace App\Http\Middleware;

use App\Models\AuthUser;
use Closure;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Checks the "Authorization: Bearer <token>" header for every API call.
 * The POS has no users table: it asks the auth service who the token belongs to
 * (GET /api/auth/me). So logout, deactivated users and role changes work at once.
 */
class VerifyToken
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();
        if (! $token) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout(5)
                ->get(config('services.auth.url').'/auth/me');
        } catch (Exception $e) {
            return response()->json(['message' => 'The auth service is not running.'], 503);
        }

        // 401 from the auth service: expired, logged out or not a real token.
        if ($response->failed()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $data = $response->json('user');
        if ($data['status'] !== 'active') {
            return response()->json(['message' => 'This account is deactivated.'], 401);
        }

        $user = new AuthUser();
        $user->id = $data['id'];
        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->branch_id = $data['branch_id'];
        $user->role = $data['role'];
        $user->permissions = $data['permissions'];

        // Now $request->user() returns this user in controllers.
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        return $next($request);
    }
}
