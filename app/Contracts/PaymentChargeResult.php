<?php

namespace App\Contracts;

class PaymentChargeResult
{
    public function __construct(
        public readonly string $chargeId,
        public readonly string $paymentUrl,
        public readonly string $status,
        /**
         * Method used for the charge: pix or boleto.
         */
        public readonly string $method = 'pix',
        /**
         * Pix "copia e cola" (EMV payload). Present for Pix charges.
         */
        public readonly ?string $pixCopiaECola = null,
        /**
         * Base64-encoded QR Code image, when provided by the gateway.
         */
        public readonly ?string $pixQrCodeBase64 = null,
        /**
         * Boleto barcode digitable line. Present for boleto charges.
         */
        public readonly ?string $boletoLinhaDigitavel = null,
        /**
         * URL to the boleto PDF, when available.
         */
        public readonly ?string $boletoPdfUrl = null,
        /**
         * Boleto due date (YYYY-MM-DD).
         */
        public readonly ?string $boletoDueDate = null,
    ) {}
}
