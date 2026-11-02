<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Public tracking view: deliberately omits phone, email, street address and internal notes. */
class TrackingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'reference' => $this->reference,
            'status' => $this->status->value,
            'payment_method' => $this->payment_method,
            'payment_status' => $this->payment_status->value,
            'city' => $this->city,
            'subtotal' => (float) $this->subtotal,
            'discount_total' => (float) $this->discount_total,
            'shipping_fee' => (float) $this->shipping_fee,
            'grand_total' => (float) $this->grand_total,
            'currency' => $this->currency,
            'items' => $this->items->map(fn ($i) => [
                'product_name' => $i->product_name, 'variant_name' => $i->variant_name,
                'quantity' => $i->quantity, 'line_total' => (float) $i->line_total,
            ]),
            'history' => $this->histories->map(fn ($h) => ['status' => $h->to_status, 'at' => $h->created_at?->toIso8601String()]),
            'shipment' => $this->shipment ? ['courier' => $this->shipment->courier, 'tracking_number' => $this->shipment->tracking_number] : null,
            'placed_at' => $this->placed_at?->toIso8601String(),
        ];
    }
}
