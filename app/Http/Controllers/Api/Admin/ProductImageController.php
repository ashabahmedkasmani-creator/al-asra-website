<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\AuditLogger;
use App\Services\ImageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductImageController extends Controller
{
    private const FILE_RULES = ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:3072', 'dimensions:min_width=200,min_height=200,max_width=5000,max_height=5000'];

    public function __construct(private ImageService $images) {}

    public function store(Request $request, Product $product)
    {
        $data = $request->validate([
            'image' => self::FILE_RULES, 'alt_text' => ['nullable', 'string', 'max:200'], 'is_primary' => ['sometimes', 'boolean'],
        ]);
        abort_if($product->images()->count() >= 10, 422, 'A product can have at most 10 images.');

        $path = $this->images->store($request->file('image'), 'products');
        $image = DB::transaction(function () use ($product, $data, $path) {
            $first = ! $product->images()->exists();
            $primary = $first || ! empty($data['is_primary']);
            if ($primary) {
                $product->images()->update(['is_primary' => false]);
            }
            return $product->images()->create([
                'path' => $path, 'alt_text' => isset($data['alt_text']) ? strip_tags($data['alt_text']) : null,
                'sort_order' => (int) $product->images()->max('sort_order') + 1, 'is_primary' => $primary,
            ]);
        });
        AuditLogger::record($request, 'product.image_added', $product, null, ['image_id' => $image->id]);
        return response()->json(['data' => ['id' => $image->id, 'url' => $image->url, 'is_primary' => $image->is_primary]], 201);
    }

    public function update(Request $request, Product $product, ProductImage $image)
    {
        abort_unless($image->product_id === $product->id, 404);
        $data = $request->validate([
            'alt_text' => ['nullable', 'string', 'max:200'], 'sort_order' => ['sometimes', 'integer', 'min:0', 'max:1000'],
            'is_primary' => ['sometimes', 'boolean'],
        ]);
        DB::transaction(function () use ($product, $image, $data) {
            if (! empty($data['is_primary'])) {
                $product->images()->where('id', '!=', $image->id)->update(['is_primary' => false]);
            }
            if (isset($data['alt_text'])) $data['alt_text'] = strip_tags($data['alt_text']);
            $image->update($data);
        });
        return response()->json(['data' => $image->fresh()->only(['id', 'alt_text', 'sort_order', 'is_primary'])]);
    }

    /** Replace the file but keep the record; the old file is deleted only after the new one is saved. */
    public function replace(Request $request, Product $product, ProductImage $image)
    {
        abort_unless($image->product_id === $product->id, 404);
        $request->validate(['image' => self::FILE_RULES]);
        $newPath = $this->images->store($request->file('image'), 'products');
        $old = $image->path;
        $image->update(['path' => $newPath]);
        $this->images->delete($old);
        AuditLogger::record($request, 'product.image_replaced', $product, null, ['image_id' => $image->id]);
        return response()->json(['data' => ['id' => $image->id, 'url' => $image->fresh()->url]]);
    }

    public function destroy(Request $request, Product $product, ProductImage $image)
    {
        abort_unless($image->product_id === $product->id, 404);
        $path = $image->path;
        $wasPrimary = $image->is_primary;
        $image->delete();
        $this->images->delete($path);
        if ($wasPrimary && ($next = $product->images()->first())) {
            $next->update(['is_primary' => true]);
        }
        AuditLogger::record($request, 'product.image_removed', $product, ['image_id' => $image->id]);
        return response()->noContent();
    }
}
