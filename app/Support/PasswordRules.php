<?php

namespace App\Support;

use Illuminate\Validation\Rules\Password;

class PasswordRules
{
    public static function default(): Password
    {
        $rule = Password::min(10)->mixedCase()->numbers();
        // Checks haveibeenpwned (k-anonymity). Only enforced in production so local dev/tests need no internet.
        return app()->isProduction() ? $rule->uncompromised() : $rule;
    }
}
