<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CheckoutRequest;
use App\Http\Resources\OrderResource;
use App\Models\PaymentMethod;
use App\Services\CartService;
use App\Services\OrderService;
use App\Services\PricingService;
use App\Support\Phone;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CheckoutController extends Controller
{
    public function __construct(
        private CartService $carts,
        private PricingService $pricing,
        private OrderService $orders,
    ) {}

    /** Price a bag without creating anything. Client prices are never read. */
    public function quote(Request $request)
    {
        $data = $request->validate([
            'items' => ['nullable', 'array', 'min:1', 'max:'.config('alasra.max_cart_lines')],
            'items.*.product_id' => ['required_with:items', 'integer', 'min:1'],
            'items.*.variant_id' => ['nullable', 'integer', 'min:1'],
            'items.*.quantity' => ['required_with:items', 'integer', 'min:1', 'max:'.config('alasra.max_line_quantity')],
            'coupon_code' => ['nullable', 'string', 'max:40'],
            'phone' => ['nullable', 'string', 'max:20'],
            'city' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:10'],
        ]);

        $q = $this->pricing->quote($this->lines($request, $data['items'] ?? null), $data['coupon_code'] ?? null, Phone::normalize($data['phone'] ?? null), city: $data['city'] ?? null, province: $data['province'] ?? null, postalCode: $data['postal_code'] ?? null);

        return response()->json(['data' => [
            'items' => collect($q['lines'])->map(fn ($l) => [
                'product_id' => $l['product']->id, 'variant_id' => $l['variant']?->id, 'name' => $l['name'],
                'variant_name' => $l['variant_name'], 'quantity' => $l['quantity'],
                'unit_price' => (float) $l['unit_price'], 'line_total' => (float) $l['line_total'],
                'availability' => $l['availability']->value, 'availability_label' => $l['availability']->label(),
            ]),
            'subtotal' => (float) $q['subtotal'], 'discount_total' => (float) $q['discount_total'],
            'shipping_fee' => (float) $q['shipping_fee'], 'grand_total' => (float) $q['grand_total'],
            'free_shipping_threshold' => (float) $q['free_shipping_threshold'],
            'amount_to_free_shipping' => (float) $q['amount_to_free_shipping'],
            'coupon_code' => $q['coupon']?->code,
            'requires_supplier_confirmation' => $q['requires_supplier_confirmation'],
            'currency' => $q['currency'], 'shipping_zone' => $q['shipping_zone'],
        ]]);
    }

    public function store(CheckoutRequest $request)
    {
        $user = $request->user('sanctum');
        $data = $request->validated();

        if (! empty($data['address_id'])) {
            $address = $user?->addresses()->find($data['address_id']); // only the caller's own address
            if (! $address) {
                throw ValidationException::withMessages(['address_id' => ['Address not found.']]);
            }
            $data['address_line'] = $address->address_line;
            $data['city'] = $address->city;
            $data['province'] = $address->province;
            $data['postal_code'] = $address->postal_code;
        }

        $key = $request->header('Idempotency-Key');
        if ($key !== null && ! preg_match('/^[A-Za-z0-9_-]{16,80}$/', $key)) {
            throw ValidationException::withMessages(['idempotency_key' => ['Idempotency-Key must be 16-80 URL-safe characters.']]);
        }

        [$cart] = $this->carts->resolve($request);
        $usedCart = empty($data['items']);
        $data['items'] = $this->lines($request, $data['items'] ?? null);

        [$order, $created] = $this->orders->place($data, $user, $key);

        if ($created && $usedCart && $cart) {
            $cart->items()->delete();
        }

        $method = PaymentMethod::where('code', $order->payment_method)->first();
        $order->loadMissing(['items', 'histories', 'shipment']);

        $response = (new OrderResource($order))->additional(['meta' => [
            'payment_instructions' => $method?->instructions,
            'track_with' => 'order reference + phone number',
        ]])->response()->setStatusCode($created ? 201 : 200);
        if (! $created) {
            $response->headers->set('Idempotent-Replayed', 'true');
        }
        return $response;
    }

    private function lines(Request $request, ?array $items): array
    {
        if ($items) {
            return $items;
        }
        [$cart] = $this->carts->resolve($request);
        $lines = $cart ? $cart->items()->orderBy('id')->get(['product_id', 'variant_id', 'quantity'])->toArray() : [];
        if (! $lines) {
            throw ValidationException::withMessages(['items' => ['Your bag is empty.']]);
        }
        return $lines;
    }
}
