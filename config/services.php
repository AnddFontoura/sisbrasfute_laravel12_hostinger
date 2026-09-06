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
    | Defines which payment gateway drives wallet deposits. When set to
    | "inter" and the Inter credentials below are present, the Banco Inter
    | Pix gateway is used; otherwise the application falls back to the
    | NullPaymentGateway (useful for local/dev environments).
    |
    */

    'payment' => [
        'gateway' => env('PAYMENT_GATEWAY', 'null'),
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
