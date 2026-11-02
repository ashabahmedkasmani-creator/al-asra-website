<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function index(string $slug)
    {
        $product = Product::published()->where('slug', $slug)->firstOrFail();
        $page = $product->reviews()->where('is_approved', true)->with('user:id,name')->latest()->paginate(10);
        return response()->json([
            'data' => $page->getCollection()->map(fn ($r) => [
                'rating' => $r->rating, 'title' => $r->title, 'body' => $r->body,
                'author' => explode(' ', $r->user->name)[0], 'created_at' => $r->created_at->toIso8601String(),
            ]),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    /** Only customers with a delivered order for this product may review it. Reviews await moderation. */
    public function store(Request $request, string $slug)
    {
        $product = Product::published()->where('slug', $slug)->firstOrFail();
        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'title' => ['nullable', 'string', 'max:120'],
            'body' => ['nullable', 'string', 'max:2000'],
        ]);
        $data = array_map(fn ($v) => is_string($v) ? trim(strip_tags($v)) : $v, $data);

        $purchased = OrderItem::where('product_id', $product->id)
            ->whereHas('order', fn ($q) => $q->where('user_id', $request->user()->id)->where('status', 'delivered'))
            ->exists();
        abort_unless($purchased, 403, 'Only customers who received this product can review it.');

        $review = Review::firstOrNew(['product_id' => $product->id, 'user_id' => $request->user()->id]);
        $review->forceFill(['product_id' => $product->id, 'user_id' => $request->user()->id]);
        $review->fill($data);
        $review->is_approved = false;
        $review->save();

        return response()->json(['message' => 'Thanks! Your review will appear after moderation.'], 201);
    }
}
