<?php

namespace App\Enums;

enum CourtSurfaceType: string
{
    case Natural = 'natural';
    case Sintetico = 'sintetico';
    case Emborrachado = 'emborrachado';
    case Piso = 'piso';

    public function label(): string
    {
        return match ($this) {
            self::Natural => 'Natural',
            self::Sintetico => 'Sintético',
            self::Emborrachado => 'Emborrachado',
            self::Piso => 'Piso',
        };
    }
}
