<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // Bearer-token lifetime (minutes). Expired tokens stop working server-side.
        config(['sanctum.expiration' => (int) config('alasra.sanctum_token_ttl_minutes', 10080)]);

        // Password-reset email links point at the Next.js frontend.
        ResetPassword::createUrlUsing(function ($user, string $token) {
            $base = rtrim((string) config('alasra.frontend_url', 'http://localhost:3000'), '/');
            return $base.'/reset-password?token='.$token.'&email='.urlencode($user->getEmailForPasswordReset());
        });

        $ip = fn (Request $r) => $r->ip();
        $email = fn (Request $r) => strtolower((string) $r->input('email'));

        RateLimiter::for('api', fn (Request $r) => Limit::perMinute(120)->by($r->user()?->id ?: $r->ip()));
        RateLimiter::for('login', fn (Request $r) => [
            Limit::perMinute(5)->by($email($r).'|'.$r->ip()),
            Limit::perMinute(20)->by($r->ip()),
        ]);
        RateLimiter::for('register', fn (Request $r) => [Limit::perMinute(5)->by($r->ip()), Limit::perHour(20)->by($r->ip())]);
        RateLimiter::for('password', fn (Request $r) => [Limit::perMinute(3)->by($email($r).'|'.$r->ip()), Limit::perHour(15)->by($r->ip())]);
        RateLimiter::for('track', fn (Request $r) => [
            Limit::perMinute(5)->by($r->ip()),
            Limit::perHour(30)->by($r->ip()),
            Limit::perHour(10)->by('ref:'.strtoupper((string) $r->input('reference'))),
        ]);
        RateLimiter::for('checkout', fn (Request $r) => [Limit::perMinute(10)->by($r->ip()), Limit::perHour(60)->by($r->ip())]);
        RateLimiter::for('quote', fn (Request $r) => Limit::perMinute(30)->by($r->ip()));
        RateLimiter::for('uploads', fn (Request $r) => Limit::perMinute(30)->by($r->user()?->id ?: $r->ip()));
        RateLimiter::for('sensitive', fn (Request $r) => Limit::perMinute(5)->by($r->user()?->id ?: $r->ip()));
        RateLimiter::for('admin', fn (Request $r) => Limit::perMinute(240)->by($r->user()?->id ?: $r->ip()));
    }
}
