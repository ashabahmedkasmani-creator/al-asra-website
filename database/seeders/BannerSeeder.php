<?php

namespace Database\Seeders;

use App\Models\Banner;
use Illuminate\Database\Seeder;

class BannerSeeder extends Seeder
{
    public function run(): void
    {
        Banner::firstOrCreate(['title' => 'Discover useful products, everyday essentials and carefully selected finds'], [
            'subtitle' => 'All in one place.', 'link_url' => '/shop', 'button_text' => 'SHOP NOW',
            'placement' => 'home_hero', 'sort_order' => 1, 'is_active' => true,
        ]);
    }
}
