<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\Availability;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductVariantController extends Controller
{
    public function store(Request $request, Product $product)
    {
        $data = $this->validated($request, null, true);
        $variant = $product->variants()->create($data);
        AuditLogger::record($request, 'variant.created', $variant, null, $variant->only(['name', 'sku', 'price']));
        return response()->json(['data' => $variant], 201);
    }

    public function update(Request $request, Product $product, ProductVariant $variant)
    {
        abort_unless($variant->product_id === $product->id, 404);
        $old = $variant->only(['name', 'sku', 'price', 'sale_price', 'availability']);
        $variant->update($this->validated($request, $variant->id, false));
        AuditLogger::record($request, 'variant.updated', $variant, $old, $variant->only(array_keys($old)));
        return response()->json(['data' => $variant->fresh()]);
    }

    public function destroy(Request $request, Product $product, ProductVariant $variant)
    {
        abort_unless($variant->product_id === $product->id, 404);
        AuditLogger::record($request, 'variant.deleted', $variant, $variant->only(['name', 'sku']));
        $variant->delete();
        return response()->noContent();
    }

    private function validated(Request $request, ?int $ignoreId, bool $creating): array
    {
        $req = $creating ? 'required' : 'sometimes';
        $data = $request->validate([
            'name' => [$req, 'string', 'max:120'],
            'sku' => [$req, 'string', 'max:64', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('product_variants', 'sku')->ignore($ignoreId)],
            'price' => [$req, 'numeric', 'min:0.01', 'max:10000000', 'decimal:0,2'],
            'sale_price' => ['nullable', 'numeric', 'min:0.01', 'decimal:0,2', 'lt:price'],
            'availability' => ['sometimes', Rule::enum(Availability::class)],
            'track_inventory' => ['sometimes', 'boolean'],
            'stock_quantity' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        if (isset($data['name'])) $data['name'] = strip_tags($data['name']);
        return $data;
    }
}
