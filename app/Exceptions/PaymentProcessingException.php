<?php

namespace App\Exceptions;

class PaymentProcessingException extends \RuntimeException
{
    public function __construct(string $message = 'Erro ao processar pagamento')
    {
        parent::__construct($message);
    }
}
