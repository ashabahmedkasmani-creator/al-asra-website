<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('alasra.admin.email', 'admin@alasra.local');
        if (app()->isProduction() && (! config('alasra.admin.email') || ! env('ADMIN_PASSWORD') || config('alasra.admin.email') === 'admin@alasra.local' || env('ADMIN_PASSWORD') === 'ChangeMe!AlAsra2026' || strlen((string) env('ADMIN_PASSWORD')) < 16)) {
            $this->command?->error('Set a unique ADMIN_EMAIL and a private ADMIN_PASSWORD of at least 16 characters (not the local demo credentials) before seeding production.');
            return;
        }
        $email = strtolower($email);
        if (User::where('email', $email)->exists()) {
            $this->command?->info('Administrator already exists; left unchanged.');
            return;
        }

        $password = env('ADMIN_PASSWORD') ?: (app()->isProduction() ? Str::password(32) : 'ChangeMe!AlAsra2026');
        $user = new User(['name' => config('alasra.admin.name', 'Store Admin'), 'email' => $email, 'password' => $password]);
        $user->role = Role::Admin;
        $user->is_active = true;
        $user->email_verified_at = now();
        $user->save();

        $this->command?->info("Administrator created: {$email}");
        if (! env('ADMIN_PASSWORD') && ! app()->isProduction()) {
            $this->command?->warn('Local default password: ChangeMe!AlAsra2026 — change it immediately after first login.');
        }
    }
}
