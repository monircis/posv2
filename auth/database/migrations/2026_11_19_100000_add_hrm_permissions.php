<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * HRM permissions outside the screen x action grid:
 * - hrm.approve: the "HR manager" level in approvals (leave, overtime, corrections, deductions, payroll)
 * - hrm.payroll: salary structures, deductions, adjustments, loans, monthly payroll and payslips
 * - hrm.admin:   the "administrator" level in approvals (can decide any step), approval workflows and HRM settings
 * And a new "HR Manager" role: Home and HRM (all actions) plus approve and payroll.
 */
return new class extends Migration
{
    private const GRANTS = [
        'hrm.approve' => ['Owner', 'Branch Manager', 'HR Manager'],
        'hrm.payroll' => ['Owner', 'Branch Manager', 'HR Manager'],
        'hrm.admin' => ['Owner'],
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $hr = Role::findOrCreate('HR Manager', 'api');
        $grid = [];
        foreach (['home', 'hrm'] as $screen) {
            foreach (['view', 'create', 'edit', 'delete', 'all-locations'] as $action) {
                $grid[] = Permission::findOrCreate("$screen.$action", 'api');
            }
        }
        if ($hr->permissions()->count() === 0) {
            $hr->givePermissionTo($grid);
        }

        foreach (self::GRANTS as $name => $roles) {
            $permission = Permission::findOrCreate($name, 'api');
            foreach ($roles as $roleName) {
                Role::where('name', $roleName)->where('guard_name', 'api')->first()?->givePermissionTo($permission);
            }
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::whereIn('name', array_keys(self::GRANTS))->where('guard_name', 'api')->delete();
        Role::where('name', 'HR Manager')->where('guard_name', 'api')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
