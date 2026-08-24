<?php

namespace App\Contracts;

use App\Enums\PaymentStatus;

interface PaymentGatewayContract
{
    /**
     * Creates a payment charge on the gateway.
     *
     * @param int $amountCents Amount in centavos
     * @param string $userRef User reference (e.g., user ID or email)
     * @param string $returnUrl URL to redirect after payment
     * @return PaymentChargeResult
     */
    public function createCharge(int $amountCents, string $userRef, string $returnUrl): PaymentChargeResult;

    /**
     * Checks the current status of a charge.
     *
     * @param string $chargeId Gateway charge identifier
     * @return PaymentStatus
     */
    public function checkStatus(string $chargeId): PaymentStatus;

    /**
     * Processes an incoming webhook payload from the gateway.
     *
     * @param array $payload Raw webhook data
     * @return WebhookResult
     */
    public function handleWebhook(array $payload): WebhookResult;
}
