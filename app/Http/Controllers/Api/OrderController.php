<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** Customer-facing: a customer can only ever reach orders whose user_id is their own. */
class OrderController extends Controller
{
    public function index(Request $request)
    {
        return OrderResource::collection(
            $request->user()->orders()->with(['items'])->latest('id')->paginate(10)
        );
    }

    public function show(Request $request, string $reference)
    {
        return new OrderResource($this->own($request, $reference));
    }

    public function cancel(Request $request, string $reference, OrderService $orders)
    {
        $order = $this->own($request, $reference);
        Gate::authorize('cancel', $order);
        $updated = $orders->changeStatus($order, \App\Enums\OrderStatus::Cancelled, $request->user(), 'Cancelled by customer');
        return new OrderResource($updated);
    }

    private function own(Request $request, string $reference): Order
    {
        // Scoped query: another customer's order is indistinguishable from a non-existent one (404).
        return $request->user()->orders()
            ->where('reference', strtoupper($reference))
            ->with(['items', 'histories', 'shipment'])->firstOrFail();
    }
}
