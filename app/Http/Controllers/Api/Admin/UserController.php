<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\PasswordRules;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Staff/admin management. Requires users.manage (admin only) and re-entering the admin's own password. */
class UserController extends Controller
{
    public function index()
    {
        return UserResource::collection(User::whereIn('role', [Role::Staff->value, Role::Admin->value])->orderBy('name')->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'email' => ['required', 'email:rfc', 'max:150', 'unique:users,email'],
            'password' => ['required', PasswordRules::default()],
            'role' => ['required', Rule::in(['staff', 'admin'])],
            'current_password' => ['required', 'current_password'],
        ]);
        $user = new User(['name' => strip_tags($data['name']), 'email' => strtolower($data['email']), 'password' => $data['password']]);
        $user->role = Role::from($data['role']);
        $user->is_active = true;
        $user->save();
        AuditLogger::record($request, 'user.created', $user, null, ['email' => $user->email, 'role' => $data['role']]);
        return (new UserResource($user))->response()->setStatusCode(201);
    }

    public function updateRole(Request $request, User $user)
    {
        $data = $request->validate([
            'role' => ['required', Rule::in(['customer', 'staff', 'admin'])],
            'current_password' => ['required', 'current_password'],
        ]);
        abort_if($user->id === $request->user()->id, 422, 'You cannot change your own role.');
        $this->guardLastAdmin($user, $data['role']);

        $old = $user->role->value;
        $user->role = Role::from($data['role']);
        $user->save();
        $user->tokens()->delete(); // force re-login under the new role
        AuditLogger::record($request, 'user.role_changed', $user, ['role' => $old], ['role' => $data['role']]);
        return new UserResource($user);
    }

    public function setActive(Request $request, User $user)
    {
        $data = $request->validate(['is_active' => ['required', 'boolean'], 'current_password' => ['required', 'current_password']]);
        abort_if($user->id === $request->user()->id, 422, 'You cannot deactivate yourself.');
        if (! $data['is_active']) {
            $this->guardLastAdmin($user, 'customer');
            $user->tokens()->delete();
        }
        $user->is_active = $data['is_active'];
        $user->save();
        AuditLogger::record($request, 'user.active_changed', $user, null, ['is_active' => $data['is_active']]);
        return new UserResource($user);
    }

    private function guardLastAdmin(User $user, string $newRole): void
    {
        if ($user->role === Role::Admin && $newRole !== 'admin') {
            abort_if(User::where('role', Role::Admin->value)->where('is_active', true)->count() <= 1, 422, 'At least one active administrator is required.');
        }
    }
}
