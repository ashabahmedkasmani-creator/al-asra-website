<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\User;
use App\Notifications\PaymentUpdatedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Payment state is only ever changed here. Nothing is marked "paid" automatically.
 * Future gateways: add a verified webhook that calls markPaid() with the provider reference.
 */
class PaymentService
{
    public function markPaid(Order $order, User $verifier, ?string $providerReference): Order
    {
        return DB::transaction(function () use ($order, $verifier, $providerReference) {
            $o = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($o->payment_status !== PaymentStatus::Pending) {
                throw ValidationException::withMessages(['payment' => ['This payment is not awaiting verification.']]);
            }
            if (in_array($o->status, [OrderStatus::Cancelled, OrderStatus::Returned], true)) {
                throw ValidationException::withMessages(['payment' => ['Cannot mark a cancelled or returned order as paid.']]);
            }
            if ($o->payment_method === 'cod' && $o->status !== OrderStatus::Delivered) {
                throw ValidationException::withMessages(['payment' => ['Cash on delivery can only be marked paid after delivery.']]);
            }
            if ($o->payment_method === 'bank_transfer' && ! $providerReference) {
                throw ValidationException::withMessages(['reference' => ['A bank transfer reference is required to verify payment.']]);
            }

            $payment = $o->payments()->latest('id')->firstOrFail();
            $payment->forceFill([
                'status' => PaymentStatus::Paid, 'provider_reference' => $providerReference,
                'verified_by' => $verifier->id, 'verified_at' => now(),
            ])->save();
            $o->forceFill(['payment_status' => PaymentStatus::Paid])->save();
            if ($o->user) $o->user->notify(new PaymentUpdatedNotification($o->reference, PaymentStatus::Paid->value));

            return $o->load('payments');
        });
    }

    public function refund(Order $order, User $actor): Order
    {
        return DB::transaction(function () use ($order) {
            $o = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($o->payment_status !== PaymentStatus::Paid || ! in_array($o->status, [OrderStatus::Cancelled, OrderStatus::Returned], true)) {
                throw ValidationException::withMessages(['payment' => ['Only paid orders that are cancelled or returned can be refunded.']]);
            }
            $o->payments()->latest('id')->firstOrFail()->forceFill(['status' => PaymentStatus::Refunded])->save();
            $o->forceFill(['payment_status' => PaymentStatus::Refunded])->save();
            if ($o->user) $o->user->notify(new PaymentUpdatedNotification($o->reference, PaymentStatus::Refunded->value));
            return $o->load('payments');
        });
    }
}
