<?php
namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;

class StorefrontController extends Controller
{
    public function home()
    {
        return view('storefront.home', [
            'categories' => Category::withCount(['products' => fn($q) => $q->published()])->where('is_active', true)->whereNull('parent_id')->orderBy('sort_order')->get(),
            'products' => Product::published()->with(['images','category'])->where('show_on_home', true)->latest('published_at')->take(8)->get(),
        ]);
    }

    public function shop(Request $request)
    {
        $query = Product::published()->with(['images','category']);
        if ($request->filled('q')) $query->where('name', 'like', '%'.trim($request->q).'%');
        if ($request->filled('category')) $query->whereHas('category', fn($q) => $q->where('slug', $request->category));
        $products = $query->latest('published_at')->paginate(16)->withQueryString();
        $categories = Category::where('is_active', true)->orderBy('sort_order')->get();
        return view('storefront.shop', compact('products','categories'));
    }

    public function product(string $slug)
    {
        $product = Product::published()->with(['images','category','variants'])->where('slug',$slug)->firstOrFail();
        return view('storefront.product', compact('product'));
    }

    public function simple(string $page)
    {
        abort_unless(in_array($page, ['cart','checkout','track','about','contact','faq'], true), 404);
        return view('storefront.simple', compact('page'));
    }
}
