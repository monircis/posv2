<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements JWTSubject
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    // Roles and permissions belong to the JWT "api" guard.
    protected $guard_name = 'api';

    protected $fillable = [
        'name', 'email', 'password', 'phone', 'branch_id', 'status', 'pin', 'last_login_at',
    ];

    protected $hidden = [
        'password', 'remember_token', 'pin', 'roles', 'permissions',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'pin' => 'hashed',
        ];
    }

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Other services (the POS backend) read the user from the token itself,
     * so they never need this database. Changed roles show up after the next refresh.
     */
    public function getJWTCustomClaims(): array
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
            'branch_id' => $this->branch_id,
            'role' => $this->getRoleNames()->first(),
            'permissions' => $this->getAllPermissions()->pluck('name')->values()->all(),
        ];
    }

    /** What the frontend gets for the signed-in user and in the user list. */
    public function toProfile(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'branch_id' => $this->branch_id,           // POS branch id, null = all branches
            'status' => $this->status,
            'role' => $this->getRoleNames()->first(),
            'permissions' => $this->getAllPermissions()->pluck('name')->values(),
            'last_login_at' => $this->last_login_at?->toIso8601String(),
        ];
    }
}
