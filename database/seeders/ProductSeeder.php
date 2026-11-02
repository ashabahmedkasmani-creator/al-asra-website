<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

/** The three products currently on the live site. Images are uploaded through the admin API. */
class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['sku' => 'ASR-BLEND-001', 'name' => 'Portable Juice Blender', 'cat' => 'kitchen', 'price' => 2499,
                'desc' => 'Portable blender for fresh juices and smoothies, wherever you are.'],
            ['sku' => 'ASR-TUMB-001', 'name' => 'Ice Cream Tumbler with Straw', 'cat' => 'drinkware', 'price' => 1799,
                'desc' => 'Fun tumbler with a straw for cold drinks and dessert-style treats.'],
            ['sku' => 'ASR-FROTH-001', 'name' => 'Electric Milk Frother', 'cat' => 'kitchen', 'price' => 999,
                'desc' => 'Handheld electric frother for creamy coffee, matcha and milk drinks in seconds.'],
        ];
        foreach ($items as $i) {
            if (Product::where('sku', $i['sku'])->exists()) {
                continue;
            }
            $p = Product::create([
                'category_id' => Category::where('slug', $i['cat'])->value('id'),
                'name' => $i['name'], 'slug' => Product::uniqueSlug($i['name']), 'sku' => $i['sku'],
                'short_description' => $i['desc'], 'description' => $i['desc'],
                'price' => $i['price'], 'availability' => 'available', 'is_featured' => true, 'show_on_home' => true,
            ]);
            $p->publish();
        }
    }
}
