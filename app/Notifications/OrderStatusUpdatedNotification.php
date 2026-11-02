<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class OrderStatusUpdatedNotification extends Notification
{
    use Queueable;

    public function __construct(public string $reference, public string $status) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        return [
            'event' => 'order.status_updated',
            'reference' => $this->reference,
            'status' => $this->status,
            'message' => 'Your order status is now '.str_replace('_', ' ', $this->status).'.',
        ];
    }
}
