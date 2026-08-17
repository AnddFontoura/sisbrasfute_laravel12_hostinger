<?php

namespace App\Enums;

enum MyTeamIs: int
{
    case Home = 0;
    case Visitor = 1;

    public function label(): string
    {
        return match ($this) {
            self::Home => 'Mandante',
            self::Visitor => 'Visitante',
        };
    }

    /**
     * Map frontend string values to enum instances.
     */
    public static function fromFrontendValue(?string $value): self
    {
        return match ($value) {
            'home' => self::Home,
            'visitor' => self::Visitor,
            default => self::Home,
        };
    }

    /**
     * Convert enum to frontend string value.
     */
    public function toFrontendValue(): string
    {
        return match ($this) {
            self::Home => 'home',
            self::Visitor => 'visitor',
        };
    }
}
