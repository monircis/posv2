<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Route example: ->middleware('permission:products.view|sell.view')
 * The user needs at least one of the permissions. Runs after VerifyToken.
 */
class Permission
{
    public function handle(Request $request, Closure $next, $permissions)
    {
        $user = $request->user();

        foreach (explode('|', $permissions) as $permission) {
            if ($user->hasPermission($permission)) {
                return $next($request);
            }
        }

        return response()->json(['message' => 'You do not have permission to do this.'], 403);
    }
}
