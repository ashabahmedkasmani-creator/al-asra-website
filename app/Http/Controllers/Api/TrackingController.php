<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TrackingResource;
use App\Models\Order;
use App\Support\Phone;
use Illuminate\Http\Request;

class TrackingController extends Controller
{
    /** Needs BOTH reference and phone. Every failure returns the same generic 404. */
    public function track(Request $request)
    {
        $request->validate([
            'reference' => ['required', 'string', 'max:20'],
            'phone' => ['required', 'string', 'max:20'],
        ]);

        $reference = strtoupper(trim($request->input('reference')));
        $phone = Phone::normalize($request->input('phone'));

        $order = null;
        if (preg_match('/^ASR-[A-Z0-9]{10}$/', $reference) && Phone::isValidMobile($phone)) {
            $order = Order::where('reference', $reference)->where('customer_phone', $phone)
                ->with(['items', 'histories', 'shipment'])->first();
        }

        if (! $order) {
            return response()->json(['message' => 'We could not find an order matching those details.'], 404);
        }
        return new TrackingResource($order);
    }
}
