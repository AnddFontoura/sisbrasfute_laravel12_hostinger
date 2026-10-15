<?php

namespace App\Enums;

enum ReservationDuration: int
{
    case Sixty = 60;
    case Ninety = 90;
    case OneTwenty = 120;

    public function label(): string
    {
        return match ($this) {
            self::Sixty => '1h',
            self::Ninety => '1h30',
            self::OneTwenty => '2h',
        };
    }
}
