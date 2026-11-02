<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Services\AuditLogger;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $v = $request->validate([
            'search' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', 'in:published,draft'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $q = Product::query()->with(['category', 'images'])->latest('id');
        if (! empty($v['search'])) {
            $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $v['search']).'%';
            $q->where(fn ($w) => $w->whereRaw("name LIKE ? ESCAPE '!'", [$like])->orWhereRaw("sku LIKE ? ESCAPE '!'", [$like]));
        }
        if (($v['status'] ?? null) === 'published') $q->where('is_published', true);
        if (($v['status'] ?? null) === 'draft') $q->where('is_published', false);
        return ProductResource::collection($q->paginate($v['per_page'] ?? 20)->withQueryString());
    }

    public function show(Product $product)
    {
        return new ProductResource($product->load(['category', 'images', 'variants']));
    }

    public function store(ProductRequest $request)
    {
        $data = $request->validated();
        $data['slug'] = Product::uniqueSlug($data['name']);
        $product = Product::create($data)->refresh();
        AuditLogger::record($request, 'product.created', $product, null, $product->only(['name', 'sku', 'price']));
        return (new ProductResource($product->load(['category', 'images'])))->response()->setStatusCode(201);
    }

    public function update(ProductRequest $request, Product $product)
    {
        $old = $product->only(['name', 'sku', 'price', 'sale_price', 'availability', 'category_id']);
        $data = $request->validated();
        if (isset($data['name']) && $data['name'] !== $product->name) {
            $data['slug'] = Product::uniqueSlug($data['name'], $product->id);
        }
        $product->update($data);
        AuditLogger::record($request, 'product.updated', $product, $old, $product->only(array_keys($old)));
        return new ProductResource($product->fresh(['category', 'images', 'variants']));
    }

    public function destroy(Request $request, Product $product)
    {
        AuditLogger::record($request, 'product.deleted', $product, $product->only(['name', 'sku']));
        $product->delete(); // soft delete: historic order items keep their snapshot
        return response()->noContent();
    }

    public function publish(Request $request, Product $product)
    {
        $product->publish();
        AuditLogger::record($request, 'product.published', $product);
        return new ProductResource($product->fresh(['category', 'images']));
    }

    public function unpublish(Request $request, Product $product)
    {
        $product->unpublish();
        AuditLogger::record($request, 'product.unpublished', $product);
        return new ProductResource($product->fresh(['category', 'images']));
    }
}
