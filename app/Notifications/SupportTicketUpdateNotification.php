<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SupportTicketUpdateNotification extends Notification
{
    use Queueable;

    public function __construct(
        public int $ticketId,
        public string $reference,
        public string $message
    ) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        return [
            'event' => 'support.replied',
            'ticket_id' => $this->ticketId,
            'reference' => $this->reference,
            'message' => $this->message,
        ];
    }
}
