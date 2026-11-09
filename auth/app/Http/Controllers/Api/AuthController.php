<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RefreshToken;
use App\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;
use PHPOpenSourceSaver\JWTAuth\JWTGuard;

/**
 * JWT sign-in for the Vue app: register, login, me, refresh, logout.
 * Sign-in gives a short-lived JWT (`token`, JWT_TTL minutes) and a long-lived `refresh_token`
 * (REFRESH_TOKEN_DAYS days). When the JWT runs out, POST /auth/token/refresh swaps the refresh token
 * for a new pair, so the user stays signed in without typing the password again.
 */
#[Group('Sign in', 'Get a JWT token and manage it.', weight: 1)]
class AuthController extends Controller
{
    /**
     * Register
     *
     * Creates an account and signs in. The first account becomes Owner; later accounts start as Cashier.
     * Limited to 10 calls per minute.
     */
    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|email|max:190|unique:users,email',
            'phone' => 'nullable|string|max:40',
            'password' => 'required|string|min:6|confirmed',
        ]);

        // The branch is set later by the Owner in User Management.
        $data['email'] = strtolower($data['email']);
        $user = User::create($data + ['status' => 'active']);

        // The first account becomes the Owner; everyone after starts as a Cashier
        // until the Owner changes their role in User Management.
        $user->assignRole(User::count() === 1 ? 'Owner' : 'Cashier');

        return $this->tokenResponse($user, $this->guard()->login($user), 201, RefreshToken::issue($user, $request));
    }

    /**
     * Login
     *
     * Checks the email and password and returns a JWT token (valid 60 minutes) and a refresh token (valid 14 days).
     * Deactivated (`off`) and `invited` users get 422. Wrong email or password gets 401.
     * Limited to 10 calls per minute.
     */
    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', strtolower($data['email']))->first();

        if ($user && $user->status === 'off') {
            return $this->fail($user->name.'’s account is deactivated. Ask the owner to reactivate it in User Management.');
        }
        if ($user && $user->status === 'invited') {
            return $this->fail($user->name.' hasn’t accepted the invitation yet. Use the link in the invite email to set a password.');
        }

        $token = $this->guard()->attempt(['email' => strtolower($data['email']), 'password' => $data['password']]);
        if (! $token) {
            return $this->fail('Email or password is incorrect.', 401);
        }

        $user->forceFill(['last_login_at' => now()])->save();

        return $this->tokenResponse($user, $token, 200, RefreshToken::issue($user, $request));
    }

    /**
     * Current user
     *
     * The user who owns the token, with role and permissions.
     * Other services (the POS backend) call this to check a token.
     */
    public function me()
    {
        return response()->json(['user' => $this->guard()->user()->toProfile()]);
    }

    /**
     * Refresh token
     *
     * Returns a new token with fresh role and permissions. The old token stops working.
     */
    public function refresh()
    {
        return $this->tokenResponse($this->guard()->user(), $this->guard()->refresh());
    }

    /**
     * New token from a refresh token
     *
     * Send `refresh_token` (no Authorization header needed). Returns a new `token` and a new `refresh_token`;
     * the one sent stops working. A refresh token that was already used signs the user out everywhere,
     * because someone else may have a copy of it. Deactivated users get 401. Limited to 60 calls per minute.
     */
    public function refreshWithToken(Request $request)
    {
        $data = $request->validate(['refresh_token' => 'required|string']);
        $stored = RefreshToken::with('user')->where('token_hash', hash('sha256', $data['refresh_token']))->first();

        if (! $stored) {
            return $this->fail('Your sign-in has run out. Please sign in again.', 401);
        }
        if ($stored->revoked_at && $stored->revoked_at->gt(now()->subSeconds(30))) {
            // Just swapped, e.g. by another tab of the same browser at the same moment: that tab has the new one.
            return $this->fail('This sign-in was just renewed. Use the new token.', 401);
        }
        if ($stored->revoked_at) {
            // Used twice: the token may have been copied. Stop every session of this user.
            RefreshToken::revokeAllFor($stored->user_id);

            return $this->fail('Your sign-in was used somewhere else, so you were signed out everywhere. Please sign in again.', 401);
        }
        if (! $stored->isUsable() || ! $stored->user || $stored->user->status !== 'active') {
            return $this->fail('Your sign-in has run out. Please sign in again.', 401);
        }

        // One use only: this one is revoked and a new one is given.
        $stored->update(['revoked_at' => now()]);
        $user = $stored->user;

        return $this->tokenResponse($user, $this->guard()->login($user), 200, RefreshToken::issue($user, $request));
    }

    /**
     * Revoke a refresh token
     *
     * Send `refresh_token`: it stops working (used when signing out, even after the JWT has run out).
     */
    public function revoke(Request $request)
    {
        $data = $request->validate(['refresh_token' => 'required|string']);
        $stored = RefreshToken::findPlain($data['refresh_token']);
        if ($stored && ! $stored->revoked_at) {
            $stored->update(['revoked_at' => now()]);
        }

        return response()->json(['message' => 'Signed out']);
    }

    /**
     * Logout
     *
     * Stops the token working.
     */
    public function logout()
    {
        $this->guard()->logout();

        return response()->json(['message' => 'Signed out']);
    }

    private function tokenResponse(User $user, string $token, int $status = 200, ?string $refreshToken = null)
    {
        $body = [
            'token' => $token,
            'token_type' => 'bearer',
            'expires_in' => $this->guard()->factory()->getTTL() * 60,
            'user' => $user->toProfile(),
        ];
        if ($refreshToken) {
            $body['refresh_token'] = $refreshToken;
            $body['refresh_expires_in'] = (int) config('auth.refresh_token_days', 14) * 86400;
        }

        return response()->json($body, $status);
    }

    private function fail(string $message, int $status = 422)
    {
        return response()->json(['message' => $message], $status);
    }

    private function guard(): JWTGuard
    {
        return auth('api');
    }
}
