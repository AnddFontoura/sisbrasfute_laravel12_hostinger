<?php

namespace App\Mail;

use App\Models\Matches;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PositionAvailableMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $tries = 3;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public Matches $match,
        public ?string $playerName = null,
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'SisBrasFute - Vaga Disponível na Partida',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $frontendUrl = config('app.frontend_url', 'http://localhost:5173');
        $participateUrl = $frontendUrl . '/matches/' . $this->match->id . '/choose-position';

        return new Content(
            view: 'emails.position-available',
            with: [
                'match' => $this->match,
                'playerName' => $this->playerName,
                'participateUrl' => $participateUrl,
                'myTeamName' => $this->match->my_team_name,
                'enemyTeamName' => $this->match->enemy_team_name,
                'schedule' => $this->match->schedule_br,
                'location' => strip_tags($this->match->location ?? ''),
                'cityName' => $this->match->cityInfo?->name ?? '',
                'tagName' => $this->match->tag?->name ?? null,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
