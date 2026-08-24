<?php

namespace App\Contracts;

class PaymentChargeResult
{
    public function __construct(
        public readonly string $chargeId,
        public readonly string $paymentUrl,
        public readonly string $status,
    ) {}
}
