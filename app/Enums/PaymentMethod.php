<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Wallet = 'wallet';
    case Pix = 'pix';
    case Boleto = 'boleto';
    case Card = 'card';

    /**
     * Methods that are settled instantly against the wallet balance.
     */
    public function isInstant(): bool
    {
        return $this === self::Wallet;
    }

    /**
     * Methods that require an external gateway charge. All of them route
     * through the gateway createCharge flow.
     */
    public function isGateway(): bool
    {
        return $this === self::Pix
            || $this === self::Boleto
            || $this === self::Card;
    }

    /**
     * Methods whose charge is authorized synchronously by the gateway (the
     * final status is known right after createCharge, without waiting for an
     * asynchronous webhook). Credit card is captured in-line.
     */
    public function isSynchronous(): bool
    {
        return $this === self::Card;
    }
}
