<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Kitchen' => 1, 'Drinkware' => 2] as $name => $order) {
            Category::firstOrCreate(['slug' => \Illuminate\Support\Str::slug($name)], ['name' => $name, 'sort_order' => $order, 'is_active' => true]);
        }
    }
}
