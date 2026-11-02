<?php

/*
| Laravel 10 application bootstrap (the Laravel 11+ fluent `Application::configure()` version is gone).
| Middleware, aliases, exception rendering and routing now live in:
|   app/Http/Kernel.php, app/Exceptions/Handler.php, app/Providers/RouteServiceProvider.php,
|   app/Http/Middleware/{Authenticate,TrustProxies}.php
*/

$app = new Illuminate\Foundation\Application(
    $_ENV['APP_BASE_PATH'] ?? dirname(__DIR__)
);

$app->singleton(
    Illuminate\Contracts\Http\Kernel::class,
    App\Http\Kernel::class
);

$app->singleton(
    Illuminate\Contracts\Console\Kernel::class,
    App\Console\Kernel::class
);

$app->singleton(
    Illuminate\Contracts\Debug\ExceptionHandler::class,
    App\Exceptions\Handler::class
);

return $app;
