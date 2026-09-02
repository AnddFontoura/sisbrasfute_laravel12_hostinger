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
        $frontendUrl = rtrim(config('app.frontend_url', 'http://localhost:5173'), '/');

        // Contextual link: match > team > notifications list
        if ($this->notification->match_id) {
            $targetUrl = $frontendUrl . '/matches/show/' . $this->notification->match_id;
            $targetLabel = 'VER PARTIDA';
        } elseif ($this->notification->team_id) {
            $targetUrl = $frontendUrl . '/team/show/' . $this->notification->team_id;
            $targetLabel = 'VER TIME';
        } else {
            $targetUrl = $frontendUrl . '/notifications';
            $targetLabel = 'VER NOTIFICAÇÕES';
        }

        return new Content(
            view: 'emails.admin-notification',
            with: [
                'title' => $this->notification->title,
                'description' => $this->notification->description,
                'recipientName' => $this->recipientName,
                'notificationsUrl' => $targetUrl,
                'notificationsLabel' => $targetLabel,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
