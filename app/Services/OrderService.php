<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Order;
use App\Models\InventoryMovement;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Shipment;
use App\Models\User;
use App\Enums\Role;
use App\Notifications\OrderPlacedNotification;
use App\Notifications\OrderStatusUpdatedNotification;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function __construct(private PricingService $pricing) {}

    /**
     * @return array{0:Order,1:bool} [order, wasCreated]
     */
    public function place(array $data, ?User $user, ?string $idempotencyKey): array
    {
        if ($idempotencyKey) {
            if ($existing = Order::where('idempotency_key', $idempotencyKey)->first()) {
                return [$this->sameCustomerOrConflict($existing, $data, $user), false];
            }
        }

        try {
            $order = DB::transaction(fn () => $this->createOrder($data, $user, $idempotencyKey), 3);
        } catch (UniqueConstraintViolationException $e) {
            // Two identical requests raced; the unique idempotency key stopped the second one.
            if ($idempotencyKey && ($existing = Order::where('idempotency_key', $idempotencyKey)->first())) {
                return [$this->sameCustomerOrConflict($existing, $data, $user), false];
            }
            throw $e;
        }

        return [$order, true];
    }

    private function sameCustomerOrConflict(Order $existing, array $data, ?User $user): Order
    {
        $sameOwner = $user ? $existing->user_id === $user->id : $existing->user_id === null;
        abort_unless($sameOwner && $existing->customer_phone === $data['customer_phone'], 409, 'This request key was already used.');
        return $existing;
    }

    private function createOrder(array $data, ?User $user, ?string $key): Order
    {
        $method = PaymentMethod::where('code', $data['payment_method'])->where('is_active', true)->first();
        if (! $method) {
            throw ValidationException::withMessages(['payment_method' => ['This payment method is not available.']]);
        }

        $quote = $this->pricing->quote($data['items'], $data['coupon_code'] ?? null, $data['customer_phone'], lock: true, city: $data['city'] ?? null, province: $data['province'] ?? null, postalCode: $data['postal_code'] ?? null);

        $status = $quote['requires_supplier_confirmation'] ? OrderStatus::AwaitingSupplier : OrderStatus::Pending;

        $order = new Order;
        $order->forceFill([
            'reference' => $this->newReference(),
            'idempotency_key' => $key,
            'user_id' => $user?->id,
            'status' => $status,
            'payment_method' => $method->code,
            'payment_status' => PaymentStatus::Pending,
            'customer_name' => $data['customer_name'],
            'customer_email' => $data['customer_email'] ?? null,
            'customer_phone' => $data['customer_phone'],
            'address_line' => $data['address_line'],
            'city' => $data['city'],
            'province' => $data['province'] ?? null,
            'postal_code' => $data['postal_code'] ?? null,
            'notes' => $data['notes'] ?? null,
            'subtotal' => $quote['subtotal'],
            'discount_total' => $quote['discount_total'],
            'shipping_fee' => $quote['shipping_fee'],
            'grand_total' => $quote['grand_total'],
            'currency' => $quote['currency'],
            'coupon_id' => $quote['coupon']?->id,
            'coupon_code' => $quote['coupon']?->code,
            'shipping_zone_id' => $quote['shipping_zone_id'] ?? null,
            'placed_at' => now(),
        ])->save();

        foreach ($quote['lines'] as $l) {
            if ($l['sellable']->track_inventory) {
                $l['sellable']->decrement('stock_quantity', $l['quantity']);
                InventoryMovement::create([
                    'product_id' => $l['product']->id, 'variant_id' => $l['variant']?->id,
                    'user_id' => $user?->id, 'type' => 'order_out',
                    'quantity_change' => -$l['quantity'], 'quantity_after' => (int) $l['sellable']->fresh()->stock_quantity,
                    'reference' => $order->reference, 'note' => 'Stock reserved for customer order',
                ]);
            }
            (new OrderItem)->forceFill([
                'order_id' => $order->id,
                'product_id' => $l['product']->id,
                'variant_id' => $l['variant']?->id,
                'product_name' => $l['name'],
                'variant_name' => $l['variant_name'],
                'sku' => $l['sku'],
                'image_path' => $l['image_path'],
                'availability_at_order' => $l['availability']->value,
                'unit_price' => $l['unit_price'],
                'quantity' => $l['quantity'],
                'line_total' => $l['line_total'],
            ])->save();
        }

        if ($coupon = $quote['coupon']) {
            Coupon::whereKey($coupon->id)->increment('used_count');
            CouponRedemption::forceCreate([
                'coupon_id' => $coupon->id, 'order_id' => $order->id,
                'customer_phone' => $data['customer_phone'], 'discount' => $quote['discount_total'],
            ]);
        }

        Payment::forceCreate([
            'order_id' => $order->id, 'method' => $method->code,
            'status' => PaymentStatus::Pending, 'amount' => $quote['grand_total'],
        ]);

        $this->recordHistory($order, null, $status, 'Order placed', $user);
        $admins = User::whereIn('role', [Role::Admin->value, Role::Staff->value])->where('is_active', true)->get();
        foreach ($admins as $admin) $admin->notify(new OrderPlacedNotification($order->id, $order->reference, (float) $order->grand_total));

        return $order->load(['items', 'histories', 'payments']);
    }

    public function changeStatus(Order $order, OrderStatus $to, ?User $actor, ?string $note = null): Order
    {
        return DB::transaction(function () use ($order, $to, $actor, $note) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $from = $locked->status;

            if (! $from->canTransitionTo($to)) {
                throw ValidationException::withMessages([
                    'status' => ["An order that is {$from->value} cannot be changed to {$to->value}."],
                ]);
            }

            if ($to === OrderStatus::Cancelled) {
                $this->releaseInventoryAndCoupon($locked, $actor);
            }

            $locked->forceFill(['status' => $to])->save();

            if ($to === OrderStatus::Shipped) {
                Shipment::firstOrCreate(['order_id' => $locked->id])->forceFill(['shipped_at' => now()])->save();
            }
            if ($to === OrderStatus::Delivered) {
                Shipment::firstOrCreate(['order_id' => $locked->id])->forceFill(['delivered_at' => now()])->save();
            }

            $this->recordHistory($locked, $from, $to, $note, $actor);
            if ($locked->user) $locked->user->notify(new OrderStatusUpdatedNotification($locked->reference, $to->value));

            return $locked->load(['items', 'histories', 'payments', 'shipment']);
        });
    }

    private function releaseInventoryAndCoupon(Order $order, ?User $actor): void
    {
        foreach ($order->items()->get() as $item) {
            $sellable = $item->variant_id
                ? \App\Models\ProductVariant::find($item->variant_id)
                : ($item->product_id ? \App\Models\Product::withTrashed()->find($item->product_id) : null);
            if ($sellable && $sellable->track_inventory) {
                $sellable->increment('stock_quantity', $item->quantity);
                InventoryMovement::create([
                    'product_id' => $item->product_id, 'variant_id' => $item->variant_id,
                    'user_id' => $actor?->id, 'type' => 'order_cancelled_in',
                    'quantity_change' => $item->quantity, 'quantity_after' => (int) $sellable->fresh()->stock_quantity,
                    'reference' => $order->reference, 'note' => 'Stock released after order cancellation',
                ]);
            }
        }
        if ($order->coupon_id) {
            $deleted = CouponRedemption::where('order_id', $order->id)->delete();
            if ($deleted) {
                Coupon::whereKey($order->coupon_id)->where('used_count', '>', 0)->decrement('used_count');
            }
        }
    }

    private function recordHistory(Order $order, ?OrderStatus $from, OrderStatus $to, ?string $note, ?User $actor): void
    {
        OrderStatusHistory::forceCreate([
            'order_id' => $order->id,
            'from_status' => $from?->value,
            'to_status' => $to->value,
            'note' => $note,
            'changed_by' => $actor?->id,
        ]);
    }

    private function newReference(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // no 0/O/1/I
        do {
            $ref = 'ASR-';
            for ($i = 0; $i < 10; $i++) {
                $ref .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while (Order::where('reference', $ref)->exists());
        return $ref;
    }
}
