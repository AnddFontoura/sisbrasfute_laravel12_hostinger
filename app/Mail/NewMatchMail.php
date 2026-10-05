<?php

namespace App\Mail;

use App\Models\Matches;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewMatchMail extends Mailable implements ShouldQueue
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
            subject: 'SisBrasFute - Nova Partida Disponível',
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
            view: 'emails.new-match',
            with: [
                'match' => $this->match,
                'playerName' => $this->playerName,
                'participateUrl' => $participateUrl,
                'myTeamName' => $this->match->my_team_name,
                'enemyTeamName' => $this->match->enemy_team_name,
                'schedule' => $this->match->schedule_br,
                // Mantém a formatação (quebras de linha, listas, negrito) criada
                // no editor do site, removendo apenas tags perigosas/indesejadas.
                'location' => $this->sanitizeLocationHtml($this->match->location ?? ''),
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

    /**
     * Mantém a formatação básica do HTML gerado pelo editor do site
     * (parágrafos, quebras de linha, listas, ênfase e links), removendo
     * tags perigosas e atributos que possam carregar scripts.
     */
    private function sanitizeLocationHtml(string $html): string
    {
        if ($html === '') {
            return '';
        }

        // Allowlist de tags de formatação que o editor (Quill) produz.
        $allowedTags = '<p><br><b><strong><i><em><u><s><ul><ol><li><a><span>';
        $clean = strip_tags($html, $allowedTags);

        // Remove quaisquer atributos que não sejam href em links,
        // evitando injeção via on*, style, etc.
        $clean = preg_replace_callback(
            '/<a\b[^>]*>/i',
            function ($matches) {
                if (preg_match('/\bhref\s*=\s*("[^"]*"|\'[^\']*\'|[^\s">]+)/i', $matches[0], $href)) {
                    return '<a ' . $href[0] . ' target="_blank" rel="noopener noreferrer">';
                }
                return '<a>';
            },
            $clean
        );

        // Remove atributos de todas as demais tags permitidas.
        $clean = preg_replace('/<(?!a\b)([a-z0-9]+)\b[^>]*>/i', '<$1>', $clean);

        return trim($clean);
    }
}
