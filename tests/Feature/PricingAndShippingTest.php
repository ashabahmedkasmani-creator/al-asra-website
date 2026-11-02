<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\Setting;
use Tests\TestCase;

class PricingAndShippingTest extends TestCase
{
    private function quote(array $items, array $extra = [])
    {
        return $this->postJson('/checkout/quote', array_merge(['items' => $items], $extra));
    }

    public function test_shipping_at_9999_is_250(): void
    {
        $p = $this->makeProduct(['price' => 9999]);
        $r = $this->quote([$this->line($p)])->assertOk();
        $this->assertEquals(9999, $r->json('data.subtotal'));
        $this->assertEquals(250, $r->json('data.shipping_fee'));
        $this->assertEquals(10249, $r->json('data.grand_total'));
        $this->assertEquals(1, $r->json('data.amount_to_free_shipping'));
    }

    public function test_shipping_at_exactly_10000_is_free(): void
    {
        $p = $this->makeProduct(['price' => 10000]);
        $r = $this->quote([$this->line($p)])->assertOk();
        $this->assertEquals(0, $r->json('data.shipping_fee'));
        $this->assertEquals(10000, $r->json('data.grand_total'));
    }

    public function test_shipping_above_10000_is_free(): void
    {
        $p = $this->makeProduct(['price' => 10001]);
        $r = $this->quote([$this->line($p)])->assertOk();
        $this->assertEquals(0, $r->json('data.shipping_fee'));
    }

    public function test_threshold_applies_to_the_sum_of_lines(): void
    {
        $p = $this->makeProduct(['price' => 5000]);
        $this->assertEquals(0, $this->quote([$this->line($p, 2)])->json('data.shipping_fee'));
        $this->assertEquals(250, $this->quote([$this->line($p, 1)])->json('data.shipping_fee'));
    }

    public function test_threshold_uses_subtotal_after_discount(): void
    {
        $p = $this->makeProduct(['price' => 10500]);
        Coupon::create(['code' => 'TAKE1000', 'type' => 'fixed', 'value' => 1000]);
        $r = $this->quote([$this->line($p)], ['coupon_code' => 'take1000'])->assertOk();
        $this->assertEquals(10500, $r->json('data.subtotal'));
        $this->assertEquals(1000, $r->json('data.discount_total'));
        $this->assertEquals(250, $r->json('data.shipping_fee')); // 9,500 after discount < 10,000
        $this->assertEquals(9750, $r->json('data.grand_total'));
    }

    public function test_shipping_settings_are_configurable(): void
    {
        Setting::create(['key' => 'free_shipping_threshold', 'value' => '5000']);
        Setting::create(['key' => 'shipping_fee', 'value' => '400']);
        $p = $this->makeProduct(['price' => 4999]);
        $q = $this->makeProduct(['price' => 5000]);
        $this->assertEquals(400, $this->quote([$this->line($p)])->json('data.shipping_fee'));
        $this->assertEquals(0, $this->quote([$this->line($q)])->json('data.shipping_fee'));
    }

    public function test_sale_price_is_used_when_lower(): void
    {
        $p = $this->makeProduct(['price' => 2000, 'sale_price' => 1500]);
        $r = $this->quote([$this->line($p, 2)])->assertOk();
        $this->assertEquals(3000, $r->json('data.subtotal'));
    }

    public function test_client_supplied_prices_are_ignored(): void
    {
        $p = $this->makeProduct(['price' => 1000]);
        $r = $this->quote([['product_id' => $p->id, 'quantity' => 1, 'price' => 1, 'unit_price' => 1]], ['grand_total' => 1, 'shipping_fee' => 0])->assertOk();
        $this->assertEquals(1000, $r->json('data.subtotal'));
        $this->assertEquals(1250, $r->json('data.grand_total'));
    }

    public function test_coupon_rules(): void
    {
        $p = $this->makeProduct(['price' => 2000]);
        Coupon::create(['code' => 'TEN', 'type' => 'percent', 'value' => 10]);
        Coupon::create(['code' => 'OLD', 'type' => 'percent', 'value' => 10, 'expires_at' => now()->subDay()]);
        Coupon::create(['code' => 'SOON', 'type' => 'percent', 'value' => 10, 'starts_at' => now()->addDay()]);
        Coupon::create(['code' => 'OFF', 'type' => 'percent', 'value' => 10, 'is_active' => false]);
        Coupon::create(['code' => 'BIG', 'type' => 'fixed', 'value' => 100, 'min_subtotal' => 5000]);
        $used = Coupon::create(['code' => 'USED', 'type' => 'percent', 'value' => 10, 'usage_limit' => 1]);
        $used->forceFill(['used_count' => 1])->save();

        $ok = $this->quote([$this->line($p)], ['coupon_code' => 'TEN'])->assertOk();
        $this->assertEquals(200, $ok->json('data.discount_total'));

        foreach (['OLD', 'SOON', 'OFF', 'BIG', 'USED', 'NOPE'] as $code) {
            $this->quote([$this->line($p)], ['coupon_code' => $code])->assertStatus(422)->assertJsonValidationErrors('coupon_code');
        }
    }

    public function test_percent_coupon_respects_max_discount_and_never_exceeds_subtotal(): void
    {
        $p = $this->makeProduct(['price' => 1000]);
        Coupon::create(['code' => 'CAPPED', 'type' => 'percent', 'value' => 50, 'max_discount' => 100]);
        Coupon::create(['code' => 'HUGE', 'type' => 'fixed', 'value' => 99999]);
        $this->assertEquals(100, $this->quote([$this->line($p)], ['coupon_code' => 'CAPPED'])->json('data.discount_total'));
        $r = $this->quote([$this->line($p)], ['coupon_code' => 'HUGE']);
        $this->assertEquals(1000, $r->json('data.discount_total'));
        $this->assertEquals(250, $r->json('data.grand_total')); // free items still pay shipping
    }

    public function test_unavailable_and_unpublished_products_cannot_be_priced(): void
    {
        $this->quote([$this->line($this->makeProduct(['availability' => 'unavailable']))])->assertStatus(422);
        $this->quote([$this->line($this->makeProduct(['availability' => 'discontinued']))])->assertStatus(422);
        $this->quote([$this->line($this->makeProduct(['publish' => false]))])->assertStatus(422);
        $this->quote([['product_id' => 999999, 'quantity' => 1]])->assertStatus(422);
    }

    public function test_quantity_limits(): void
    {
        $p = $this->makeProduct();
        $this->quote([$this->line($p, 0)])->assertStatus(422);
        $this->quote([$this->line($p, 21)])->assertStatus(422);
        $this->quote([])->assertStatus(422);
    }
}
