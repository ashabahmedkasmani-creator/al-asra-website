<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /** Never redirect API clients to a (non-existent) login page. */
    protected function redirectTo(Request $request): ?string
    {
        return null;
    }
}
