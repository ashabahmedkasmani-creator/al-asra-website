<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Services\SettingsService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SettingController extends Controller
{
    public function index(SettingsService $settings)
    {
        return response()->json(['data' => $settings->all()]);
    }

    public function update(Request $request, SettingsService $settings)
    {
        $defs = config('alasra.settings');
        $input = $request->input('settings');
        if (! is_array($input) || ! $input) {
            throw ValidationException::withMessages(['settings' => ['Provide a "settings" object.']]);
        }
        $unknown = array_diff(array_keys($input), array_keys($defs)); // whitelist: unknown keys rejected
        if ($unknown) {
            throw ValidationException::withMessages(['settings' => ['Unknown setting(s): '.implode(', ', array_map('strip_tags', $unknown))]]);
        }

        $rules = [];
        foreach (array_keys($input) as $key) {
            $rules["settings.$key"] = $defs[$key]['rules'];
        }
        $request->validate($rules);

        $old = $settings->all();
        foreach ($input as $key => $value) {
            $settings->set($key, $value === null ? null : strip_tags((string) $value));
        }
        $new = $settings->all();
        AuditLogger::record($request, 'settings.updated', null,
            array_intersect_key($old, $input), array_intersect_key($new, $input));
        return response()->json(['data' => $new]);
    }
}
