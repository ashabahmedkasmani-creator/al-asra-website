<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class AdminOrderResource extends OrderResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'assigned_to' => $this->assigned_to,
            'assignee' => $this->whenLoaded('assignedTo', fn () => $this->assignedTo ? ['id' => $this->assignedTo->id, 'name' => $this->assignedTo->name] : null),
            'shipping_zone' => $this->whenLoaded('shippingZone', fn () => $this->shippingZone ? ['id' => $this->shippingZone->id, 'name' => $this->shippingZone->name] : null),
            'history' => $this->whenLoaded('histories', fn () => $this->histories->map(fn ($h) => [
                'from' => $h->from_status, 'to' => $h->to_status, 'note' => $h->note,
                'changed_by' => $h->changed_by, 'at' => $h->created_at?->toIso8601String(),
            ])),
            'payments' => $this->whenLoaded('payments', fn () => $this->payments->map(fn ($p) => [
                'id' => $p->id, 'method' => $p->method, 'status' => $p->status->value, 'amount' => (float) $p->amount,
                'provider_reference' => $p->provider_reference, 'verified_by' => $p->verified_by,
                'verified_at' => $p->verified_at?->toIso8601String(),
            ])),
        ]);
    }
}
