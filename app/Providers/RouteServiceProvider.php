<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    public const HOME = '/';

    public function boot(): void
    {
        $this->routes(function (): void {
            // Canonical browser/API URL. The admin panel and storefront call /api/*.
            Route::prefix('api')
                ->middleware('api')
                ->group(base_path('routes/api.php'));

            // Backwards-compatible aliases retained for existing integrations and tests.
            Route::middleware('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }
}
