<?php

namespace App\Mail;

use App\Models\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdminNotificationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $tries = 3;

    public function __construct(
        public Notification $notification,
        public ?string $recipientName = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'SisBrasFute - ' . $this->notification->title,
        );
    }

    public function content(): Content
    {
        $frontendUrl = config('app.frontend_url', 'http://localhost:5173');

        return new Content(
            view: 'emails.admin-notification',
            with: [
                'title' => $this->notification->title,
                'description' => $this->notification->description,
                'recipientName' => $this->recipientName,
                'notificationsUrl' => $frontendUrl . '/notifications',
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
