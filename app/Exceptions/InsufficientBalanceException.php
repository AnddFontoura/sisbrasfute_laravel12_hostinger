<?php

namespace App\Exceptions;

class InsufficientBalanceException extends \RuntimeException
{
    public function __construct(
        public readonly int $currentBalance,
        public readonly int $requiredAmount,
    ) {
        parent::__construct("Saldo insuficiente. Disponível: {$currentBalance}, necessário: {$requiredAmount}");
    }
}
