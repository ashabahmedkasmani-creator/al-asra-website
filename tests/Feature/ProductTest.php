<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\OrderItem;
use App\Models\Product;
use Tests\TestCase;

class ProductTest extends TestCase
{
    private function body(array $o = []): array
    {
        return array_merge(['name' => 'Steel Bottle', 'sku' => 'BOT-001', 'price' => 1500, 'short_description' => 'Keeps drinks cold'], $o);
    }

    public function test_staff_can_create_edit_publish_unpublish_and_delete(): void
    {
        $staff = $this->makeUser('staff');
        $id = $this->as($staff)->postJson('/admin/products', $this->body())->assertCreated()
            ->assertJsonPath('data.slug', 'steel-bottle')->assertJsonPath('data.is_published', false)->json('data.id');

        $this->getJson('/products/steel-bottle')->assertStatus(404); // draft is invisible
        $this->as($staff)->postJson("/admin/products/$id/publish")->assertOk()->assertJsonPath('data.is_published', true);
        $this->asGuest()->getJson('/products/steel-bottle')->assertOk()->assertJsonPath('data.name', 'Steel Bottle');

        $this->as($staff)->patchJson("/admin/products/$id", ['price' => 1800, 'sale_price' => 1600])->assertOk();
        $sale = $this->asGuest()->getJson('/products/steel-bottle')->assertJsonPath('data.on_sale', true);
        $this->assertEquals(1600, $sale->json('data.effective_price'));

        $this->as($staff)->postJson("/admin/products/$id/unpublish")->assertOk();
        $this->asGuest()->getJson('/products/steel-bottle')->assertStatus(404);

        $this->as($staff)->deleteJson("/admin/products/$id")->assertNoContent();
        $this->assertSoftDeleted('products', ['id' => $id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'product.deleted']);
    }

    public function test_product_validation(): void
    {
        $staff = $this->makeUser('staff');
        $this->makeProduct(['sku' => 'DUP-1']);
        $bad = [
            ['name' => ''], ['price' => -5], ['price' => 0], ['price' => 'abc'], ['price' => 10.123],
            ['sku' => 'DUP-1'], ['sku' => 'bad sku!'], ['sale_price' => 1500], ['sale_price' => 2000],
            ['availability' => 'maybe'], ['category_id' => 9999], ['specifications' => 'not-an-array'],
        ];
        foreach ($bad as $o) {
            $this->as($staff)->postJson('/admin/products', $this->body($o))->assertStatus(422);
        }
        $this->assertDatabaseCount('products', 1);
    }

    public function test_html_is_stripped_and_slugs_are_unique(): void
    {
        $staff = $this->makeUser('staff');
        $r = $this->as($staff)->postJson('/admin/products', $this->body(['name' => '<b>Mug</b>', 'description' => '<script>x()</script>Nice mug']))->assertCreated();
        $this->assertSame('Mug', $r->json('data.name'));
        $this->assertStringNotContainsString('<', $r->json('data.description'));

        $this->as($staff)->postJson('/admin/products', $this->body(['name' => 'Mug', 'sku' => 'MUG-2']))->assertCreated()->assertJsonPath('data.slug', 'mug-2');
    }

    public function test_mass_assignment_cannot_publish_through_create(): void
    {
        $r = $this->as($this->makeUser('staff'))->postJson('/admin/products', $this->body(['is_published' => true, 'slug' => 'hacked']))->assertCreated();
        $r->assertJsonPath('data.is_published', false)->assertJsonPath('data.slug', 'steel-bottle');
    }

    public function test_public_listing_filters_sorting_and_pagination(): void
    {
        $kitchen = Category::create(['name' => 'Kitchen', 'slug' => 'kitchen']);
        $sub = Category::create(['name' => 'Blenders', 'slug' => 'blenders', 'parent_id' => $kitchen->id]);
        $drink = Category::create(['name' => 'Drinkware', 'slug' => 'drinkware']);
        $this->makeProduct(['name' => 'Alpha Blender', 'price' => 3000, 'category_id' => $sub->id, 'is_featured' => true]);
        $this->makeProduct(['name' => 'Beta Frother', 'price' => 1000, 'category_id' => $kitchen->id]);
        $this->makeProduct(['name' => 'Gamma Cup', 'price' => 500, 'sale_price' => 400, 'category_id' => $drink->id]);
        $this->makeProduct(['name' => 'Hidden Draft', 'price' => 1, 'publish' => false]);
        $this->makeProduct(['name' => 'Gone', 'price' => 9000, 'availability' => 'unavailable']);

        $names = fn ($q) => collect($this->getJson("/products?$q")->assertOk()->json('data'))->pluck('name')->all();

        $this->assertNotContains('Hidden Draft', $names(''));
        $this->assertEqualsCanonicalizing(['Alpha Blender', 'Beta Frother'], $names('category=kitchen')); // includes subcategory
        $this->assertSame(['Gamma Cup'], $names('category=drinkware'));
        $this->assertSame(['Gamma Cup', 'Beta Frother', 'Alpha Blender', 'Gone'], $names('sort=price_asc&per_page=10')); // sorts by effective price
        $this->assertSame(['Alpha Blender'], $names('featured=1'));
        $this->assertSame(['Gamma Cup'], $names('max_price=450'));
        $this->assertEqualsCanonicalizing(['Alpha Blender'], $names('min_price=2000&max_price=5000'));
        $this->assertSame(['Beta Frother'], $names('search=frother'));
        $this->assertSame(['Gone'], $names('availability=unavailable'));

        $page = $this->getJson('/products?per_page=2&page=2')->assertOk();
        $page->assertJsonStructure(['data', 'links', 'meta' => ['current_page', 'last_page', 'per_page', 'total']]);
        $this->assertSame(2, $page->json('meta.per_page'));
        $this->getJson('/products?per_page=1000')->assertStatus(422);
        $this->getJson('/products?sort=drop_table')->assertStatus(422);
    }

    public function test_search_treats_like_wildcards_literally_and_resists_injection(): void
    {
        $this->makeProduct(['name' => 'Plain Product']);
        $this->makeProduct(['name' => '100% Cotton']);
        $this->assertCount(1, $this->getJson('/products?search='.urlencode('%'))->json('data'));
        $this->assertCount(0, $this->getJson('/products?search='.urlencode("' OR 1=1 --"))->json('data'));
        $this->assertDatabaseCount('products', 2);
    }

    public function test_public_api_hides_stock_counts_and_draft_state(): void
    {
        $this->makeProduct(['track_inventory' => true, 'stock_quantity' => 7]);
        $row = $this->getJson('/products')->json('data.0');
        foreach (['stock_quantity', 'track_inventory', 'is_published', 'updated_at'] as $k) {
            $this->assertArrayNotHasKey($k, $row);
        }
        $this->assertArrayHasKey('availability_label', $row);
    }

    public function test_availability_states_are_exposed_honestly(): void
    {
        $p = $this->makeProduct(['availability' => 'supplier_confirmation_required']);
        $this->getJson("/products/{$p->slug}")->assertJsonPath('data.is_purchasable', true)
            ->assertJsonPath('data.availability', 'supplier_confirmation_required');
        $q = $this->makeProduct(['availability' => 'discontinued']);
        $this->getJson("/products/{$q->slug}")->assertJsonPath('data.is_purchasable', false);
    }

    public function test_deleting_a_product_keeps_historic_orders_intact(): void
    {
        $p = $this->makeProduct(['name' => 'Short Lived']);
        $this->placeOrder([$this->line($p)]);
        $this->as($this->makeUser('admin'))->deleteJson("/admin/products/{$p->id}")->assertNoContent();
        $this->assertSame('Short Lived', OrderItem::first()->product_name);
        $this->assertNotNull(Product::withTrashed()->find($p->id));
    }

    public function test_category_management_rules(): void
    {
        $staff = $this->makeUser('staff');
        $parent = $this->as($staff)->postJson('/admin/categories', ['name' => 'Home'])->assertCreated()->json('data.id');
        $child = $this->as($staff)->postJson('/admin/categories', ['name' => 'Decor', 'parent_id' => $parent])->assertCreated()->json('data.id');

        $this->as($staff)->patchJson("/admin/categories/$parent", ['parent_id' => $child])->assertStatus(422); // cycle
        $this->as($staff)->deleteJson("/admin/categories/$parent")->assertStatus(422);                         // has children
        $this->makeProduct(['category_id' => $child]);
        $this->as($staff)->deleteJson("/admin/categories/$child")->assertStatus(422);                          // has products
        $this->getJson('/categories')->assertOk()->assertJsonPath('data.0.children.0.name', 'Decor');
    }

    public function test_variants_require_a_choice_and_are_priced_server_side(): void
    {
        $staff = $this->makeUser('staff');
        $p = $this->makeProduct(['price' => 1000]);
        $v = $this->as($staff)->postJson("/admin/products/{$p->id}/variants", ['name' => 'Large', 'sku' => 'VAR-L', 'price' => 1400])->assertCreated()->json('data.id');

        $this->asGuest()->postJson('/checkout/quote', ['items' => [$this->line($p)]])->assertStatus(422); // must choose an option
        $r = $this->postJson('/checkout/quote', ['items' => [['product_id' => $p->id, 'variant_id' => $v, 'quantity' => 2]]])->assertOk();
        $this->assertEquals(2800, $r->json('data.subtotal'));

        $other = $this->makeProduct();
        $this->postJson('/checkout/quote', ['items' => [['product_id' => $other->id, 'variant_id' => $v, 'quantity' => 1]]])->assertStatus(422);
    }
}
