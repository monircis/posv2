<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/** User Management > Roles & permissions (Spatie). */
#[Group('Roles', 'Roles and their permissions (Spatie).', weight: 3)]
class RoleController extends Controller
{
    /** Built-in roles cannot be deleted. */
    private const BUILT_IN = ['Owner', 'Manager', 'Cashier', 'Kitchen'];

    /**
     * List roles
     *
     * Every role with its permissions and number of users. Needs `users.view`.
     */
    public function index()
    {
        return Role::with('permissions')->withCount('users')->orderBy('id')->get()->map(fn (Role $role) => $this->row($role));
    }

    /**
     * List permissions
     *
     * Every permission name, e.g. "products.view". Needs `users.view`.
     */
    public function permissions()
    {
        return Permission::orderBy('id')->pluck('name');
    }

    /**
     * Add role
     *
     * Needs `users.create`.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:60|unique:roles,name',
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,name',
        ]);
        $role = Role::create(['name' => $data['name'], 'guard_name' => 'api']);
        $role->syncPermissions($data['permissions'] ?? []);

        return response()->json($this->row($role->loadCount('users')), 201);
    }

    /**
     * Update role permissions
     *
     * Replaces the role's permissions. Needs `users.edit`. The Owner role cannot be changed (422).
     *
     * @param  string  $name  Role name, e.g. "Cashier".
     */
    public function update(Request $request, string $name)
    {
        $role = Role::findByName($name, 'api');
        if ($role->name === 'Owner') {
            return response()->json(['message' => 'The Owner role always has every permission.'], 422);
        }
        $data = $request->validate([
            'permissions' => 'present|array',
            'permissions.*' => 'exists:permissions,name',
        ]);
        $role->syncPermissions($data['permissions']);

        return $this->row($role->loadCount('users'));
    }

    /**
     * Delete role
     *
     * Needs `users.delete`. Built-in roles and roles that still have users cannot be deleted (422).
     *
     * @param  string  $name  Role name.
     */
    public function destroy(string $name)
    {
        $role = Role::findByName($name, 'api');
        if (in_array($role->name, self::BUILT_IN, true) || $role->users()->exists()) {
            return response()->json(['message' => 'Built-in roles and roles that still have users cannot be deleted.'], 422);
        }
        $role->delete();

        return response()->noContent();
    }

    private function row(Role $role): array
    {
        return [
            'name' => $role->name,
            'built_in' => in_array($role->name, self::BUILT_IN, true),
            'users' => $role->users_count ?? 0,
            'permissions' => $role->permissions->pluck('name')->values(),
        ];
    }
}
