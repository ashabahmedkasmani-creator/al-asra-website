<?php

namespace App\Http\Controllers\Api;

use App\Enums\Availability;
use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\ProductResource;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CatalogController extends Controller
{
    public const EFFECTIVE_PRICE_SQL = 'CASE WHEN sale_price IS NOT NULL AND sale_price < price THEN sale_price ELSE price END';

    public function index(Request $request)
    {
        $v = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:140'],
            'min_price' => ['nullable', 'numeric', 'min:0'],
            'max_price' => ['nullable', 'numeric', 'min:0'],
            'availability' => ['nullable', Rule::enum(Availability::class)],
            'featured' => ['nullable', 'boolean'],
            'home' => ['nullable', 'boolean'],
            'sort' => ['nullable', 'in:newest,price_asc,price_desc,name'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:48'],
        ]);

        $q = Product::query()->published()->with(['category', 'images'])
            ->withAvg(['reviews as reviews_avg_rating' => fn ($r) => $r->where('is_approved', true)], 'rating')
            ->withCount(['reviews' => fn ($r) => $r->where('is_approved', true)]);

        if (! empty($v['search'])) {
            $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $v['search']).'%';
            $q->where(fn ($w) => $w->whereRaw("name LIKE ? ESCAPE '!'", [$like])
                ->orWhereRaw("short_description LIKE ? ESCAPE '!'", [$like])
                ->orWhereRaw("sku LIKE ? ESCAPE '!'", [$like]));
        }
        if (! empty($v['category'])) {
            $cat = Category::where('slug', $v['category'])->where('is_active', true)->first();
            $q->whereIn('category_id', $cat ? $cat->descendantIds() : [0]);
        }
        if (isset($v['min_price'])) $q->whereRaw(self::EFFECTIVE_PRICE_SQL.' >= ?', [$v['min_price']]);
        if (isset($v['max_price'])) $q->whereRaw(self::EFFECTIVE_PRICE_SQL.' <= ?', [$v['max_price']]);
        if (! empty($v['availability'])) $q->where('availability', $v['availability']);
        if ($request->boolean('featured')) $q->where('is_featured', true);
        if ($request->boolean('home')) $q->where('show_on_home', true);

        match ($v['sort'] ?? 'newest') {
            'price_asc' => $q->orderByRaw(self::EFFECTIVE_PRICE_SQL.' asc'),
            'price_desc' => $q->orderByRaw(self::EFFECTIVE_PRICE_SQL.' desc'),
            'name' => $q->orderBy('name'),
            default => $q->orderByDesc('published_at')->orderByDesc('id'),
        };

        return ProductResource::collection($q->paginate($v['per_page'] ?? 12)->withQueryString());
    }

    public function show(string $slug)
    {
        $product = Product::query()->published()->where('slug', $slug)
            ->with(['category', 'images', 'variants' => fn ($q) => $q->where('is_active', true)])
            ->withAvg(['reviews as reviews_avg_rating' => fn ($r) => $r->where('is_approved', true)], 'rating')
            ->withCount(['reviews' => fn ($r) => $r->where('is_approved', true)])
            ->firstOrFail();

        return new ProductResource($product);
    }

    public function categories()
    {
        $cats = Category::query()->whereNull('parent_id')->where('is_active', true)
            ->withCount(['products' => fn ($q) => $q->where('is_published', true)])
            ->with(['children' => fn ($q) => $q->where('is_active', true)->withCount(['products' => fn ($p) => $p->where('is_published', true)])])
            ->orderBy('sort_order')->get();

        return CategoryResource::collection($cats);
    }
}
