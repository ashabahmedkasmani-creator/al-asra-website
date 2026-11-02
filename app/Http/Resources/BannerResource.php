<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class BannerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $staff = (bool) $request->user('sanctum')?->isStaffOrAdmin();
        return [
            'id' => $this->id,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'image_url' => $this->image_path ? Storage::disk('public')->url($this->image_path) : null,
            'link_url' => $this->link_url,
            'button_text' => $this->button_text,
            'placement' => $this->placement,
            'sort_order' => $this->sort_order,
            'is_active' => $this->when($staff, $this->is_active),
            'starts_at' => $this->when($staff, $this->starts_at?->toIso8601String()),
            'ends_at' => $this->when($staff, $this->ends_at?->toIso8601String()),
        ];
    }
}
