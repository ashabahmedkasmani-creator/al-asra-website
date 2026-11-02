<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AuthTest extends TestCase
{
    private function registerPayload(array $o = []): array
    {
        return array_merge(['name' => 'Sara Ahmed', 'email' => 'sara@example.test', 'phone' => '0312 3456789',
            'password' => 'Str0ngPassw0rd!', 'password_confirmation' => 'Str0ngPassw0rd!'], $o);
    }

    public function test_customer_can_register_and_receives_token(): void
    {
        $res = $this->postJson('/auth/register', $this->registerPayload());
        $res->assertCreated()->assertJsonPath('data.user.role', 'customer')->assertJsonStructure(['data' => ['token', 'user' => ['id', 'email']]]);
        $this->assertDatabaseHas('users', ['email' => 'sara@example.test', 'phone' => '03123456789', 'role' => 'customer']);
        $res->assertJsonMissing(['password']);
    }

    public function test_registration_cannot_set_role(): void
    {
        $this->postJson('/auth/register', $this->registerPayload(['role' => 'admin', 'is_active' => false]))->assertCreated();
        $this->assertSame('customer', User::first()->role->value);
        $this->assertTrue(User::first()->is_active);
    }

    public function test_weak_and_duplicate_registrations_are_rejected(): void
    {
        $this->postJson('/auth/register', $this->registerPayload(['password' => 'short', 'password_confirmation' => 'short']))->assertStatus(422)->assertJsonValidationErrors('password');
        $this->postJson('/auth/register', $this->registerPayload(['password_confirmation' => 'different']))->assertStatus(422);
        $this->postJson('/auth/register', $this->registerPayload())->assertCreated();
        $this->postJson('/auth/register', $this->registerPayload())->assertStatus(422);
    }

    public function test_login_succeeds_and_fails_with_identical_generic_errors(): void
    {
        $this->makeUser('customer', ['email' => 'real@example.test']);
        $this->postJson('/auth/login', ['email' => 'real@example.test', 'password' => 'Str0ngPassw0rd!'])->assertOk()->assertJsonStructure(['data' => ['token']]);

        $wrongPw = $this->postJson('/auth/login', ['email' => 'real@example.test', 'password' => 'nope-nope-nope']);
        $noUser = $this->postJson('/auth/login', ['email' => 'ghost@example.test', 'password' => 'nope-nope-nope']);
        $wrongPw->assertStatus(401);
        $noUser->assertStatus(401);
        $this->assertSame($wrongPw->json(), $noUser->json()); // no account enumeration
    }

    public function test_inactive_user_cannot_login(): void
    {
        $u = $this->makeUser('customer', ['email' => 'off@example.test']);
        $u->forceFill(['is_active' => false])->save();
        $this->postJson('/auth/login', ['email' => 'off@example.test', 'password' => 'Str0ngPassw0rd!'])->assertStatus(401);
    }

    public function test_login_is_rate_limited(): void
    {
        $this->makeUser('customer', ['email' => 'rl@example.test']);
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/auth/login', ['email' => 'rl@example.test', 'password' => 'wrong-wrong-1'])->assertStatus(401);
        }
        $this->postJson('/auth/login', ['email' => 'rl@example.test', 'password' => 'Str0ngPassw0rd!'])->assertStatus(429);
    }

    public function test_registration_is_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/auth/register', $this->registerPayload(['email' => "r$i@example.test"]))->assertCreated();
        }
        $this->postJson('/auth/register', $this->registerPayload(['email' => 'r9@example.test']))->assertStatus(429);
    }

    public function test_logout_revokes_the_token(): void
    {
        $this->makeUser('customer', ['email' => 'out@example.test']);
        $token = $this->postJson('/auth/login', ['email' => 'out@example.test', 'password' => 'Str0ngPassw0rd!'])->json('data.token');

        $this->asGuest()->withToken($token)->getJson('/me')->assertOk();
        $this->asGuest()->withToken($token)->postJson('/auth/logout')->assertOk();
        $this->asGuest()->withToken($token)->getJson('/me')->assertStatus(401);
    }

    public function test_protected_routes_require_authentication(): void
    {
        $this->asGuest()->getJson('/me')->assertStatus(401);
        $this->getJson('/orders')->assertStatus(401);
        $this->getJson('/addresses')->assertStatus(401);
    }

    public function test_profile_update_cannot_change_role(): void
    {
        $u = $this->makeUser();
        $this->as($u)->patchJson('/me', ['name' => 'New Name', 'role' => 'admin', 'is_active' => false])->assertOk();
        $u->refresh();
        $this->assertSame('New Name', $u->name);
        $this->assertSame('customer', $u->role->value);
    }

    public function test_forgot_password_response_is_identical_for_known_and_unknown_emails(): void
    {
        Notification::fake();
        $u = $this->makeUser('customer', ['email' => 'known@example.test']);
        $a = $this->postJson('/auth/forgot-password', ['email' => 'known@example.test']);
        $b = $this->postJson('/auth/forgot-password', ['email' => 'unknown@example.test']);
        $a->assertOk();
        $b->assertOk();
        $this->assertSame($a->json(), $b->json());
        Notification::assertSentTo($u, ResetPassword::class);
        Notification::assertCount(1);
    }

    public function test_password_reset_works_once_and_revokes_tokens(): void
    {
        $u = $this->makeUser('customer', ['email' => 'reset@example.test']);
        $u->createToken('old');
        $token = Password::createToken($u);
        $body = ['token' => $token, 'email' => 'reset@example.test', 'password' => 'An0therStrongPass!', 'password_confirmation' => 'An0therStrongPass!'];

        $this->postJson('/auth/reset-password', $body)->assertOk();
        $this->assertSame(0, $u->tokens()->count());
        $this->postJson('/auth/login', ['email' => 'reset@example.test', 'password' => 'An0therStrongPass!'])->assertOk();
        $this->postJson('/auth/reset-password', $body)->assertStatus(422); // token is single use
    }

    public function test_user_cannot_read_or_edit_another_users_address(): void
    {
        $a = $this->makeUser();
        $b = $this->makeUser();
        $addr = ['recipient_name' => 'Ali', 'phone' => '03001234567', 'address_line' => 'House 1, Block 2', 'city' => 'Karachi'];
        $id = $this->as($a)->postJson('/addresses', $addr)->assertCreated()->json('data.id');

        $this->as($b)->patchJson("/addresses/$id", ['city' => 'Lahore'])->assertStatus(403);
        $this->as($b)->deleteJson("/addresses/$id")->assertStatus(403);
        $this->as($b)->getJson('/addresses')->assertOk()->assertJsonCount(0, 'data');
        $this->as($a)->getJson('/addresses')->assertOk()->assertJsonCount(1, 'data');
    }
}
