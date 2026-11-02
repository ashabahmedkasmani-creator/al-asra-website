<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Validation\Rule;

class CouponController extends Controller
{
    public function index()
    {
        return JsonResource::collection(Coupon::latest('id')->paginate(20));
    }

    public function store(Request $request): JsonResponse
    {
        $coupon = Coupon::create($this->validated($request, null))->refresh();
        AuditLogger::record($request, 'coupon.created', $coupon, null, $coupon->only(['code', 'type', 'value']));
        return response()->json(['data' => $coupon], 201);
    }

    public function update(Request $request, Coupon $coupon): JsonResponse
    {
        $old = $coupon->only(['code', 'type', 'value', 'min_subtotal', 'is_active', 'usage_limit']);
        $coupon->update($this->validated($request, $coupon));
        AuditLogger::record($request, 'coupon.updated', $coupon, $old, $coupon->only(array_keys($old)));
        return response()->json(['data' => $coupon->fresh()]);
    }

    public function destroy(Request $request, Coupon $coupon)
    {
        AuditLogger::record($request, 'coupon.deleted', $coupon, $coupon->only(['code']));
        $coupon->delete();
        return response()->noContent();
    }

    private function validated(Request $request, ?Coupon $current): array
    {
        $req = $current ? 'sometimes' : 'required';
        if (is_string($request->code)) {
            $request->merge(['code' => strtoupper(trim($request->code))]);
        }
        $data = $request->validate([
            'code' => [$req, 'string', 'min:3', 'max:40', 'regex:/^[A-Z0-9_-]+$/', Rule::unique('coupons', 'code')->ignore($current?->id)],
            'description' => ['nullable', 'string', 'max:255'],
            'type' => [$req, Rule::in(['percent', 'fixed'])],
            'value' => [$req, 'numeric', 'min:0.01', 'max:10000000'],
            'min_subtotal' => ['sometimes', 'numeric', 'min:0'],
            'max_discount' => ['nullable', 'numeric', 'min:0.01'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'per_customer_limit' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after:starts_at'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $type = $data['type'] ?? $current?->type;
        $value = $data['value'] ?? $current?->value;
        if ($type === 'percent' && (float) $value > 100) {
            abort(422, 'A percentage discount cannot exceed 100.');
        }
        return $data;
    }
}
