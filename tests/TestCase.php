<?php

namespace Tests;

use App\Enums\Role;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\PaymentMethodSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;
    use CreatesApplication {
        createApplication as protected baseCreateApplication;
    }

    /** Safety net: refuse to run unless the isolated in-memory SQLite test database is configured. */
    public function createApplication(): \Illuminate\Foundation\Application
    {
        $app = $this->baseCreateApplication();
        $default = $app['config']->get('database.default');
        $db = $app['config']->get("database.connections.$default.database");
        if ($default !== 'sqlite' || $db !== ':memory:') {
            throw new \RuntimeException("Refusing to run tests against '$default' / '$db'. Tests must use sqlite :memory: (see phpunit.xml).");
        }
        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PaymentMethodSeeder::class);
    }

    protected function makeUser(string $role = 'customer', array $over = []): User
    {
        static $n = 0;
        $n++;
        $u = new User(array_merge([
            'name' => "Test User $n", 'email' => "user{$n}@example.test", 'phone' => '03001234567', 'password' => 'Str0ngPassw0rd!',
        ], $over));
        $u->role = Role::from($role);
        $u->is_active = true;
        $u->save();
        return $u;
    }

    /** Authenticate subsequent requests as $user with a real Sanctum token. */
    protected function as(User $user): static
    {
        $this->app['auth']->forgetGuards(); // Sanctum caches the resolved user per guard
        $this->flushHeaders();
        return $this->withToken($user->createToken('test')->plainTextToken);
    }

    protected function asGuest(): static
    {
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();
        return $this;
    }

    protected function makeProduct(array $o = []): Product
    {
        static $n = 0;
        $n++;
        $publish = $o['publish'] ?? true;
        unset($o['publish']);
        $p = Product::create(array_merge([
            'name' => "Test Product $n", 'slug' => "test-product-$n", 'sku' => "SKU-$n", 'price' => 1000, 'availability' => 'available',
        ], $o));
        if ($publish) {
            $p->publish();
        }
        return $p->fresh();
    }

    protected function payload(array $items, array $over = []): array
    {
        return array_merge([
            'customer_name' => 'Ali Khan', 'customer_phone' => '0300 1234567',
            'address_line' => 'House 12, Street 4, Gulshan', 'city' => 'Karachi', 'province' => 'Sindh',
            'payment_method' => 'cod', 'items' => $items,
        ], $over);
    }

    protected function line(Product $p, int $qty = 1): array
    {
        return ['product_id' => $p->id, 'quantity' => $qty];
    }

    /** Place an order through the real checkout endpoint. Returns the order reference. */
    protected function placeOrder(array $items, array $over = [], ?User $as = null): string
    {
        $client = $as ? $this->as($as) : $this->asGuest();
        $res = $client->postJson('/checkout', $this->payload($items, $over));
        $res->assertCreated();
        return $res->json('data.reference');
    }
}
