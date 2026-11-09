<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * A refresh token. The token itself is only given to the user once; the table keeps its SHA-256 hash.
 * It lasts REFRESH_TOKEN_DAYS (default 14) and works once.
 */
class RefreshToken extends Model
{
    protected $table = 'refresh_tokens';

    protected $fillable = ['user_id', 'token_hash', 'expires_at', 'revoked_at', 'user_agent', 'ip'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Makes a new refresh token for the user and returns the plain token (only now; it is not stored). */
    public static function issue(User $user, Request $request): string
    {
        $plain = Str::random(64);

        static::create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $plain),
            'expires_at' => now()->addDays((int) config('auth.refresh_token_days', 14)),
            'user_agent' => Str::limit((string) $request->userAgent(), 180, ''),
            'ip' => $request->ip(),
        ]);

        return $plain;
    }

    /** The stored token for a plain token, or null. */
    public static function findPlain(?string $plain): ?RefreshToken
    {
        if (! $plain) {
            return null;
        }

        return static::where('token_hash', hash('sha256', $plain))->first();
    }

    /** Signs the user out everywhere: every refresh token they have stops working. */
    public static function revokeAllFor(int $userId): void
    {
        static::where('user_id', $userId)->whereNull('revoked_at')->update(['revoked_at' => now()]);
    }

    public function isUsable(): bool
    {
        return ! $this->revoked_at && $this->expires_at->isFuture();
    }
}
