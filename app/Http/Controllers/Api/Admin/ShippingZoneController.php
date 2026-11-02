<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ShippingZone;
use App\Services\AuditLogger;
use Illuminate\Http\Request;

class ShippingZoneController extends Controller
{
    public function index()
    {
        return response()->json(['data' => ShippingZone::orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        $zone = ShippingZone::create($this->validated($request));
        AuditLogger::record($request, 'shipping_zone.created', $zone);

        return response()->json(['data' => $zone], 201);
    }

    public function update(Request $request, ShippingZone $shippingZone)
    {
        $old = $shippingZone->getOriginal();
        $shippingZone->update($this->validated($request));
        AuditLogger::record($request, 'shipping_zone.updated', $shippingZone, $old, $shippingZone->getAttributes());

        return response()->json(['data' => $shippingZone->fresh()]);
    }

    public function destroy(Request $request, ShippingZone $shippingZone)
    {
        AuditLogger::record($request, 'shipping_zone.deleted', $shippingZone);
        $shippingZone->delete();

        return response()->noContent();
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'city' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'postal_codes' => ['nullable', 'array'],
            'postal_codes.*' => ['string', 'max:10'],
            'shipping_fee' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'free_shipping_threshold' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
            'estimated_min_days' => ['required', 'integer', 'min:0', 'max:60'],
            'estimated_max_days' => ['required', 'integer', 'gte:estimated_min_days', 'max:90'],
            'is_active' => ['required', 'boolean'],
        ]);
    }
}
