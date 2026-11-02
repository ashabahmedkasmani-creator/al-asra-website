<?php

namespace Tests\Feature;

use Tests\TestCase;

class TrackingTest extends TestCase
{
    private function track(array $body)
    {
        return $this->asGuest()->postJson('/orders/track', $body);
    }

    public function test_tracking_needs_reference_and_matching_phone(): void
    {
        $ref = $this->placeOrder([$this->line($this->makeProduct())]);

        $ok = $this->track(['reference' => $ref, 'phone' => '03001234567'])->assertOk();
        $ok->assertJsonPath('data.reference', $ref)->assertJsonPath('data.status', 'pending');
        // lowercase reference and +92 phone format are accepted
        $this->track(['reference' => strtolower($ref), 'phone' => '+92 300 1234567'])->assertOk();
    }

    public function test_tracking_response_does_not_leak_personal_data(): void
    {
        $ref = $this->placeOrder([$this->line($this->makeProduct())]);
        $res = $this->track(['reference' => $ref, 'phone' => '03001234567'])->assertOk();
        $json = json_encode($res->json());
        foreach (['03001234567', 'Ali Khan', 'Gulshan', 'customer_phone', 'address_line', 'customer_email'] as $needle) {
            $this->assertStringNotContainsString($needle, $json);
        }
    }

    public function test_every_failure_returns_the_same_generic_404(): void
    {
        $ref = $this->placeOrder([$this->line($this->makeProduct())]);
        $wrongPhone = $this->track(['reference' => $ref, 'phone' => '03009999999']);
        $wrongRef = $this->track(['reference' => 'ASR-ZZZZZZZZZZ', 'phone' => '03001234567']);
        $badFormat = $this->track(['reference' => "' OR 1=1 --", 'phone' => '03001234567']);

        foreach ([$wrongPhone, $wrongRef, $badFormat] as $r) {
            $r->assertStatus(404);
        }
        $this->assertSame($wrongPhone->json(), $wrongRef->json());
        $this->assertSame($wrongPhone->json(), $badFormat->json());
    }

    public function test_reference_alone_is_not_enough(): void
    {
        $ref = $this->placeOrder([$this->line($this->makeProduct())]);
        $this->track(['reference' => $ref])->assertStatus(422);
    }

    public function test_tracking_is_strictly_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->track(['reference' => 'ASR-AAAAAAAAA'.$i, 'phone' => '03001234567'])->assertStatus(404);
        }
        $this->track(['reference' => 'ASR-AAAAAAAAAA', 'phone' => '03001234567'])->assertStatus(429);
    }

    public function test_guessing_one_reference_across_many_phones_is_throttled(): void
    {
        $ref = $this->placeOrder([$this->line($this->makeProduct())]);
        $codes = [];
        // Requests are spaced 2 minutes apart (cumulatively), so the per-minute IP limit is never hit;
        // the per-reference limit of 10 per hour must still stop the 11th guess.
        for ($i = 0; $i < 12; $i++) {
            $this->travel(2)->minutes();
            $codes[] = $this->track(['reference' => $ref, 'phone' => '0300000000'.($i % 10)])->status();
        }
        $this->assertContains(429, $codes);
    }
}
