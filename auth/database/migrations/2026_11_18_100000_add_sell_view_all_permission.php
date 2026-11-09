<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * "sell.view-all": see everyone's sales in Sell. Without it a user only sees the sales they made.
 * Given to the roles that look after the shop: Owner, Manager, Accountant and Branch Manager.
 */
return new class extends Migration
{
    private const ROLES = ['Owner', 'Manager', 'Accountant', 'Branch Manager'];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $permission = Permission::findOrCreate('sell.view-all', 'api');

        foreach (self::ROLES as $name) {
            $role = Role::where('name', $name)->where('guard_name', 'api')->first();
            if ($role) {
                $role->givePermissionTo($permission);
            }
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::where('name', 'sell.view-all')->where('guard_name', 'api')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
