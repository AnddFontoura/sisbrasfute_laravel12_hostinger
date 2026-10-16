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

    /*
    | EFI Bank — credentials and base URLs are split per environment
    | ("sandbox" / "production"). The active environment is chosen in the admin
    | panel (system_configs "payment_environment_efi" key, default
    | "production"); AppServiceProvider resolves the matching block below. The
    | EFI client_id/secret and the mTLS certificate differ between sandbox and
    | production, so each environment has its own set.
    */
    'efi' => [
        'default_environment' => env('EFI_ENVIRONMENT', 'production'),

        // Charge timings shared across environments. The Pix key is NOT shared:
        // each environment carries its own (production vs homologação), since
        // a homologação Pix key is invalid in production and vice versa.
        'cob_expiration' => (int) env('EFI_COB_EXPIRATION', 3600),
        'boleto_due_days' => (int) env('EFI_BOLETO_DUE_DAYS', 3),

        'production' => [
            // Pix API (Pix charges, requires mTLS).
            'pix_base_url' => env('EFI_PIX_BASE_URL', 'https://pix.api.efipay.com.br'),
            // Cobranças API (boleto + credit card, no mTLS).
            'cobrancas_base_url' => env('EFI_COBRANCAS_BASE_URL', 'https://cobrancas.api.efipay.com.br'),
            'client_id' => env('EFI_CLIENT_ID'),
            'client_secret' => env('EFI_CLIENT_SECRET'),
            // Pix key registered on the PRODUCTION account.
            'pix_key' => env('EFI_PIX_KEY'),
            // Absolute path to the mTLS certificate (.pem) generated from the
            // production .p12 (EFI dashboard > API > Minhas aplicações).
            'certificate_path' =>  storage_path('app/private') . '/' .  env('EFI_CERTIFICATE_PATH'),
            // Optional separate key; leave empty if the .pem bundles both.
            'private_key_path' => env('EFI_PRIVATE_KEY_PATH'),
        ],

        'sandbox' => [
            'pix_base_url' => env('EFI_SANDBOX_PIX_BASE_URL', 'https://pix-h.api.efipay.com.br'),
            'cobrancas_base_url' => env('EFI_SANDBOX_COBRANCAS_BASE_URL', 'https://cobrancas-h.api.efipay.com.br'),
            'client_id' => env('EFI_SANDBOX_CLIENT_ID'),
            'client_secret' => env('EFI_SANDBOX_CLIENT_SECRET'),
            // Pix key registered on the homologação account.
            'pix_key' => env('EFI_SANDBOX_PIX_KEY'),
            // mTLS certificate (.pem) generated from the homologação .p12.
            // Same convention as production: set only the file name in the env;
            // it is resolved under storage/app/private.
            'certificate_path' => storage_path('app/private') . '/' . env('EFI_SANDBOX_CERTIFICATE_PATH'),
            'private_key_path' => env('EFI_SANDBOX_PRIVATE_KEY_PATH'),
        ],
    ],

    /*
    | Banco Inter — same per-environment split. Inter's sandbox uses the same
    | API host as production but with sandbox credentials/certificate issued in
    | the Inter PJ internet banking.
    */
    'inter' => [
        'default_environment' => env('INTER_ENVIRONMENT', 'production'),

        // Shared across environments.
        'pix_key' => env('INTER_PIX_KEY'),
        'conta_corrente' => env('INTER_CONTA_CORRENTE'),
        'cob_expiration' => (int) env('INTER_COB_EXPIRATION', 3600),
        'boleto_due_days' => (int) env('INTER_BOLETO_DUE_DAYS', 3),

        'production' => [
            'base_url' => env('INTER_BASE_URL', 'https://cdpj.partners.bancointer.com.br'),
            'client_id' => env('INTER_CLIENT_ID'),
            'client_secret' => env('INTER_CLIENT_SECRET'),
            // mTLS certificate/key downloaded from the Inter PJ internet
            // banking (Gestão de Integrações).
            'certificate_path' => env('INTER_CERTIFICATE_PATH'),
            'private_key_path' => env('INTER_PRIVATE_KEY_PATH'),
        ],

        'sandbox' => [
            'base_url' => env('INTER_SANDBOX_BASE_URL', 'https://cdpj-sandbox.partners.uatinter.co'),
            'client_id' => env('INTER_SANDBOX_CLIENT_ID'),
            'client_secret' => env('INTER_SANDBOX_CLIENT_SECRET'),
            'certificate_path' => env('INTER_SANDBOX_CERTIFICATE_PATH'),
            'private_key_path' => env('INTER_SANDBOX_PRIVATE_KEY_PATH'),
        ],
    ],

];
