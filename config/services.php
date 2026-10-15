<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment Gateway
    |--------------------------------------------------------------------------
    |
    | Defines which payment gateway drives wallet deposits. The active gateway
    | is normally chosen in the admin panel (system_configs "payment_gateway"
    | key) and defaults to "efi". When the selected gateway's credentials are
    | missing, the application falls back to the NullPaymentGateway (useful for
    | local/dev environments). Supported values: "efi", "inter", "null".
    |
    */

    'payment' => [
        // Fallback used when no gateway is configured in the admin panel
        // (system_configs "payment_gateway" key). The admin setting takes
        // precedence; this env only acts as the default. Valid: efi, inter, null.
        'gateway' => env('PAYMENT_GATEWAY', 'efi'),
    ],

    'efi' => [
        // Pix API (used for Pix charges, requires mTLS).
        // Production: https://pix.api.efipay.com.br  Sandbox: https://pix-h.api.efipay.com.br
        'pix_base_url' => env('EFI_PIX_BASE_URL', 'https://pix.api.efipay.com.br'),
        // Cobranças API (used for boleto + credit card, no mTLS).
        // Production: https://cobrancas.api.efipay.com.br  Sandbox: https://cobrancas-h.api.efipay.com.br
        'cobrancas_base_url' => env('EFI_COBRANCAS_BASE_URL', 'https://cobrancas.api.efipay.com.br'),
        'client_id' => env('EFI_CLIENT_ID'),
        'client_secret' => env('EFI_CLIENT_SECRET'),
        // Pix key (chave) that will receive the payments.
        'pix_key' => env('EFI_PIX_KEY'),
        // Absolute path to the mTLS certificate (.pem) generated from the .p12
        // downloaded in the EFI dashboard (API > Minhas aplicações). Used by the
        // Pix API only; the Cobranças API (boleto/card) does not use mTLS.
        'certificate_path' => env('EFI_CERTIFICATE_PATH'),
        // Optional: separate private key path. Leave empty when the .pem above
        // already bundles both certificate and key.
        'private_key_path' => env('EFI_PRIVATE_KEY_PATH'),
        // Expiration (in seconds) of the Pix charge (cob).
        'cob_expiration' => (int) env('EFI_COB_EXPIRATION', 3600),
        // Number of days until the boleto due date.
        'boleto_due_days' => (int) env('EFI_BOLETO_DUE_DAYS', 3),
    ],

    'inter' => [
        // Production: https://cdpj.partners.bancointer.com.br
        'base_url' => env('INTER_BASE_URL', 'https://cdpj.partners.bancointer.com.br'),
        'client_id' => env('INTER_CLIENT_ID'),
        'client_secret' => env('INTER_CLIENT_SECRET'),
        // Pix key (chave) that will receive the payments.
        'pix_key' => env('INTER_PIX_KEY'),
        // Absolute paths to the mTLS certificate and private key downloaded
        // from the Inter PJ internet banking (Gestão de Integrações).
        'certificate_path' => env('INTER_CERTIFICATE_PATH'),
        'private_key_path' => env('INTER_PRIVATE_KEY_PATH'),
        // Optional: conta corrente header for accounts with multiple accounts.
        'conta_corrente' => env('INTER_CONTA_CORRENTE'),
        // Expiration (in seconds) of the Pix charge (cob).
        'cob_expiration' => (int) env('INTER_COB_EXPIRATION', 3600),
        // Number of days until the boleto due date.
        'boleto_due_days' => (int) env('INTER_BOLETO_DUE_DAYS', 3),
    ],

];
