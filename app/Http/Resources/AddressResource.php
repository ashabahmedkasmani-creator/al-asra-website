<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AddressResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return $this->only(['id', 'label', 'recipient_name', 'phone', 'address_line', 'city', 'province', 'postal_code', 'is_default']);
    }
}
