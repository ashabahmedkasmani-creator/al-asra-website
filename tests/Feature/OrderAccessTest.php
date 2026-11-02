<?php

namespace Tests\Feature;

use App\Models\Order;
use Tests\TestCase;

class OrderAccessTest extends TestCase
{
    public function test_customers_only_see_their_own_orders(): void
    {
        $a = $this->makeUser();
        $b = $this->makeUser();
        $p = $this->makeProduct();
        $refA = $this->placeOrder([$this->line($p)], [], $a);
        $guestRef = $this->placeOrder([$this->line($p)], ['customer_phone' => '03450000000']);

        $this->as($a)->getJson('/orders')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.reference', $refA);
        $this->as($a)->getJson("/orders/$refA")->assertOk();

        // object-level authorization: B cannot read or cancel A's order, and cannot read a guest order
        $this->as($b)->getJson("/orders/$refA")->assertStatus(404);
        $this->as($b)->postJson("/orders/$refA/cancel")->assertStatus(404);
        $this->as($b)->getJson("/orders/$guestRef")->assertStatus(404);
        $this->as($b)->getJson('/orders')->assertOk()->assertJsonCount(0, 'data');
        $this->assertSame('pending', Order::where('reference', $refA)->first()->status->value);
    }

    public function test_orders_require_authentication(): void
    {
        $p = $this->makeProduct();
        $ref = $this->placeOrder([$this->line($p)]);
        $this->asGuest()->getJson("/orders/$ref")->assertStatus(401);
        $this->asGuest()->postJson("/orders/$ref/cancel")->assertStatus(401);
    }

    public function test_customer_cannot_cancel_after_processing_starts(): void
    {
        $u = $this->makeUser();
        $ref = $this->placeOrder([$this->line($this->makeProduct())], [], $u);
        Order::where('reference', $ref)->first()->forceFill(['status' => 'processing'])->save();
        $this->as($u)->postJson("/orders/$ref/cancel")->assertStatus(403);
        $this->assertSame('processing', Order::where('reference', $ref)->first()->status->value);
    }

    public function test_customer_order_view_hides_internal_fields(): void
    {
        $u = $this->makeUser();
        $ref = $this->placeOrder([$this->line($this->makeProduct())], [], $u);
        $res = $this->as($u)->getJson("/orders/$ref")->assertOk();
        $res->assertJsonMissingPath('data.id')->assertJsonMissingPath('data.user_id')->assertJsonMissingPath('data.idempotency_key');
        $this->assertStringNotContainsString('verified_by', json_encode($res->json()));
    }
}
