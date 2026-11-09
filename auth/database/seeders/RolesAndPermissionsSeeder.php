<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * One permission per screen and action, e.g. "products.view", "sell.create".
 * The roles follow the presets on the User Management > Permissions screen.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    // Screen keys, the same names the Vue app uses for its pages.
    public const SCREENS = [
        'home', 'users', 'contacts', 'products', 'mfg', 'purchases', 'sell', 'transfers', 'adjust',
        'expenses', 'accounts', 'accounting', 'reports', 'bookings', 'kitchen', 'orders', 'settings', 'hrm',
    ];

    public const ACTIONS = ['view', 'create', 'edit', 'delete', 'all-locations'];

    // Permissions outside the screen x action grid: name => roles that get it.
    // "<screen>.view-all" = see everyone's records on that screen (without it, only the ones you made).
    public const EXTRAS = [
        'sell.view-all' => ['Owner', 'Manager', 'Accountant'],
        'purchases.view-all' => ['Owner', 'Manager', 'Accountant'],
        'expenses.view-all' => ['Owner', 'Manager', 'Accountant'],
        'mfg.view-all' => ['Owner', 'Manager', 'Accountant'],
        'transfers.view-all' => ['Owner', 'Manager', 'Accountant', 'Stock keeper'],
        'adjust.view-all' => ['Owner', 'Manager', 'Accountant', 'Stock keeper'],
        'bookings.view-all' => ['Owner', 'Manager', 'Accountant', 'Cashier'],
        // HRM: approve = the HR manager approval level, payroll = salary and payroll, admin = the administrator level and HRM settings
        'hrm.approve' => ['Owner', 'HR Manager'],
        'hrm.payroll' => ['Owner', 'HR Manager'],
        'hrm.admin' => ['Owner'],
    ];

    // role => [screens (null = all), [view, create, edit, delete, all-locations]]
    private const ROLES = [
        'Owner' => [null, [1, 1, 1, 1, 1]],
        'Manager' => [['home', 'contacts', 'products', 'mfg', 'purchases', 'sell', 'transfers', 'adjust', 'expenses', 'accounts', 'reports', 'bookings', 'kitchen', 'orders'], [1, 1, 1, 0, 0]],
        'Cashier' => [['home', 'sell', 'bookings', 'orders'], [1, 1, 0, 0, 0]],
        'Accountant' => [['home', 'contacts', 'purchases', 'sell', 'expenses', 'accounts', 'accounting', 'reports'], [1, 1, 1, 0, 1]],
        'Kitchen' => [['kitchen', 'orders'], [1, 0, 1, 0, 0]],
        'Stock keeper' => [['home', 'products', 'purchases', 'transfers', 'adjust'], [1, 1, 1, 0, 0]],
        'HR Manager' => [['home', 'hrm'], [1, 1, 1, 1, 1]],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::SCREENS as $screen) {
            foreach (self::ACTIONS as $action) {
                Permission::findOrCreate("$screen.$action", 'api');
            }
        }
        foreach (array_keys(self::EXTRAS) as $extra) {
            Permission::findOrCreate($extra, 'api');
        }

        foreach (self::ROLES as $name => [$screens, $grants]) {
            $permissions = [];
            foreach ($screens ?? self::SCREENS as $screen) {
                foreach (self::ACTIONS as $i => $action) {
                    if ($grants[$i]) {
                        $permissions[] = "$screen.$action";
                    }
                }
            }
            foreach (self::EXTRAS as $extra => $roles) {
                if (in_array($name, $roles, true)) {
                    $permissions[] = $extra;
                }
            }
            Role::findOrCreate($name, 'api')->syncPermissions($permissions);
        }
    }
}
