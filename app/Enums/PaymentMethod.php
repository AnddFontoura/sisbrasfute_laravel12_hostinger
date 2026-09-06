<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Wallet = 'wallet';
    case Pix = 'pix';
    case Boleto = 'boleto';

    /**
     * Methods that are settled instantly against the wallet balance.
     */
    public function isInstant(): bool
    {
        return $this === self::Wallet;
    }

    /**
     * Methods that require an external gateway charge and asynchronous
     * confirmation via webhook.
     */
    public function isGateway(): bool
    {
        return $this === self::Pix || $this === self::Boleto;
    }
}
