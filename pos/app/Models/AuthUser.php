<?php

namespace App\Models;

/**
 * The signed-in user. Users are saved in the auth service, not in the POS database,
 * so this is filled from the auth service's /api/auth/me answer by the VerifyToken middleware.
 */
class AuthUser
{
    public $id;

    public $name;

    public $email;

    public $branch_id; // null = all branches

    public $role;

    public $permissions = [];

    /** Example: $user->hasPermission('products.view') */
    public function hasPermission($permission)
    {
        return in_array($permission, $this->permissions);
    }
}
