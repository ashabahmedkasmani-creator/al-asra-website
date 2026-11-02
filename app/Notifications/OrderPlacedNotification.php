<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class OrderPlacedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public int $orderId,
        public string $reference,
        public float $total
    ) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        return [
            'event' => 'order.placed',
            'order_id' => $this->orderId,
            'reference' => $this->reference,
            'total' => $this->total,
            'message' => 'A new order has been placed.',
        ];
    }
}
