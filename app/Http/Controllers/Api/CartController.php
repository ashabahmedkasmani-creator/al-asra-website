<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\CartService;
use App\Services\PricingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class CartController extends Controller
{
    public function __construct(private CartService $carts, private PricingService $pricing) {}

    public function show(Request $request)
    {
        [$cart] = $this->carts->resolve($request);
        return $this->respond($cart, null);
    }

    public function addItem(Request $request)
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'min:1'],
            'variant_id' => ['nullable', 'integer', 'min:1'],
            'quantity' => ['required', 'integer', 'min:1', 'max:'.config('alasra.max_line_quantity')],
        ]);

        $product = Product::published()->find($data['product_id']);
        if (! $product || ! $product->availability->isPurchasable()) {
            throw ValidationException::withMessages(['product_id' => ['This product is not available.']]);
        }
        $variantId = $data['variant_id'] ?? null;
        if ($variantId) {
            abort_unless(ProductVariant::where('id', $variantId)->where('product_id', $product->id)->where('is_active', true)->exists(), 422, 'Invalid product option.');
        }

        [$cart, $rawToken] = $this->carts->resolve($request, create: true);
        $item = $cart->items()->where('product_id', $product->id)->where('variant_id', $variantId)->first();
        $newQty = ($item?->quantity ?? 0) + $data['quantity'];
        if ($newQty > config('alasra.max_line_quantity')) {
            throw ValidationException::withMessages(['quantity' => ['Maximum quantity per item exceeded.']]);
        }
        if ($item) {
            $item->update(['quantity' => $newQty]);
        } else {
            $cart->items()->create(['product_id' => $product->id, 'variant_id' => $variantId, 'quantity' => $newQty]);
        }
        return $this->respond($cart, $rawToken);
    }

    public function updateItem(Request $request, int $item)
    {
        $data = $request->validate(['quantity' => ['required', 'integer', 'min:1', 'max:'.config('alasra.max_line_quantity')]]);
        [$cart] = $this->carts->resolve($request);
        abort_unless($cart, 404);
        $cart->items()->whereKey($item)->firstOrFail()->update(['quantity' => $data['quantity']]); // scoped to own cart
        return $this->respond($cart, null);
    }

    public function removeItem(Request $request, int $item)
    {
        [$cart] = $this->carts->resolve($request);
        abort_unless($cart, 404);
        $cart->items()->whereKey($item)->firstOrFail()->delete();
        return $this->respond($cart, null);
    }

    public function clear(Request $request)
    {
        [$cart] = $this->carts->resolve($request);
        $cart?->items()->delete();
        return $this->respond($cart, null);
    }

    private function respond($cart, ?string $rawToken)
    {
        $items = $cart ? $cart->items()->with(['product.images', 'variant'])->orderBy('id')->get() : collect();

        // Drop items whose product was unpublished/deleted since they were added.
        foreach ($items as $i) {
            if (! $i->product || ! $i->product->is_published) {
                $i->delete();
            }
        }
        $items = $items->filter(fn ($i) => $i->product && $i->product->is_published)->values();

        $pricing = null;
        $issues = null;
        $lines = [];
        if ($items->isNotEmpty()) {
            try {
                $q = $this->pricing->quote($items->map(fn ($i) => ['product_id' => $i->product_id, 'variant_id' => $i->variant_id, 'quantity' => $i->quantity])->all());
                $pricing = collect($q)->except(['lines', 'coupon'])->all();
                $lines = $q['lines'];
            } catch (ValidationException $e) {
                $issues = $e->errors();
            }
        }

        $payload = ['data' => [
            'items' => $items->map(function ($i, $idx) use ($pricing, $lines) {
                $img = $i->product->images->sortByDesc('is_primary')->first();
                return [
                    'id' => $i->id, 'product_id' => $i->product_id, 'variant_id' => $i->variant_id,
                    'name' => $i->product->name, 'slug' => $i->product->slug, 'variant_name' => $i->variant?->name,
                    'quantity' => $i->quantity,
                    'unit_price' => $pricing ? (float) $lines[$idx]['unit_price'] : null,
                    'line_total' => $pricing ? (float) $lines[$idx]['line_total'] : null,
                    'image_url' => $img ? Storage::disk('public')->url($img->path) : null,
                ];
            }),
            'pricing' => $pricing ? array_map(fn ($v) => is_numeric($v) ? (float) $v : $v, $pricing) : null,
            'issues' => $issues,
        ]];
        if ($rawToken) {
            $payload['cart_token'] = $rawToken;
        }
        $response = response()->json($payload);
        if ($rawToken) {
            $response->headers->set('X-Cart-Token', $rawToken);
        }
        return $response;
    }
}
