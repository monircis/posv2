<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RefreshToken;
use App\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** User Management > Users. Staff without a password are "invited". */
#[Group('Users', 'User Management. Needs the users.* permissions.', weight: 2)]
class UserController extends Controller
{
    /**
     * List users
     *
     * Needs `users.view`.
     */
    public function index()
    {
        return User::with('roles')->orderBy('id')->get()->map->toProfile();
    }

    /**
     * Add user
     *
     * Needs `users.create`. Without a password the user is `invited`.
     * `branch_id` is a POS branch id; empty means all branches.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|email|max:190|unique:users,email',
            'phone' => 'nullable|string|max:40',
            'branch_id' => 'nullable|integer|min:1', // POS branch id, empty = all branches
            'role' => 'required|exists:roles,name',
            'pin' => 'nullable|digits:4',
            'password' => 'nullable|string|min:6',
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => strtolower($data['email']),
            'phone' => $data['phone'] ?? null,
            'branch_id' => $data['branch_id'] ?? null,
            'pin' => $data['pin'] ?? null,
            'password' => $data['password'] ?? null,
            'status' => empty($data['password']) ? 'invited' : 'active',
        ]);
        $user->assignRole($data['role']);

        return response()->json($user->toProfile(), 201);
    }

    /**
     * Show user
     *
     * Needs `users.view`.
     */
    public function show(User $user)
    {
        return $user->toProfile();
    }

    /**
     * Update user
     *
     * Needs `users.edit`. Send only the fields to change. An empty password keeps the old one.
     * The Owner cannot be demoted or deactivated (422).
     */
    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:120',
            'email' => ['sometimes', 'required', 'email', Rule::unique('users')->ignore($user->id)],
            'phone' => 'sometimes|nullable|string|max:40',
            'branch_id' => 'sometimes|nullable|integer|min:1',
            'status' => 'sometimes|required|in:active,invited,off',
            'role' => 'sometimes|required|exists:roles,name',
            'pin' => 'sometimes|nullable|digits:4',
            'password' => 'sometimes|nullable|string|min:6',
        ]);

        if ($user->hasRole('Owner') && (($data['role'] ?? 'Owner') !== 'Owner' || ($data['status'] ?? 'active') !== 'active')) {
            return response()->json(['message' => 'The Owner cannot be demoted or deactivated.'], 422);
        }

        $user->update(collect($data)->except('role')->filter(fn ($v, $k) => $k !== 'password' || $v)->all());
        // Switched off, or a new password: their refresh tokens stop working, so they must sign in again.
        if ((isset($data['status']) && $data['status'] !== 'active') || ! empty($data['password'])) {
            RefreshToken::revokeAllFor($user->id);
        }
        if (isset($data['role'])) {
            $user->syncRoles([$data['role']]);
        }

        return $user->fresh()->toProfile();
    }

    /**
     * Delete user
     *
     * Needs `users.delete`. You cannot delete yourself or the Owner (422).
     */
    public function destroy(Request $request, User $user)
    {
        if ($user->id === $request->user()->id || $user->hasRole('Owner')) {
            return response()->json(['message' => 'You cannot delete yourself or the Owner.'], 422);
        }
        $user->delete();

        return response()->noContent();
    }
}
