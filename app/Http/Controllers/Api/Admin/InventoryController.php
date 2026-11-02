<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryController extends Controller
{
    public function stock()
    {
        $products = Product::query()
            ->where(fn ($query) => $query->where('track_inventory', true)
                ->orWhereHas('variants', fn ($variants) => $variants->where('track_inventory', true)))
            ->with(['category:id,name', 'variants'])
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $products->map(fn ($product) => [
            'id' => $product->id,
            'name' => $product->name,
            'sku' => $product->sku,
            'stock_quantity' => $product->stock_quantity,
            'low_stock' => $product->stock_quantity !== null && $product->stock_quantity <= 5,
            'category' => $product->category?->name,
            'variants' => $product->variants->where('track_inventory', true)->values()->map(fn ($variant) => [
                'id' => $variant->id,
                'name' => $variant->name,
                'sku' => $variant->sku,
                'stock_quantity' => $variant->stock_quantity,
                'low_stock' => $variant->stock_quantity !== null && $variant->stock_quantity <= 5,
            ]),
        ])]);
    }

    public function index(Request $request)
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = InventoryMovement::with(['product:id,name,sku', 'variant:id,name,sku', 'user:id,name'])->latest();

        if (! empty($validated['search'])) {
            $search = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $validated['search']).'%';
            $query->whereHas('product', fn ($product) => $product
                ->where('name', 'like', $search)
                ->orWhere('sku', 'like', $search));
        }

        return response()->json(['data' => $query->paginate($validated['per_page'] ?? 25)]);
    }

    public function adjust(Request $request, Product $product)
    {
        $validated = $request->validate([
            'variant_id' => ['nullable', 'integer'],
            'quantity_change' => ['required', 'integer', 'between:-100000,100000'],
            'note' => ['required', 'string', 'max:500'],
        ]);

        abort_if((int) $validated['quantity_change'] === 0, 422, 'Quantity change cannot be zero.');

        $movement = DB::transaction(function () use ($request, $product, $validated) {
            $sellable = ! empty($validated['variant_id'])
                ? ProductVariant::where('product_id', $product->id)->lockForUpdate()->findOrFail($validated['variant_id'])
                : Product::lockForUpdate()->findOrFail($product->id);

            abort_unless($sellable->track_inventory, 422, 'Inventory tracking is disabled for this item.');

            $nextQuantity = (int) $sellable->stock_quantity + (int) $validated['quantity_change'];
            if ($nextQuantity < 0) {
                throw ValidationException::withMessages([
                    'quantity_change' => ['Stock cannot become negative.'],
                ]);
            }

            $sellable->stock_quantity = $nextQuantity;
            $sellable->save();

            return InventoryMovement::create([
                'product_id' => $product->id,
                'variant_id' => $sellable instanceof ProductVariant ? $sellable->id : null,
                'user_id' => $request->user()->id,
                'type' => $validated['quantity_change'] > 0 ? 'adjustment_in' : 'adjustment_out',
                'quantity_change' => $validated['quantity_change'],
                'quantity_after' => $nextQuantity,
                'note' => strip_tags($validated['note']),
            ]);
        });

        AuditLogger::record($request, 'inventory.adjusted', $product, null, [
            'movement_id' => $movement->id,
            'quantity_change' => $validated['quantity_change'],
        ]);

        return response()->json(['data' => $movement->load(['product', 'variant', 'user'])], 201);
    }
}
