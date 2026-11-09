<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * "<screen>.view-all": see everyone's records on that screen. Without it a user only sees what they made.
 * (sell.view-all was added before.) permission => roles that get it.
 */
return new class extends Migration
{
    private const GRANTS = [
        'purchases.view-all' => ['Owner', 'Manager', 'Accountant', 'Branch Manager'],
        'expenses.view-all' => ['Owner', 'Manager', 'Accountant', 'Branch Manager'],
        'mfg.view-all' => ['Owner', 'Manager', 'Accountant', 'Branch Manager'],
        'transfers.view-all' => ['Owner', 'Manager', 'Accountant', 'Branch Manager', 'Stock keeper'],
        'adjust.view-all' => ['Owner', 'Manager', 'Accountant', 'Branch Manager', 'Stock keeper'],
        'bookings.view-all' => ['Owner', 'Manager', 'Accountant', 'Branch Manager', 'Cashier'],
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (self::GRANTS as $name => $roles) {
            $permission = Permission::findOrCreate($name, 'api');
            foreach ($roles as $roleName) {
                $role = Role::where('name', $roleName)->where('guard_name', 'api')->first();
                if ($role) {
                    $role->givePermissionTo($permission);
                }
            }
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::whereIn('name', array_keys(self::GRANTS))->where('guard_name', 'api')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
