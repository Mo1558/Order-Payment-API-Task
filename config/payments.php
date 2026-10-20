<?php

use App\Services\Payment\Gateways\CreditCardPaymentGateway;
use App\Services\Payment\Gateways\PayPalPaymentGateway;
use App\Services\Payment\Gateways\StripePaymentGateway;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Payment Gateway
    |--------------------------------------------------------------------------
    |
    | This value is the default payment gateway used when no specific gateway
    | is requested by the client.
    |
    */
    'default' => env('DEFAULT_PAYMENT_GATEWAY', 'credit_card'),

    /*
    |--------------------------------------------------------------------------
    | Payment Gateways Registry
    |--------------------------------------------------------------------------
    |
    | Each gateway must implement App\Services\Payment\Contracts\PaymentGatewayInterface.
    | Configuration options defined here can be overridden dynamically at runtime
    | through database settings stored in the `payment_gateways` table.
    |
    */
    'gateways' => [

        'credit_card' => [
            'driver' => CreditCardPaymentGateway::class,
            'display_name' => 'Credit / Debit Card',
            'enabled' => env('PAYMENT_CREDIT_CARD_ENABLED', true),
            'api_key' => env('PAYMENT_CREDIT_CARD_API_KEY', 'mock_cc_api_key_test_123'),
            'secret' => env('PAYMENT_CREDIT_CARD_SECRET', 'mock_cc_secret_key_xyz_789'),
        ],

        'paypal' => [
            'driver' => PayPalPaymentGateway::class,
            'display_name' => 'PayPal Express Checkout',
            'enabled' => env('PAYMENT_PAYPAL_ENABLED', true),
            'client_id' => env('PAYMENT_PAYPAL_CLIENT_ID', 'mock_paypal_client_id_123'),
            'secret' => env('PAYMENT_PAYPAL_SECRET', 'mock_paypal_secret_xyz'),
            'mode' => env('PAYMENT_PAYPAL_MODE', 'sandbox'),
        ],

        'stripe' => [
            'driver' => StripePaymentGateway::class,
            'display_name' => 'Stripe Payments',
            'enabled' => env('PAYMENT_STRIPE_ENABLED', true),
            'secret_key' => env('PAYMENT_STRIPE_SECRET_KEY', 'sk_test_mock_stripe_key_123'),
            'webhook_secret' => env('PAYMENT_STRIPE_WEBHOOK_SECRET', 'whsec_mock_stripe_test'),
        ],

    ],

];
