<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** Owner / just-placed view. Admin view adds fields in AdminOrderResource. */
class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'reference' => $this->reference,
            'status' => $this->status->value,
            'payment_method' => $this->payment_method,
            'payment_status' => $this->payment_status->value,
            'customer_name' => $this->customer_name,
            'customer_phone' => $this->customer_phone,
            'customer_email' => $this->customer_email,
            'address' => [
                'address_line' => $this->address_line, 'city' => $this->city,
                'province' => $this->province, 'postal_code' => $this->postal_code,
            ],
            'notes' => $this->notes,
            'subtotal' => (float) $this->subtotal,
            'discount_total' => (float) $this->discount_total,
            'shipping_fee' => (float) $this->shipping_fee,
            'grand_total' => (float) $this->grand_total,
            'currency' => $this->currency,
            'coupon_code' => $this->coupon_code,
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($i) => [
                'product_name' => $i->product_name, 'variant_name' => $i->variant_name, 'sku' => $i->sku,
                'unit_price' => (float) $i->unit_price, 'quantity' => $i->quantity, 'line_total' => (float) $i->line_total,
                'image_url' => $i->image_path ? Storage::disk('public')->url($i->image_path) : null,
                'availability_at_order' => $i->availability_at_order,
            ])),
            'history' => $this->whenLoaded('histories', fn () => $this->histories->map(fn ($h) => [
                'status' => $h->to_status, 'at' => $h->created_at?->toIso8601String(),
            ])),
            'shipment' => $this->whenLoaded('shipment', fn () => $this->shipment ? [
                'courier' => $this->shipment->courier, 'tracking_number' => $this->shipment->tracking_number,
            ] : null),
            'placed_at' => $this->placed_at?->toIso8601String(),
        ];
    }
}
