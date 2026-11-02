<?php

namespace App\Policies;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function view(User $user, Order $order): bool
    {
        return $order->user_id !== null && $order->user_id === $user->id;
    }

    /** Customers may cancel only their own orders, and only before processing starts. */
    public function cancel(User $user, Order $order): bool
    {
        return $this->view($user, $order)
            && in_array($order->status, [OrderStatus::Pending, OrderStatus::AwaitingSupplier], true);
    }
}
