<?php

namespace App\Exceptions;

use Symfony\Component\HttpFoundation\Response;

/**
 * Thrown when a requested payment method is not available on the active
 * gateway — either the gateway does not support it or the admin disabled it.
 */
class PaymentMethodNotAvailableException extends \RuntimeException
{
    public function __construct(string $method, string $gateway)
    {
        parent::__construct(
            "O método de pagamento \"{$method}\" não está disponível no gateway \"{$gateway}\".",
            Response::HTTP_UNPROCESSABLE_ENTITY,
        );
    }
}
