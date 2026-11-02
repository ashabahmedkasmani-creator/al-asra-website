<?php

namespace Tests\Feature;

use App\Models\Order;
use Tests\TestCase;

class OrderStatusAndPaymentTest extends TestCase
{
    private function newOrder(array $over = [], int $price = 1500): Order
    {
        $ref = $this->placeOrder([$this->line($this->makeProduct(['price' => $price]))], $over);
        return Order::where('reference', $ref)->firstOrFail();
    }

    private function status($actor, Order $o, string $to)
    {
        return $this->as($actor)->patchJson("/admin/orders/{$o->id}/status", ['status' => $to]);
    }

    public function test_valid_status_flow_is_recorded_in_history(): void
    {
        $staff = $this->makeUser('staff');
        $o = $this->newOrder();
        foreach (['confirmed', 'processing', 'shipped', 'delivered'] as $s) {
            $this->status($staff, $o, $s)->assertOk()->assertJsonPath('data.status', $s);
        }
        $this->assertDatabaseCount('order_status_histories', 5); // placed + 4 transitions
        $this->assertNotNull($o->fresh()->shipment->shipped_at);
        $this->assertNotNull($o->fresh()->shipment->delivered_at);
    }

    public function test_invalid_transitions_are_rejected(): void
    {
        $staff = $this->makeUser('staff');
        $o = $this->newOrder();
        $this->status($staff, $o, 'delivered')->assertStatus(422);   // pending -> delivered
        $this->status($staff, $o, 'shipped')->assertStatus(422);
        $this->status($staff, $o, 'bogus')->assertStatus(422);
        $this->status($staff, $o, 'cancelled')->assertOk();
        $this->status($staff, $o, 'confirmed')->assertStatus(422);   // cancelled is final
        $this->assertSame('cancelled', $o->fresh()->status->value);
    }

    public function test_customers_cannot_use_admin_status_endpoint(): void
    {
        $u = $this->makeUser();
        $ref = $this->placeOrder([$this->line($this->makeProduct())], [], $u);
        $o = Order::where('reference', $ref)->first();
        $this->status($u, $o, 'delivered')->assertStatus(403);
        $this->assertSame('pending', $o->fresh()->status->value);
    }

    public function test_cod_cannot_be_marked_paid_before_delivery(): void
    {
        $admin = $this->makeUser('admin');
        $o = $this->newOrder();
        $this->as($admin)->postJson("/admin/orders/{$o->id}/payment/verify")->assertStatus(422);
        $this->assertSame('pending', $o->fresh()->payment_status->value);

        foreach (['confirmed', 'processing', 'shipped', 'delivered'] as $s) {
            $this->status($admin, $o, $s)->assertOk();
        }
        $this->assertSame('pending', $o->fresh()->payment_status->value); // delivery alone never marks it paid
        $this->as($admin)->postJson("/admin/orders/{$o->id}/payment/verify")->assertOk()->assertJsonPath('data.payment_status', 'paid');
        $this->assertDatabaseHas('payments', ['order_id' => $o->id, 'status' => 'paid', 'verified_by' => $admin->id]);
    }

    public function test_bank_transfer_requires_admin_verification_with_reference(): void
    {
        $admin = $this->makeUser('admin');
        $o = $this->newOrder(['payment_method' => 'bank_transfer']);
        $this->assertSame('pending', $o->payment_status->value);

        $this->as($admin)->postJson("/admin/orders/{$o->id}/payment/verify")->assertStatus(422);       // no reference
        $this->as($admin)->postJson("/admin/orders/{$o->id}/payment/verify", ['reference' => 'TXN-12345'])->assertOk();
        $this->assertSame('paid', $o->fresh()->payment_status->value);
        $this->as($admin)->postJson("/admin/orders/{$o->id}/payment/verify", ['reference' => 'TXN-12345'])->assertStatus(422); // no double-verify
        $this->assertDatabaseHas('audit_logs', ['action' => 'order.payment_verified']);
    }

    public function test_only_admins_can_verify_payments(): void
    {
        $o = $this->newOrder(['payment_method' => 'bank_transfer']);
        $this->as($this->makeUser('staff'))->postJson("/admin/orders/{$o->id}/payment/verify", ['reference' => 'X1'])->assertStatus(403);
        $this->as($this->makeUser('customer'))->postJson("/admin/orders/{$o->id}/payment/verify", ['reference' => 'X1'])->assertStatus(403);
        $this->assertSame('pending', $o->fresh()->payment_status->value);
    }

    public function test_cancelled_orders_cannot_be_marked_paid_and_refunds_need_a_paid_cancelled_order(): void
    {
        $admin = $this->makeUser('admin');
        $o = $this->newOrder(['payment_method' => 'bank_transfer']);
        $this->status($admin, $o, 'cancelled')->assertOk();
        $this->as($admin)->postJson("/admin/orders/{$o->id}/payment/verify", ['reference' => 'T1'])->assertStatus(422);
        $this->as($admin)->postJson("/admin/orders/{$o->id}/payment/refund")->assertStatus(422); // nothing was paid

        $paid = $this->newOrder(['payment_method' => 'bank_transfer', 'customer_phone' => '03112223334']);
        $this->as($admin)->postJson("/admin/orders/{$paid->id}/payment/verify", ['reference' => 'T2'])->assertOk();
        $this->as($admin)->postJson("/admin/orders/{$paid->id}/payment/refund")->assertStatus(422); // not cancelled yet
        $this->status($admin, $paid, 'cancelled')->assertOk();
        $this->as($admin)->postJson("/admin/orders/{$paid->id}/payment/refund")->assertOk()->assertJsonPath('data.payment_status', 'refunded');
    }

    public function test_payment_status_cannot_be_forged_through_checkout_or_status_endpoint(): void
    {
        $p = $this->makeProduct();
        $res = $this->asGuest()->postJson('/checkout', $this->payload([$this->line($p)], ['payment_status' => 'paid', 'status' => 'delivered']))->assertCreated();
        $res->assertJsonPath('data.payment_status', 'pending')->assertJsonPath('data.status', 'pending');

        $staff = $this->makeUser('staff');
        $o = Order::first();
        $this->as($staff)->patchJson("/admin/orders/{$o->id}/status", ['status' => 'confirmed', 'payment_status' => 'paid'])->assertOk();
        $this->assertSame('pending', $o->fresh()->payment_status->value);
    }

    public function test_shipment_details_can_be_set_by_staff_and_show_in_tracking(): void
    {
        $staff = $this->makeUser('staff');
        $o = $this->newOrder();
        $this->as($staff)->putJson("/admin/orders/{$o->id}/shipment", ['courier' => 'TCS', 'tracking_number' => 'TCS-998877'])->assertOk();
        $this->asGuest()->postJson('/orders/track', ['reference' => $o->reference, 'phone' => '03001234567'])
            ->assertOk()->assertJsonPath('data.shipment.courier', 'TCS');
    }

    public function test_admin_order_list_filters(): void
    {
        $admin = $this->makeUser('admin');
        $a = $this->newOrder();
        $b = $this->newOrder(['customer_phone' => '03211234567', 'customer_name' => 'Zara']);
        $this->status($admin, $b, 'confirmed')->assertOk();

        $this->as($admin)->getJson('/admin/orders?status=confirmed')->assertOk()->assertJsonCount(1, 'data');
        $this->as($admin)->getJson('/admin/orders?search='.$a->reference)->assertOk()->assertJsonCount(1, 'data');
        $this->as($admin)->getJson('/admin/orders?search=03211234567')->assertOk()->assertJsonCount(1, 'data');
        $this->as($admin)->getJson('/admin/orders?status=nonsense')->assertStatus(422);
    }
}
