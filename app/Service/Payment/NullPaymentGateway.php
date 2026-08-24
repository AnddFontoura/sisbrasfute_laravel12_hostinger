<?php

namespace App\Service\Payment;

use App\Contracts\PaymentGatewayContract;
use App\Contracts\PaymentChargeResult;
use App\Contracts\WebhookResult;
use App\Enums\PaymentStatus;

class NullPaymentGateway implements PaymentGatewayContract
{
    public function createCharge(int $amountCents, string $userRef, string $returnUrl): PaymentChargeResult
    {
        return new PaymentChargeResult(
            chargeId: 'null_' . uniqid(),
            paymentUrl: $returnUrl . '?status=success',
            status: 'pending',
        );
    }

    public function checkStatus(string $chargeId): PaymentStatus
    {
        return PaymentStatus::Completed;
    }

    public function handleWebhook(array $payload): WebhookResult
    {
        return new WebhookResult(
            chargeId: $payload['charge_id'] ?? 'unknown',
            status: PaymentStatus::Completed,
        );
    }
}
