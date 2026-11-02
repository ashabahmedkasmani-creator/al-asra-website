<?php

namespace App\Services;

use App\Enums\Availability;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingZone;
use Illuminate\Validation\ValidationException;

/**
 * Single source of truth for money. Every price comes from the database; client-supplied
 * prices are never read. Arithmetic is done in integer paisa to avoid float drift.
 *
 * Shipping rule: the free-shipping threshold is compared against the subtotal AFTER discounts.
 */
class PricingService
{
    public function __construct(private SettingsService $settings) {}

    /**
     * @param  array<int,array{product_id:int,variant_id?:int|null,quantity:int}>  $lines
     */
    public function quote(array $lines, ?string $couponCode = null, ?string $phone = null, bool $lock = false, ?string $city = null, ?string $province = null, ?string $postalCode = null): array
    {
        $merged = [];
        foreach ($lines as $l) {
            $pid = (int) ($l['product_id'] ?? 0);
            $vid = isset($l['variant_id']) ? (int) $l['variant_id'] : 0;
            $qty = (int) ($l['quantity'] ?? 0);
            if ($pid < 1 || $qty < 1) {
                throw ValidationException::withMessages(['items' => ['Invalid item in your bag.']]);
            }
            $k = "$pid:$vid";
            $merged[$k] = [$pid, $vid, ($merged[$k][2] ?? 0) + $qty];
        }
        if (! $merged) {
            throw ValidationException::withMessages(['items' => ['Your bag is empty.']]);
        }
        if (count($merged) > config('alasra.max_cart_lines')) {
            throw ValidationException::withMessages(['items' => ['Too many different items in one order.']]);
        }

        $pids = array_values(array_unique(array_column($merged, 0)));
        $pq = Product::query()->published()->whereIn('id', $pids);
        $lock && $pq->lockForUpdate();
        $products = $pq->get()->keyBy('id');
        $products->load('images');

        $vids = array_values(array_filter(array_column($merged, 1)));
        $variants = collect();
        if ($vids) {
            $vq = ProductVariant::query()->where('is_active', true)->whereIn('id', $vids);
            $lock && $vq->lockForUpdate();
            $variants = $vq->get()->keyBy('id');
        }
        $withVariants = ProductVariant::query()->where('is_active', true)->whereIn('product_id', $pids)
            ->pluck('product_id')->unique()->all();

        $out = [];
        $subtotal = 0;
        $needsSupplier = false;
        $max = (int) config('alasra.max_line_quantity');

        foreach ($merged as [$pid, $vid, $qty]) {
            $product = $products->get($pid);
            if (! $product || ! $product->availability->isPurchasable()) {
                throw ValidationException::withMessages(['items' => ['One of the items in your bag is no longer available.']]);
            }
            $variant = null;
            if (in_array($pid, $withVariants, true)) {
                $variant = $variants->get($vid);
                if (! $variant || $variant->product_id !== $pid) {
                    throw ValidationException::withMessages(['items' => ["Please choose an option for {$product->name}."]]);
                }
            } elseif ($vid) {
                throw ValidationException::withMessages(['items' => ['Invalid product option.']]);
            }
            $sellable = $variant ?? $product;
            if (! $sellable->availability->isPurchasable()) {
                throw ValidationException::withMessages(['items' => ["{$product->name} is currently unavailable."]]);
            }
            if ($qty > $max) {
                throw ValidationException::withMessages(['items' => ["You can order at most $max of {$product->name}."]]);
            }
            if ($sellable->track_inventory && (int) $sellable->stock_quantity < $qty) {
                throw ValidationException::withMessages(['items' => ["Not enough stock for {$product->name}."]]);
            }

            $unit = $this->cents($sellable->effective_price);
            $lineTotal = $unit * $qty;
            $subtotal += $lineTotal;
            if ($sellable->availability === Availability::SupplierConfirmation || $product->availability === Availability::SupplierConfirmation) {
                $needsSupplier = true;
            }
            $img = $product->images->sortByDesc('is_primary')->first();
            $out[] = [
                'product' => $product, 'variant' => $variant, 'sellable' => $sellable,
                'quantity' => $qty,
                'unit_price_cents' => $unit, 'line_total_cents' => $lineTotal,
                'unit_price' => $this->fmt($unit), 'line_total' => $this->fmt($lineTotal),
                'name' => $product->name, 'variant_name' => $variant?->name,
                'sku' => $variant?->sku ?? $product->sku,
                'image_path' => $img?->path,
                'availability' => $sellable->availability,
            ];
        }

        [$coupon, $discount] = $this->applyCoupon($couponCode, $subtotal, $phone, $lock);

        $afterDiscount = max(0, $subtotal - $discount);
        $zoneQuery = ShippingZone::query()->where('is_active', true)
            ->where(function ($q) use ($city) { $q->whereNull('city'); if ($city) $q->orWhereRaw('LOWER(city) = ?', [mb_strtolower(trim($city))]); });
        if ($province) $zoneQuery->where(function ($q) use ($province) { $q->whereNull('province')->orWhereRaw('LOWER(province) = ?', [mb_strtolower(trim($province))]); });
        $zoneQuery->where(function ($q) use ($postalCode) { $q->whereNull('postal_codes'); if ($postalCode) $q->orWhereJsonContains('postal_codes', trim($postalCode)); });
        $zone = $zoneQuery->orderByRaw('CASE WHEN city IS NULL THEN 1 ELSE 0 END')->first();
        $threshold = $zone?->free_shipping_threshold !== null ? $this->cents($zone->free_shipping_threshold) : $this->settings->money('free_shipping_threshold');
        $fee = $zone ? $this->cents($zone->shipping_fee) : $this->settings->money('shipping_fee');
        $shipping = $afterDiscount >= $threshold ? 0 : $fee;
        $total = $afterDiscount + $shipping;

        return [
            'lines' => $out,
            'coupon' => $coupon,
            'requires_supplier_confirmation' => $needsSupplier,
            'subtotal' => $this->fmt($subtotal),
            'discount_total' => $this->fmt($discount),
            'shipping_fee' => $this->fmt($shipping),
            'grand_total' => $this->fmt($total),
            'free_shipping_threshold' => $this->fmt($threshold),
            'amount_to_free_shipping' => $this->fmt(max(0, $threshold - $afterDiscount)),
            'currency' => config('alasra.currency'),
            'shipping_zone_id' => $zone?->id,
            'shipping_zone' => $zone ? ['id' => $zone->id, 'name' => $zone->name, 'estimated_min_days' => $zone->estimated_min_days, 'estimated_max_days' => $zone->estimated_max_days] : null,
        ];
    }

    /** @return array{0:?Coupon,1:int} */
    private function applyCoupon(?string $code, int $subtotal, ?string $phone, bool $lock): array
    {
        $code = $code !== null ? strtoupper(trim($code)) : '';
        if ($code === '') {
            return [null, 0];
        }
        $q = Coupon::query()->where('code', $code);
        $lock && $q->lockForUpdate();
        $coupon = $q->first();

        $bad = ValidationException::withMessages(['coupon_code' => ['This coupon code cannot be applied.']]);
        if (! $coupon || ! $coupon->is_active) throw $bad;
        if ($coupon->starts_at && $coupon->starts_at->isFuture()) throw $bad;
        if ($coupon->expires_at && $coupon->expires_at->isPast()) throw $bad;
        if ($coupon->usage_limit !== null && $coupon->used_count >= $coupon->usage_limit) throw $bad;
        if ($subtotal < $this->cents($coupon->min_subtotal)) throw $bad;
        if ($coupon->per_customer_limit !== null && $phone) {
            $used = CouponRedemption::where('coupon_id', $coupon->id)->where('customer_phone', $phone)->count();
            if ($used >= $coupon->per_customer_limit) throw $bad;
        }

        $discount = $coupon->type === 'percent'
            ? (int) round($subtotal * ((float) $coupon->value) / 100)
            : $this->cents($coupon->value);
        if ($coupon->max_discount !== null) {
            $discount = min($discount, $this->cents($coupon->max_discount));
        }
        return [$coupon, min($discount, $subtotal)];
    }

    private function cents(string|float|null $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    private function fmt(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
