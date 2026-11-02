<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    public function test_guest_can_place_a_cod_order_with_server_side_totals(): void
    {
        $a = $this->makeProduct(['price' => 2499]);
        $b = $this->makeProduct(['price' => 999, 'sale_price' => 800]);

        $res = $this->asGuest()->postJson('/checkout', $this->payload(
            [['product_id' => $a->id, 'quantity' => 1, 'price' => 1], ['product_id' => $b->id, 'quantity' => 2]],
            ['grand_total' => 1, 'shipping_fee' => 0, 'subtotal' => 1]
        ));

        $res->assertCreated()->assertJsonPath('data.status', 'pending')->assertJsonPath('data.payment_status', 'pending');
        $this->assertMatchesRegularExpression('/^ASR-[A-Z2-9]{10}$/', $res->json('data.reference'));
        $this->assertEquals(4099, $res->json('data.subtotal'));       // 2499 + 2*800
        $this->assertEquals(250, $res->json('data.shipping_fee'));
        $this->assertEquals(4349, $res->json('data.grand_total'));

        $order = Order::firstOrFail();
        $this->assertNull($order->user_id);
        $this->assertSame('03001234567', $order->customer_phone);
        $this->assertSame('4349.00', $order->grand_total);
        $this->assertDatabaseCount('order_items', 2);
        $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'method' => 'cod', 'status' => 'pending', 'amount' => 4349]);
        $this->assertDatabaseHas('order_status_histories', ['order_id' => $order->id, 'to_status' => 'pending']);
    }

    public function test_free_shipping_order_total(): void
    {
        $p = $this->makeProduct(['price' => 10000]);
        $ref = $this->placeOrder([$this->line($p)]);
        $this->assertSame('10000.00', Order::where('reference', $ref)->first()->grand_total);
        $this->assertSame('0.00', Order::where('reference', $ref)->first()->shipping_fee);
    }

    public function test_order_items_are_snapshots_and_survive_product_changes(): void
    {
        $p = $this->makeProduct(['name' => 'Original Name', 'price' => 1500]);
        $ref = $this->placeOrder([$this->line($p)]);

        $p->update(['name' => 'Renamed', 'price' => 9999]);
        $p->delete(); // soft delete

        $item = OrderItem::first();
        $this->assertSame('Original Name', $item->product_name);
        $this->assertSame('1500.00', $item->unit_price);
        $this->assertSame('Original Name', Order::where('reference', $ref)->first()->items->first()->product_name);
    }

    public function test_invalid_input_is_rejected(): void
    {
        $p = $this->makeProduct();
        foreach ([
            ['customer_phone' => '12345'], ['customer_name' => ''], ['city' => ''], ['address_line' => 'x'],
            ['payment_method' => 'bitcoin'], ['customer_email' => 'not-an-email'],
        ] as $bad) {
            $this->asGuest()->postJson('/checkout', $this->payload([$this->line($p)], $bad))->assertStatus(422);
        }
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_text_fields_are_stripped_of_html(): void
    {
        $p = $this->makeProduct();
        $ref = $this->placeOrder([$this->line($p)], ['customer_name' => '<script>alert(1)</script>Ali', 'notes' => '<b>Ring</b> twice']);
        $o = Order::where('reference', $ref)->first();
        $this->assertStringNotContainsString('<', $o->customer_name);
        $this->assertStringNotContainsString('<', $o->notes);
    }

    public function test_cannot_order_unavailable_or_unpublished_products(): void
    {
        foreach ([['availability' => 'unavailable'], ['availability' => 'discontinued'], ['publish' => false]] as $o) {
            $p = $this->makeProduct($o);
            $this->asGuest()->postJson('/checkout', $this->payload([$this->line($p)]))->assertStatus(422);
        }
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_supplier_confirmation_products_create_awaiting_supplier_orders(): void
    {
        $p = $this->makeProduct(['availability' => 'supplier_confirmation_required']);
        $ref = $this->placeOrder([$this->line($p)]);
        $this->assertSame('awaiting_supplier', Order::where('reference', $ref)->first()->status->value);
    }

    public function test_inventory_is_decremented_and_overselling_is_blocked(): void
    {
        $p = $this->makeProduct(['track_inventory' => true, 'stock_quantity' => 3]);
        $this->placeOrder([$this->line($p, 2)]);
        $this->assertSame(1, $p->fresh()->stock_quantity);

        $this->asGuest()->postJson('/checkout', $this->payload([$this->line($p, 2)]))->assertStatus(422);
        $this->assertSame(1, $p->fresh()->stock_quantity);
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_cancelling_restores_stock_and_coupon_usage(): void
    {
        $p = $this->makeProduct(['track_inventory' => true, 'stock_quantity' => 5, 'price' => 2000]);
        $c = Coupon::create(['code' => 'SAVE', 'type' => 'fixed', 'value' => 100]);
        $user = $this->makeUser();
        $ref = $this->placeOrder([$this->line($p, 2)], ['coupon_code' => 'SAVE'], $user);
        $this->assertSame(3, $p->fresh()->stock_quantity);
        $this->assertSame(1, $c->fresh()->used_count);

        $this->as($user)->postJson("/orders/$ref/cancel")->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->assertSame(5, $p->fresh()->stock_quantity);
        $this->assertSame(0, $c->fresh()->used_count);
    }

    public function test_order_creation_is_atomic(): void
    {
        $a = $this->makeProduct(['track_inventory' => true, 'stock_quantity' => 5]);
        $b = $this->makeProduct();
        $c = Coupon::create(['code' => 'ATOM', 'type' => 'fixed', 'value' => 10]);

        $count = 0;
        OrderItem::creating(function () use (&$count) {
            if (++$count === 2) {
                throw new \RuntimeException('simulated failure mid-order');
            }
        });

        $this->asGuest()->postJson('/checkout', $this->payload([$this->line($a, 2), $this->line($b)], ['coupon_code' => 'ATOM']))->assertStatus(500);

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertDatabaseCount('payments', 0);
        $this->assertSame(5, $a->fresh()->stock_quantity);   // stock decrement rolled back
        $this->assertSame(0, $c->fresh()->used_count);       // coupon usage rolled back
    }

    public function test_coupon_is_applied_and_usage_counted(): void
    {
        $p = $this->makeProduct(['price' => 2000]);
        $c = Coupon::create(['code' => 'TEN', 'type' => 'percent', 'value' => 10, 'usage_limit' => 1]);
        $ref = $this->placeOrder([$this->line($p)], ['coupon_code' => 'ten']);
        $o = Order::where('reference', $ref)->first();
        $this->assertSame('200.00', $o->discount_total);
        $this->assertSame('2050.00', $o->grand_total); // 1800 + 250 shipping
        $this->assertSame(1, $c->fresh()->used_count);

        // limit reached: second order with the same coupon is refused
        $this->asGuest()->postJson('/checkout', $this->payload([$this->line($p)], ['coupon_code' => 'TEN']))->assertStatus(422);
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_per_customer_coupon_limit(): void
    {
        $p = $this->makeProduct(['price' => 2000]);
        Coupon::create(['code' => 'ONCE', 'type' => 'fixed', 'value' => 100, 'per_customer_limit' => 1]);
        $this->placeOrder([$this->line($p)], ['coupon_code' => 'ONCE']);
        $this->asGuest()->postJson('/checkout', $this->payload([$this->line($p)], ['coupon_code' => 'ONCE']))->assertStatus(422);
        // a different phone number may still use it
        $this->asGuest()->postJson('/checkout', $this->payload([$this->line($p)], ['coupon_code' => 'ONCE', 'customer_phone' => '03111111111']))->assertCreated();
    }

    public function test_duplicate_requests_with_the_same_idempotency_key_create_one_order(): void
    {
        $p = $this->makeProduct();
        $key = 'idem-key-1234567890abcdef';
        $first = $this->asGuest()->withHeader('Idempotency-Key', $key)->postJson('/checkout', $this->payload([$this->line($p)]));
        $second = $this->asGuest()->withHeader('Idempotency-Key', $key)->postJson('/checkout', $this->payload([$this->line($p)]));

        $first->assertCreated();
        $second->assertOk()->assertHeader('Idempotent-Replayed', 'true');
        $this->assertSame($first->json('data.reference'), $second->json('data.reference'));
        $this->assertDatabaseCount('orders', 1);

        // same key but a different customer must not reveal or reuse the order
        $this->asGuest()->withHeader('Idempotency-Key', $key)
            ->postJson('/checkout', $this->payload([$this->line($p)], ['customer_phone' => '03211111111']))->assertStatus(409);
    }

    public function test_malformed_idempotency_key_is_rejected(): void
    {
        $p = $this->makeProduct();
        $this->asGuest()->withHeader('Idempotency-Key', 'short')->postJson('/checkout', $this->payload([$this->line($p)]))->assertStatus(422);
    }

    public function test_bank_transfer_order_starts_unpaid_with_instructions(): void
    {
        $p = $this->makeProduct();
        $res = $this->asGuest()->postJson('/checkout', $this->payload([$this->line($p)], ['payment_method' => 'bank_transfer']))->assertCreated();
        $res->assertJsonPath('data.payment_status', 'pending');
        $this->assertNotEmpty($res->json('meta.payment_instructions'));
    }

    public function test_guest_cannot_use_a_saved_address_id(): void
    {
        $owner = $this->makeUser();
        $id = $this->as($owner)->postJson('/addresses', ['recipient_name' => 'A', 'phone' => '03001234567', 'address_line' => 'House 1 Block 2', 'city' => 'Karachi'])->json('data.id');
        $p = $this->makeProduct();
        $payload = $this->payload([$this->line($p)], ['address_id' => $id]);
        unset($payload['address_line'], $payload['city']);
        $this->asGuest()->postJson('/checkout', $payload)->assertStatus(422);
        // another logged-in user cannot use it either
        $this->as($this->makeUser())->postJson('/checkout', $payload)->assertStatus(422);
        // the owner can
        $this->as($owner)->postJson('/checkout', $payload)->assertCreated();
    }

    public function test_checkout_is_rate_limited(): void
    {
        $p = $this->makeProduct();
        for ($i = 0; $i < 10; $i++) {
            $this->asGuest()->postJson('/checkout', $this->payload([$this->line($p)], ['customer_phone' => '03'.str_pad((string) (100000000 + $i), 9, '0', STR_PAD_LEFT)]))->assertCreated();
        }
        $this->asGuest()->postJson('/checkout', $this->payload([$this->line($p)]))->assertStatus(429);
    }
}
