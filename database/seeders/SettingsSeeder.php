<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('alasra.settings') as $key => $def) {
            Setting::firstOrCreate(['key' => $key], ['value' => $def['default']]); // never overwrites admin edits
        }
    }
}
