<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Setting;
use App\Models\User;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    /** [method, uri] pairs that require specific admin permissions. */
    private function adminOnly(): array
    {
        return [
            ['getJson', '/admin/settings'], ['getJson', '/admin/users'], ['getJson', '/admin/coupons'],
            ['getJson', '/admin/customers'], ['getJson', '/admin/audit-logs'],
        ];
    }

    private function staffAllowed(): array
    {
        return [
            '/admin/dashboard', '/admin/products', '/admin/categories', '/admin/orders',
            '/admin/banners', '/admin/faqs', '/admin/reviews',
        ];
    }

    public function test_guests_cannot_reach_admin_apis(): void
    {
        foreach (array_merge($this->staffAllowed(), array_column($this->adminOnly(), 1)) as $uri) {
            $this->asGuest()->getJson($uri)->assertStatus(401);
        }
    }

    public function test_customers_are_forbidden_from_every_admin_api(): void
    {
        $c = $this->makeUser('customer');
        foreach (array_merge($this->staffAllowed(), array_column($this->adminOnly(), 1)) as $uri) {
            $this->as($c)->getJson($uri)->assertStatus(403);
        }
        $this->as($c)->postJson('/admin/products', ['name' => 'x', 'sku' => 'x', 'price' => 10])->assertStatus(403);
        $this->as($c)->patchJson('/admin/settings', ['settings' => ['shipping_fee' => 0]])->assertStatus(403);
        $this->as($c)->patchJson('/admin/users/'.$c->id.'/role', ['role' => 'admin', 'current_password' => 'Str0ngPassw0rd!'])->assertStatus(403);
        $this->assertSame('customer', $c->fresh()->role->value);
    }

    public function test_staff_have_least_privilege_access(): void
    {
        $s = $this->makeUser('staff');
        foreach ($this->staffAllowed() as $uri) {
            $this->as($s)->getJson($uri)->assertOk();
        }
        foreach ($this->adminOnly() as [$m, $uri]) {
            $this->as($s)->$m($uri)->assertStatus(403);
        }
        $this->as($s)->patchJson('/admin/settings', ['settings' => ['shipping_fee' => 0]])->assertStatus(403);
        $this->as($s)->postJson('/admin/coupons', ['code' => 'FREE', 'type' => 'percent', 'value' => 100])->assertStatus(403);
        $this->assertDatabaseCount('coupons', 0);
    }

    public function test_admin_can_reach_everything(): void
    {
        $a = $this->makeUser('admin');
        foreach (array_merge($this->staffAllowed(), array_column($this->adminOnly(), 1)) as $uri) {
            $this->as($a)->getJson($uri)->assertOk();
        }
    }

    public function test_deactivated_staff_lose_access_immediately(): void
    {
        $s = $this->makeUser('staff');
        $this->as($s)->getJson('/admin/dashboard')->assertOk();
        $s->forceFill(['is_active' => false])->save();
        $this->as($s)->getJson('/admin/dashboard')->assertStatus(401);
    }

    public function test_dashboard_numbers_come_from_the_database(): void
    {
        $p = $this->makeProduct(['price' => 3000]);
        $this->makeProduct(['publish' => false]);
        $this->placeOrder([$this->line($p)]);
        $this->placeOrder([$this->line($p, 2)], ['customer_phone' => '03111111111']);
        $this->makeUser('customer');

        $d = $this->as($this->makeUser('admin'))->getJson('/admin/dashboard')->assertOk()->json('data');
        $this->assertSame(2, $d['orders_total']);
        $this->assertSame(2, $d['orders_by_status']['pending']);
        $this->assertSame(2, $d['products_total']);
        $this->assertSame(1, $d['products_published']);
        $this->assertSame(1, $d['customers_total']);
        $this->assertEquals(0, $d['revenue_paid']);
        $this->assertEquals(3250 + 6250, $d['revenue_pending']); // (3000+250) + (6000+250)
    }

    public function test_staff_dashboard_hides_financial_data(): void
    {
        $d = $this->as($this->makeUser('staff'))->getJson('/admin/dashboard')->assertOk()->json('data');
        $this->assertArrayNotHasKey('revenue_paid', $d);
        $this->assertArrayNotHasKey('customers_total', $d);
    }

    public function test_admin_can_change_roles_with_password_confirmation_and_tokens_are_revoked(): void
    {
        $admin = $this->makeUser('admin');
        $target = $this->makeUser('customer');
        $target->createToken('t');

        $this->as($admin)->patchJson("/admin/users/{$target->id}/role", ['role' => 'staff', 'current_password' => 'wrong'])->assertStatus(422);
        $this->assertSame('customer', $target->fresh()->role->value);

        $this->as($admin)->patchJson("/admin/users/{$target->id}/role", ['role' => 'staff', 'current_password' => 'Str0ngPassw0rd!'])->assertOk();
        $this->assertSame('staff', $target->fresh()->role->value);
        $this->assertSame(0, $target->tokens()->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.role_changed', 'user_id' => $admin->id, 'auditable_id' => $target->id]);
    }

    public function test_admin_cannot_change_own_role_and_staff_cannot_change_roles(): void
    {
        $admin = $this->makeUser('admin');
        $this->as($admin)->patchJson("/admin/users/{$admin->id}/role", ['role' => 'customer', 'current_password' => 'Str0ngPassw0rd!'])->assertStatus(422);
        $this->assertSame('admin', $admin->fresh()->role->value);

        $staff = $this->makeUser('staff');
        $this->as($staff)->patchJson("/admin/users/{$staff->id}/role", ['role' => 'admin', 'current_password' => 'Str0ngPassw0rd!'])->assertStatus(403);
        $this->assertSame('staff', $staff->fresh()->role->value);
    }

    public function test_settings_update_is_validated_whitelisted_and_audited(): void
    {
        $admin = $this->makeUser('admin');
        $this->as($admin)->patchJson('/admin/settings', ['settings' => ['shipping_fee' => 300, 'free_shipping_threshold' => 12000]])->assertOk();
        $this->assertSame('300', Setting::where('key', 'shipping_fee')->value('value'));
        $this->assertTrue(AuditLog::where('action', 'settings.updated')->exists());

        $this->as($admin)->patchJson('/admin/settings', ['settings' => ['shipping_fee' => -5]])->assertStatus(422);
        $this->as($admin)->patchJson('/admin/settings', ['settings' => ['shipping_fee' => 'abc']])->assertStatus(422);
        $this->as($admin)->patchJson('/admin/settings', ['settings' => ['app_key' => 'x']])->assertStatus(422);
        $this->assertSame('300', Setting::where('key', 'shipping_fee')->value('value'));

        // the new value really drives checkout
        $p = $this->makeProduct(['price' => 11000]);
        $this->asGuest()->postJson('/checkout/quote', ['items' => [$this->line($p)]])->assertOk();
        $this->assertEquals(300, $this->postJson('/checkout/quote', ['items' => [$this->line($p)]])->json('data.shipping_fee'));
    }

    public function test_public_settings_expose_only_whitelisted_public_keys(): void
    {
        $res = $this->getJson('/settings')->assertOk();
        $this->assertArrayHasKey('shipping_fee', $res->json('data'));
        $this->assertArrayNotHasKey('bank_account_title', $res->json('data'));
    }

    public function test_audit_logs_never_store_secrets(): void
    {
        $admin = $this->makeUser('admin');
        $this->as($admin)->postJson('/admin/users', [
            'name' => 'New Staff', 'email' => 'newstaff@example.test', 'password' => 'Sup3rSecretPass!',
            'role' => 'staff', 'current_password' => 'Str0ngPassw0rd!',
        ])->assertCreated();
        $dump = json_encode(AuditLog::all()->toArray());
        $this->assertStringNotContainsString('Sup3rSecretPass', $dump);
        $this->assertStringNotContainsString('Str0ngPassw0rd', $dump);
        $this->assertStringNotContainsString('password', strtolower($dump));
    }

    public function test_user_mass_assignment_protection(): void
    {
        $u = new User(['name' => 'X', 'email' => 'x@example.test', 'password' => 'Str0ngPassw0rd!', 'role' => 'admin', 'is_active' => false]);
        $u->save();
        $u->refresh();
        $this->assertSame('customer', $u->role->value);
        $this->assertTrue($u->is_active);
    }
}
