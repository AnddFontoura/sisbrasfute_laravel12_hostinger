<?php

namespace App\Mail;

use App\Models\Court;
use App\Models\CourtInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CourtTeamInvitationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $tries = 3;

    public function __construct(
        public CourtInvitation $invitation,
        public Court $court,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'SisBrasFute - Convite para administrar quadra',
        );
    }

    public function content(): Content
    {
        $frontendUrl = config('app.frontend_url', 'http://localhost:5173');
        $acceptUrl = $frontendUrl . '/court-invitation/accept?token=' . $this->invitation->token;

        return new Content(
            view: 'emails.court-team-invitation',
            with: [
                'invitation' => $this->invitation,
                'court' => $this->court,
                'acceptUrl' => $acceptUrl,
                'courtName' => $this->court->name,
                'roleLabel' => $this->invitation->role->label(),
            ],
        );
    }

    /**
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
