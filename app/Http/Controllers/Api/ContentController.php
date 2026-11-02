<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BannerResource;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\ProductResource;
use App\Models\Banner;
use App\Models\Category;
use App\Models\Faq;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Services\SettingsService;

class ContentController extends Controller
{
    public function settings(SettingsService $settings)
    {
        return response()->json(['data' => $settings->all(publicOnly: true)]);
    }

    public function faqs()
    {
        return response()->json(['data' => Faq::where('is_active', true)->orderBy('sort_order')->get(['id', 'question', 'answer'])]);
    }

    public function paymentMethods()
    {
        return response()->json(['data' => PaymentMethod::where('is_active', true)->orderBy('sort_order')->get(['code', 'name', 'instructions'])]);
    }

    public function banners()
    {
        $now = now();
        $banners = Banner::where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now))
            ->orderBy('sort_order')->get();
        return BannerResource::collection($banners);
    }

    /** One call for the home page. */
    public function home()
    {
        $base = fn () => Product::query()->published()->with(['category', 'images']);
        $now = now();
        return response()->json(['data' => [
            'banners' => BannerResource::collection(Banner::where('is_active', true)
                ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
                ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now))
                ->orderBy('sort_order')->get())->resolve(),
            'featured' => ProductResource::collection($base()->where('is_featured', true)->latest('id')->limit(8)->get())->resolve(),
            'home_products' => ProductResource::collection($base()->where('show_on_home', true)->latest('id')->limit(12)->get())->resolve(),
            'categories' => CategoryResource::collection(Category::whereNull('parent_id')->where('is_active', true)
                ->withCount(['products' => fn ($q) => $q->where('is_published', true)])->orderBy('sort_order')->get())->resolve(),
        ]]);
    }
}
