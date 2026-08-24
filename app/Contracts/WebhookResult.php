<?php

namespace App\Contracts;

use App\Enums\PaymentStatus;

class WebhookResult
{
    public function __construct(
        public readonly string $chargeId,
        public readonly PaymentStatus $status,
        public readonly ?string $errorMessage = null,
    ) {}
}
