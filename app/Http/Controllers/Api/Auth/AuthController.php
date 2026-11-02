<?php

namespace App\Http\Controllers\Api\Auth;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Support\PasswordRules;
use App\Support\Phone;
use App\Services\CartService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    public function register(RegisterRequest $request, CartService $carts)
    {
        $user = new User($request->safe()->only(['name', 'email', 'phone', 'password']));
        $user->role = Role::Customer;     // role is never taken from input
        $user->is_active = true;
        $user->save();
        $carts->mergeGuestCart($request, $user);

        return response()->json(['data' => [
            'user' => new UserResource($user),
            'token' => $user->createToken('customer')->plainTextToken,
            'token_type' => 'Bearer',
        ]], 201);
    }

    public function login(Request $request, CartService $carts)
    {
        $creds = $request->validate(['email' => ['required', 'email', 'max:150'], 'password' => ['required', 'string', 'max:200']]);
        $user = User::where('email', strtolower($creds['email']))->first();

        // Always run a hash comparison so response time does not reveal whether the email exists.
        $hash = $user?->password ?? Hash::make(Str::random(16));
        $ok = Hash::check($creds['password'], $hash) && $user && $user->is_active;

        if (! $ok) {
            return response()->json(['message' => 'These credentials do not match our records.'], 401);
        }
        $carts->mergeGuestCart($request, $user);

        return response()->json(['data' => [
            'user' => new UserResource($user),
            'token' => $user->createToken($user->isStaffOrAdmin() ? 'staff' : 'customer')->plainTextToken,
            'token_type' => 'Bearer',
        ]]);
    }

    public function logout(Request $request)
    {
        $token = $request->user()->currentAccessToken();
        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }
        return response()->json(['message' => 'Logged out.']);
    }

    public function logoutAll(Request $request)
    {
        $request->user()->tokens()->delete();
        return response()->json(['message' => 'All sessions ended.']);
    }

    public function me(Request $request)
    {
        return new UserResource($request->user());
    }

    public function updateProfile(Request $request)
    {
        $request->merge([
            'name' => is_string($request->name) ? trim(strip_tags($request->name)) : $request->name,
            'phone' => $request->phone ? Phone::normalize($request->phone) : null,
        ]);
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'min:2', 'max:100'],
            'phone' => ['nullable', 'regex:/^03\d{9}$/'],
        ]);
        $request->user()->update($data);
        return new UserResource($request->user()->fresh());
    }

    public function changePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'different:current_password', PasswordRules::default()],
        ]);
        $user = $request->user();
        $user->update(['password' => $data['password']]);
        $current = $user->currentAccessToken();
        $user->tokens()->when($current instanceof PersonalAccessToken, fn ($q) => $q->where('id', '!=', $current->id))->delete();
        return response()->json(['message' => 'Password updated. Other sessions were signed out.']);
    }

    /** Always answers the same way, whether or not the email exists. */
    public function forgotPassword(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:150']]);
        try {
            Password::sendResetLink(['email' => strtolower($data['email'])]);
        } catch (\Throwable $e) {
            Log::warning('Password reset mail failed: '.get_class($e)); // no email address / secrets logged
        }
        return response()->json(['message' => 'If an account exists for that email, a reset link has been sent.']);
    }

    public function resetPassword(Request $request)
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:200'],
            'email' => ['required', 'email', 'max:150'],
            'password' => ['required', 'confirmed', PasswordRules::default()],
        ]);

        $status = Password::reset(
            ['email' => strtolower($data['email']), 'token' => $data['token'], 'password' => $data['password'], 'password_confirmation' => $request->password_confirmation],
            function (User $user, string $password) {
                $user->forceFill(['password' => $password])->save();
                $user->tokens()->delete();
                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json(['message' => 'This password reset link is invalid or has expired.'], 422);
        }
        return response()->json(['message' => 'Password has been reset. Please sign in.']);
    }
}
