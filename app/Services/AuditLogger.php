<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditLogger
{
    private const SECRET_KEYS = ['password', 'current_password', 'password_confirmation', 'token', 'remember_token', 'api_token', 'secret'];

    public static function record(Request $request, string $action, ?Model $model = null, ?array $old = null, ?array $new = null): void
    {
        AuditLog::forceCreate([
            'user_id' => $request->user()?->id,
            'action' => $action,
            'auditable_type' => $model ? class_basename($model) : null,
            'auditable_id' => $model?->getKey(),
            'old_values' => $old ? self::scrub($old) : null,
            'new_values' => $new ? self::scrub($new) : null,
            'ip_address' => $request->ip(),
        ]);
    }

    private static function scrub(array $values): array
    {
        foreach ($values as $k => $v) {
            if (in_array(strtolower((string) $k), self::SECRET_KEYS, true)) {
                unset($values[$k]);
            } elseif (is_array($v)) {
                $values[$k] = self::scrub($v);
            }
        }
        return $values;
    }
}
