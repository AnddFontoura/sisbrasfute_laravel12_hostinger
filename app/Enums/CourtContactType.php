<?php

namespace App\Enums;

enum CourtContactType: string
{
    case Whatsapp = 'whatsapp';
    case Phone = 'phone';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Whatsapp => 'WhatsApp',
            self::Phone => 'Telefone',
            self::Other => 'Outro',
        };
    }
}
