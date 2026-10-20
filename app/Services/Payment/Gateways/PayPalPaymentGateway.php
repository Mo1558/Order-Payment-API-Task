<?php

namespace App\Services\Payment\Gateways;

use App\Models\Order;
use App\Services\Payment\DTOs\PaymentResult;
use Illuminate\Support\Str;

class PayPalPaymentGateway extends AbstractPaymentGateway
{
    public function getName(): string
    {
        return 'paypal';
    }

    public function getDisplayName(): string
    {
        return $this->config('display_name', 'PayPal Express Checkout');
    }

    public function process(Order $order, float $amount, array $details = []): PaymentResult
    {
        $payerEmail = (string) ($details['payer_email'] ?? $order->customer_email);
        $simulateFailure = (bool) ($details['simulate_failure'] ?? false);

        if ($simulateFailure || str_contains(strtolower($payerEmail), 'fail')) {
            return PaymentResult::failure(
                message: 'PayPal payment failed: payer account not verified or payment rejected.',
                rawResponse: [
                    'gateway' => $this->getName(),
                    'error_code' => 'INSTRUMENT_DECLINED',
                    'order_number' => $order->order_number,
                    'amount' => $amount,
                    'payer_email' => $payerEmail,
                    'timestamp' => now()->toIso8601String(),
                ]
            );
        }

        $captureId = 'PAYID-'.strtoupper(Str::random(18));

        return PaymentResult::success(
            transactionId: $captureId,
            message: 'PayPal payment captured successfully.',
            rawResponse: [
                'gateway' => $this->getName(),
                'capture_id' => $captureId,
                'order_number' => $order->order_number,
                'amount' => $amount,
                'currency' => $order->currency,
                'payer_email' => $payerEmail,
                'mode' => $this->config('mode', 'sandbox'),
                'timestamp' => now()->toIso8601String(),
            ]
        );
    }
}
