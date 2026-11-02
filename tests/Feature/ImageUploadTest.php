<?php

namespace Tests\Feature;

use App\Models\ProductImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImageUploadTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function upload($user, $product, UploadedFile $file, string $url = null)
    {
        return $this->as($user)->post($url ?? "/admin/products/{$product->id}/images", ['image' => $file], ['Accept' => 'application/json']);
    }

    public function test_valid_image_is_reencoded_and_stored_with_a_random_name(): void
    {
        $staff = $this->makeUser('staff');
        $p = $this->makeProduct();
        $this->upload($staff, $p, UploadedFile::fake()->image('evil name.jpg', 600, 600))->assertCreated();

        $img = ProductImage::firstOrFail();
        $this->assertMatchesRegularExpression('#^products/[0-9a-f-]{36}\.(webp|png)$#', $img->path);
        $this->assertStringNotContainsString('evil', $img->path);
        Storage::disk('public')->assertExists($img->path);
        $this->assertTrue($img->is_primary);
    }

    public function test_executable_and_non_image_uploads_are_rejected(): void
    {
        $staff = $this->makeUser('staff');
        $p = $this->makeProduct();
        $cases = [
            UploadedFile::fake()->createWithContent('shell.php', '<?php system($_GET["c"]);'),
            UploadedFile::fake()->createWithContent('shell.jpg', '<?php system($_GET["c"]);'),           // PHP disguised as jpg
            UploadedFile::fake()->createWithContent('x.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
            UploadedFile::fake()->createWithContent('doc.pdf', '%PDF-1.4'),
            UploadedFile::fake()->create('big.jpg', 5000, 'image/jpeg'),                                   // > 3 MB
            UploadedFile::fake()->image('tiny.png', 50, 50),                                              // below minimum size
            UploadedFile::fake()->image('wide.png', 5001, 300),                                           // above maximum size
        ];
        foreach ($cases as $file) {
            $this->upload($staff, $p, $file)->assertStatus(422);
        }
        $this->assertDatabaseCount('product_images', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_customers_and_guests_cannot_upload(): void
    {
        $p = $this->makeProduct();
        $file = fn () => UploadedFile::fake()->image('a.jpg', 400, 400);
        $this->upload($this->makeUser('customer'), $p, $file())->assertStatus(403);
        $this->asGuest()->post("/admin/products/{$p->id}/images", ['image' => $file()], ['Accept' => 'application/json'])->assertStatus(401);
        $this->assertDatabaseCount('product_images', 0);
    }

    public function test_replace_and_remove_images_safely(): void
    {
        $staff = $this->makeUser('staff');
        $p = $this->makeProduct();
        $this->upload($staff, $p, UploadedFile::fake()->image('a.jpg', 400, 400))->assertCreated();
        $img = ProductImage::first();
        $old = $img->path;

        $this->upload($staff, $p, UploadedFile::fake()->image('b.png', 500, 500), "/admin/products/{$p->id}/images/{$img->id}/replace")->assertOk();
        $img->refresh();
        $this->assertNotSame($old, $img->path);
        Storage::disk('public')->assertMissing($old);
        Storage::disk('public')->assertExists($img->path);

        $this->as($staff)->deleteJson("/admin/products/{$p->id}/images/{$img->id}")->assertNoContent();
        Storage::disk('public')->assertMissing($img->path);
        $this->assertDatabaseCount('product_images', 0);
    }

    public function test_images_cannot_be_modified_through_another_product(): void
    {
        $staff = $this->makeUser('staff');
        $a = $this->makeProduct();
        $b = $this->makeProduct();
        $this->upload($staff, $a, UploadedFile::fake()->image('a.jpg', 400, 400))->assertCreated();
        $img = ProductImage::first();

        $this->as($staff)->deleteJson("/admin/products/{$b->id}/images/{$img->id}")->assertStatus(404);
        $this->as($staff)->patchJson("/admin/products/{$b->id}/images/{$img->id}", ['alt_text' => 'x'])->assertStatus(404);
        $this->assertDatabaseCount('product_images', 1);
    }

    public function test_primary_image_switching_and_limit(): void
    {
        $staff = $this->makeUser('staff');
        $p = $this->makeProduct();
        $this->upload($staff, $p, UploadedFile::fake()->image('a.jpg', 400, 400))->assertCreated();
        $this->upload($staff, $p, UploadedFile::fake()->image('b.jpg', 400, 400))->assertCreated();
        $second = ProductImage::orderByDesc('id')->first();
        $this->as($staff)->patchJson("/admin/products/{$p->id}/images/{$second->id}", ['is_primary' => true])->assertOk();
        $this->assertSame(1, ProductImage::where('is_primary', true)->count());
        $this->assertTrue($second->fresh()->is_primary);
    }
}
