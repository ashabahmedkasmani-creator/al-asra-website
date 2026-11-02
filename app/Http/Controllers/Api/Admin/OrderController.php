<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\AdminOrderResource;
use App\Models\Order;
use App\Models\Shipment;
use App\Services\AuditLogger;
use App\Services\OrderService;
use App\Services\PaymentService;
use App\Support\Phone;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $v = $request->validate([
            'status' => ['nullable', Rule::enum(OrderStatus::class)], 'payment_status' => ['nullable', Rule::enum(PaymentStatus::class)],
            'search' => ['nullable', 'string', 'max:100'], 'from' => ['nullable', 'date'], 'to' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $q = Order::query()->with(['items', 'assignedTo', 'shippingZone'])->latest('id');
        if (! empty($v['status'])) $q->where('status', $v['status']);
        if (! empty($v['payment_status'])) $q->where('payment_status', $v['payment_status']);
        if (! empty($v['from'])) $q->whereDate('created_at', '>=', $v['from']);
        if (! empty($v['to'])) $q->whereDate('created_at', '<=', $v['to']);
        if (! empty($v['search'])) {
            $s = trim($v['search']);
            $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $s).'%';
            $phone = Phone::normalize($s);
            $q->where(fn ($w) => $w->where('reference', strtoupper($s))->orWhere('customer_phone', $phone)
                ->orWhereRaw("customer_name LIKE ? ESCAPE '!'", [$like]));
        }
        return AdminOrderResource::collection($q->paginate($v['per_page'] ?? 20)->withQueryString());
    }

    public function show(Order $order)
    {
        return new AdminOrderResource($order->load(['items', 'histories', 'payments', 'shipment', 'assignedTo', 'shippingZone']));
    }

    public function updateStatus(Request $request, Order $order, OrderService $orders)
    {
        $data = $request->validate(['status' => ['required', Rule::enum(OrderStatus::class)], 'note' => ['nullable', 'string', 'max:500']]);
        $from = $order->status->value;
        $updated = $orders->changeStatus($order, OrderStatus::from($data['status']), $request->user(), isset($data['note']) ? strip_tags($data['note']) : null);
        AuditLogger::record($request, 'order.status_changed', $order, ['status' => $from], ['status' => $data['status']]);
        return new AdminOrderResource($updated);
    }

    public function verifyPayment(Request $request, Order $order, PaymentService $payments)
    {
        $data = $request->validate(['reference' => ['nullable', 'string', 'max:120']]);
        $updated = $payments->markPaid($order, $request->user(), isset($data['reference']) ? strip_tags($data['reference']) : null);
        AuditLogger::record($request, 'order.payment_verified', $order, ['payment_status' => 'pending'], ['payment_status' => 'paid']);
        return new AdminOrderResource($updated->load(['items', 'histories', 'shipment', 'assignedTo', 'shippingZone']));
    }

    public function refundPayment(Request $request, Order $order, PaymentService $payments)
    {
        $updated = $payments->refund($order, $request->user());
        AuditLogger::record($request, 'order.payment_refunded', $order, ['payment_status' => 'paid'], ['payment_status' => 'refunded']);
        return new AdminOrderResource($updated->load(['items', 'histories', 'shipment', 'assignedTo', 'shippingZone']));
    }

    public function assign(Request $request, Order $order)
    {
        $data = $request->validate(['assigned_to' => ['present', 'nullable', 'integer', 'exists:users,id']]);
        if (! empty($data['assigned_to'])) {
            $assignee = \App\Models\User::findOrFail($data['assigned_to']);
            abort_unless($assignee->isStaffOrAdmin() && $assignee->is_active, 422, 'Assignee must be an active staff member or administrator.');
        }
        $old = $order->assigned_to;
        $order->assigned_to = $data['assigned_to'] ?? null;
        $order->save();
        AuditLogger::record($request, 'order.assigned', $order, ['assigned_to' => $old], ['assigned_to' => $order->assigned_to]);
        return new AdminOrderResource($order->fresh(['items', 'histories', 'payments', 'shipment', 'assignedTo']));
    }

    public function updateShipment(Request $request, Order $order)
    {
        $data = $request->validate(['courier' => ['nullable', 'string', 'max:80'], 'tracking_number' => ['nullable', 'string', 'max:80', 'regex:/^[A-Za-z0-9 -]+$/']]);
        $shipment = Shipment::firstOrNew(['order_id' => $order->id]);
        $shipment->fill(array_map(fn ($v) => is_string($v) ? strip_tags($v) : $v, $data))->save();
        AuditLogger::record($request, 'order.shipment_updated', $order, null, $data);
        return new AdminOrderResource($order->load(['items', 'histories', 'payments', 'shipment', 'assignedTo', 'shippingZone']));
    }
}
