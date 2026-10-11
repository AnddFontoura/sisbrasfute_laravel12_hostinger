<?php

namespace App\Enums;

enum CourtRole: string
{
    case Owner = 'owner';
    case Manager = 'manager';
    case Employee = 'employee';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Proprietário',
            self::Manager => 'Gerente',
            self::Employee => 'Funcionário',
        };
    }

    public function canManageTeam(): bool
    {
        return $this === self::Owner;
    }

    public function canEditData(): bool
    {
        return in_array($this, [self::Owner, self::Manager], true);
    }

    public function canManageReservations(): bool
    {
        // All current roles (owner/manager/employee) may manage reservations.
        // Returning true unconditionally is correct ONLY because every case may
        // reserve; switch to an in_array allow-list if a view-only role is ever added.
        return true;
    }
}
