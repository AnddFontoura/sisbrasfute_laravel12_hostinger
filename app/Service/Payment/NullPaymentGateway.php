<?php

namespace App\Service\Payment;

use App\Contracts\PaymentGatewayContract;
use App\Contracts\PaymentChargeResult;
use App\Contracts\WebhookResult;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;

class NullPaymentGateway implements PaymentGatewayContract
{
    public function createCharge(
        int $amountCents,
        string $userRef,
        string $returnUrl,
        PaymentMethod $method = PaymentMethod::Pix,
        array $payer = [],
    ): PaymentChargeResult {
        $prefix = match ($method) {
            PaymentMethod::Boleto => 'boleto:',
            PaymentMethod::Card => 'card:',
            default => '',
        };

        return new PaymentChargeResult(
            chargeId: $prefix . 'null_' . uniqid(),
            paymentUrl: $returnUrl . '?status=success',
            // Card is synchronous: approve immediately in the fake gateway.
            status: $method === PaymentMethod::Card
                ? PaymentStatus::Completed->value
                : PaymentStatus::Pending->value,
            method: $method->value,
            pixCopiaECola: $method === PaymentMethod::Pix ? 'null-pix-copia-e-cola' : null,
            boletoLinhaDigitavel: $method === PaymentMethod::Boleto ? '00000.00000 00000.000000 00000.000000 0 00000000000000' : null,
            installments: $method === PaymentMethod::Card ? max(1, (int) ($payer['installments'] ?? 1)) : null,
            installmentValueCents: $method === PaymentMethod::Card
                ? intdiv($amountCents, max(1, (int) ($payer['installments'] ?? 1)))
                : null,
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
