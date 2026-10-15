<?php

namespace App\Enums;

enum CourtInvitationStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendente',
            self::Accepted => 'Aceito',
            self::Rejected => 'Recusado',
            self::Expired => 'Expirado',
            self::Cancelled => 'Cancelado',
        };
    }
}
