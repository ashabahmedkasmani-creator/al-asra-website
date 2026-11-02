<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PaymentUpdatedNotification extends Notification
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
            'event' => 'payment.updated',
            'reference' => $this->reference,
            'status' => $this->status,
            'message' => 'Your payment status is now '.$this->status.'.',
        ];
    }
}
