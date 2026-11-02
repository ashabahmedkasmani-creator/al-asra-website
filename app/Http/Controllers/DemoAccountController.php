<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\User;
use App\Support\PasswordRules;
use App\Support\Phone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DemoAccountController extends Controller
{
    public function loginPage()
    {
        return view('demo.login');
    }

    public function registerPage()
    {
        return view('demo.register');
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $authenticated = Auth::attempt([
            'email' => strtolower($validated['email']),
            'password' => $validated['password'],
            'is_active' => true,
        ]);

        if (! $authenticated) {
            return back()->withErrors(['email' => 'The supplied credentials are invalid.'])->onlyInput('email');
        }

        if (Auth::user()->role !== Role::Customer) {
            Auth::logout();

            return back()->withErrors(['email' => 'Use the admin panel to sign in.']);
        }

        $request->session()->regenerate();

        return redirect()->intended('/my-account');
    }

    public function register(Request $request)
    {
        $request->merge([
            'email' => is_string($request->email) ? strtolower(trim($request->email)) : $request->email,
            'phone' => Phone::normalize($request->phone),
        ]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'phone' => ['nullable', 'regex:/^03\d{9}$/'],
            'password' => ['required', 'confirmed', PasswordRules::default()],
        ]);

        $user = new User([
            'name' => strip_tags($validated['name']),
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'password' => $validated['password'],
        ]);
        $user->role = Role::Customer;
        $user->is_active = true;
        $user->save();

        Auth::login($user);
        $request->session()->regenerate();

        return redirect('/my-account');
    }

    public function account(Request $request)
    {
        $user = $request->user();
        abort_unless($user->role === Role::Customer, 403);

        $orders = $user->orders()->with('items')->latest()->paginate(10);

        return view('demo.account', ['u' => $user, 'orders' => $orders]);
    }

    public function update(Request $request)
    {
        $user = $request->user();
        abort_unless($user->role === Role::Customer, 403);
        $request->merge(['phone' => Phone::normalize($request->phone)]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'phone' => ['nullable', 'regex:/^03\d{9}$/'],
        ]);

        $user->update([
            'name' => strip_tags($validated['name']),
            'phone' => $validated['phone'] ?? null,
        ]);

        return back()->with('success', 'Your profile has been updated.');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
