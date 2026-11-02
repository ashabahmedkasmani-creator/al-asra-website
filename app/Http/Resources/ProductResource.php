<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $staff = (bool) $request->user('sanctum')?->isStaffOrAdmin();
        $effective = (float) $this->effective_price;
        $variants = $this->whenLoaded('variants');

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'sku' => $this->sku,
            'short_description' => $this->short_description,
            'description' => $this->description,
            'specifications' => $this->specifications,
            'price' => (float) $this->price,
            'sale_price' => $this->sale_price !== null ? (float) $this->sale_price : null,
            'effective_price' => $effective,
            'on_sale' => $effective < (float) $this->price,
            'currency' => 'PKR',
            'availability' => $this->availability->value,
            'availability_label' => $this->availability->label(),
            'is_purchasable' => $this->availability->isPurchasable(),
            'is_featured' => $this->is_featured,
            'show_on_home' => $this->show_on_home,
            'category' => $this->whenLoaded('category', fn () => $this->category ? new CategoryResource($this->category) : null),
            'images' => $this->whenLoaded('images', fn () => $this->images->map(fn ($i) => [
                'id' => $i->id, 'url' => $i->url, 'alt_text' => $i->alt_text,
                'is_primary' => $i->is_primary, 'sort_order' => $i->sort_order,
            ])),
            'variants' => $this->when($this->relationLoaded('variants'), fn () => $this->variants->map(function ($v) use ($staff) {
                $row = [
                    'id' => $v->id, 'name' => $v->name, 'sku' => $v->sku,
                    'price' => (float) $v->price, 'effective_price' => (float) $v->effective_price,
                    'availability' => $v->availability->value, 'is_purchasable' => $v->availability->isPurchasable(),
                ];
                if ($staff) {
                    $row['is_active'] = $v->is_active;
                    $row['stock_quantity'] = $v->stock_quantity;
                }
                return $row;
            })),
            'reviews_avg' => $this->when(isset($this->resource->reviews_avg_rating), fn () => round((float) $this->resource->reviews_avg_rating, 1)),
            'reviews_count' => $this->whenCounted('reviews'),
            // Staff-only fields. The public API never exposes stock counts or draft state.
            'is_published' => $this->when($staff, $this->is_published),
            'track_inventory' => $this->when($staff, $this->track_inventory),
            'stock_quantity' => $this->when($staff, $this->stock_quantity),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->when($staff, $this->updated_at?->toIso8601String()),
        ];
    }
}
