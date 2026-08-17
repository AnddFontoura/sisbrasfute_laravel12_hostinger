<?php

namespace App\Enums;

enum MatchType: int
{
    case Normal = 0;
    case Friendly = 1;
    case Championship = 2;

    public function label(): string
    {
        return match ($this) {
            self::Normal => 'Partida entre o time',
            self::Friendly => 'Amistoso',
            self::Championship => 'Campeonato',
        };
    }

    /**
     * Map frontend string values to enum instances.
     */
    public static function fromFrontendValue(string $value): self
    {
        return match ($value) {
            'team_match' => self::Normal,
            'friendly_match' => self::Friendly,
            'championship_match' => self::Championship,
            default => self::Normal,
        };
    }

    /**
     * Convert enum to frontend string value.
     */
    public function toFrontendValue(): string
    {
        return match ($this) {
            self::Normal => 'team_match',
            self::Friendly => 'friendly_match',
            self::Championship => 'championship_match',
        };
    }
}
