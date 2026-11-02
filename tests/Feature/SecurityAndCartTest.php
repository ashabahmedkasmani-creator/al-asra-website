<?php

namespace Tests\Feature;

use Tests\TestCase;

class SecurityAndCartTest extends TestCase
{
    public function test_health_endpoint_reveals_no_configuration(): void
    {
        $res = $this->getJson('/health')->assertOk()->assertJsonPath('status', 'ok');
        $json = json_encode($res->json());
        foreach (['APP_KEY', 'DB_', 'password', 'mysql', 'sqlite', 'version'] as $needle) {
            $this->assertStringNotContainsStringIgnoringCase($needle, $json);
        }
    }

    public function test_security_headers_are_present(): void
    {
        $this->getJson('/health')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'no-referrer');
    }

    public function test_cors_allows_only_the_configured_frontend_origin(): void
    {
        $ok = $this->withHeaders(['Origin' => 'http://localhost:3000', 'Access-Control-Request-Method' => 'POST'])->options('/checkout');
        $ok->assertHeader('Access-Control-Allow-Origin', 'http://localhost:3000');

        $bad = $this->withHeaders(['Origin' => 'https://evil.example', 'Access-Control-Request-Method' => 'POST'])->options('/checkout');
        $this->assertNull($bad->headers->get('Access-Control-Allow-Origin'));
    }

    public function test_errors_do_not_leak_internals(): void
    {
        $r = $this->getJson('/products/does-not-exist')->assertStatus(404);
        $this->assertSame('Resource not found.', $r->json('message'));
        $this->assertStringNotContainsString('App\\Models', json_encode($r->json()));
        $this->getJson('/definitely/not/a/route')->assertStatus(404);
        $this->asGuest()->getJson('/admin/orders/1')->assertStatus(401);
    }

    public function test_unknown_admin_ids_return_404_for_authorised_users_only(): void
    {
        $this->as($this->makeUser('customer'))->getJson('/admin/orders/99999')->assertStatus(403);
        $this->as($this->makeUser('staff'))->getJson('/admin/orders/99999')->assertStatus(404);
    }

    public function test_guest_cart_lifecycle_and_isolation(): void
    {
        $p = $this->makeProduct(['price' => 1200]);
        $res = $this->asGuest()->postJson('/cart/items', ['product_id' => $p->id, 'quantity' => 2])->assertOk();
        $token = $res->json('cart_token');
        $this->assertNotEmpty($token);
        $this->assertEquals(2400, $res->json('data.pricing.subtotal'));
        $itemId = $res->json('data.items.0.id');

        // same item again increments the quantity instead of duplicating
        $this->withHeader('X-Cart-Token', $token)->postJson('/cart/items', ['product_id' => $p->id, 'quantity' => 1])
            ->assertOk()->assertJsonCount(1, 'data.items')->assertJsonPath('data.items.0.quantity', 3);

        // a different visitor cannot see or modify it
        $this->asGuest()->withHeader('X-Cart-Token', 'x'.str_repeat('y', 47))->getJson('/cart')->assertOk()->assertJsonCount(0, 'data.items');
        $this->asGuest()->withHeader('X-Cart-Token', 'x'.str_repeat('y', 47))->deleteJson("/cart/items/$itemId")->assertStatus(404);

        $this->asGuest()->withHeader('X-Cart-Token', $token)->patchJson("/cart/items/$itemId", ['quantity' => 1])->assertOk()->assertJsonPath('data.items.0.quantity', 1);
        $this->patchJson("/cart/items/$itemId", ['quantity' => 999])->assertStatus(422);
    }

    public function test_checkout_from_cart_clears_it_and_uses_server_prices(): void
    {
        $p = $this->makeProduct(['price' => 4000]);
        $token = $this->asGuest()->postJson('/cart/items', ['product_id' => $p->id, 'quantity' => 1])->json('cart_token');
        $payload = $this->payload([]);
        unset($payload['items']);

        $order = $this->asGuest()->withHeader('X-Cart-Token', $token)->postJson('/checkout', $payload)->assertCreated();
        $this->assertEquals(4250, $order->json('data.grand_total'));
        $this->asGuest()->withHeader('X-Cart-Token', $token)->getJson('/cart')->assertJsonCount(0, 'data.items');
        $this->asGuest()->postJson('/checkout', $payload)->assertStatus(422); // empty bag
    }

    public function test_cart_drops_products_that_are_no_longer_published(): void
    {
        $p = $this->makeProduct();
        $token = $this->asGuest()->postJson('/cart/items', ['product_id' => $p->id, 'quantity' => 1])->json('cart_token');
        $p->unpublish();
        $this->asGuest()->withHeader('X-Cart-Token', $token)->getJson('/cart')->assertOk()->assertJsonCount(0, 'data.items');
    }

    public function test_cannot_add_unavailable_products_to_cart(): void
    {
        $p = $this->makeProduct(['availability' => 'discontinued']);
        $this->asGuest()->postJson('/cart/items', ['product_id' => $p->id, 'quantity' => 1])->assertStatus(422);
    }

    public function test_reviews_require_a_delivered_purchase_and_moderation(): void
    {
        $u = $this->makeUser();
        $p = $this->makeProduct();
        $ref = $this->placeOrder([$this->line($p)], [], $u);

        $this->as($u)->postJson("/products/{$p->slug}/reviews", ['rating' => 5, 'body' => 'Great'])->assertStatus(403); // not delivered yet
        \App\Models\Order::where('reference', $ref)->first()->forceFill(['status' => 'delivered'])->save();
        $this->as($u)->postJson("/products/{$p->slug}/reviews", ['rating' => 5, 'body' => '<b>Great</b>'])->assertCreated();
        $this->getJson("/products/{$p->slug}/reviews")->assertJsonCount(0, 'data'); // pending moderation

        $review = \App\Models\Review::first();
        $this->assertStringNotContainsString('<', $review->body);
        $this->as($this->makeUser('staff'))->postJson("/admin/reviews/{$review->id}/approve")->assertOk();
        $this->asGuest()->getJson("/products/{$p->slug}/reviews")->assertJsonCount(1, 'data');
    }

    public function test_coupon_admin_validation(): void
    {
        $admin = $this->makeUser('admin');
        $this->as($admin)->postJson('/admin/coupons', ['code' => 'ok10', 'type' => 'percent', 'value' => 10])->assertCreated()->assertJsonPath('data.code', 'OK10');
        $this->as($admin)->postJson('/admin/coupons', ['code' => 'OK10', 'type' => 'percent', 'value' => 10])->assertStatus(422);
        $this->as($admin)->postJson('/admin/coupons', ['code' => 'TOOMUCH', 'type' => 'percent', 'value' => 150])->assertStatus(422);
        $this->as($admin)->postJson('/admin/coupons', ['code' => 'bad code', 'type' => 'fixed', 'value' => 5])->assertStatus(422);
    }
}
