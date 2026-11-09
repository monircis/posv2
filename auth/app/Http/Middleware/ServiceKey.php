<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * For calls from other services (the POS backend), not from the browser.
 * They must send the SERVICE_KEY from .env in the "X-Service-Key" header.
 */
class ServiceKey
{
    public function handle(Request $request, Closure $next)
    {
        $key = config('services.internal.key');

        if (empty($key) || $request->header('X-Service-Key') !== $key) {
            return response()->json(['message' => 'Invalid service key.'], 401);
        }

        return $next($request);
    }
}
