<?php

namespace App\Services;

use App\Models\Setting;

class SettingsService
{
    public function get(string $key): ?string
    {
        $row = Setting::where('key', $key)->first();
        return $row ? $row->value : (config("alasra.settings.$key.default"));
    }

    public function money(string $key): int
    {
        return (int) round(((float) $this->get($key)) * 100); // minor units
    }

    public function set(string $key, ?string $value): void
    {
        abort_unless(array_key_exists($key, config('alasra.settings')), 422, 'Unknown setting.');
        Setting::updateOrCreate(['key' => $key], ['value' => $value]);
    }

    /** @return array<string,string|null> */
    public function all(bool $publicOnly = false): array
    {
        $stored = Setting::pluck('value', 'key')->all();
        $out = [];
        foreach (config('alasra.settings') as $key => $def) {
            if ($publicOnly && ! $def['public']) {
                continue;
            }
            $out[$key] = array_key_exists($key, $stored) ? $stored[$key] : $def['default'];
        }
        return $out;
    }
}
