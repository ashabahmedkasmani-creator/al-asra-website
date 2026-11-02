<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([PaymentMethodSeeder::class, SettingsSeeder::class, AdminSeeder::class]);

        // Sample catalogue only outside production. No customers or orders are ever seeded.
        if (! app()->isProduction()) {
            $this->call([CategorySeeder::class, ProductSeeder::class, FaqSeeder::class, BannerSeeder::class]);
        }
    }
}
