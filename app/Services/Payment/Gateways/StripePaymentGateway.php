<?php

namespace App\Services\Payment\Gateways;

use App\Models\Order;
use App\Services\Payment\DTOs\PaymentResult;
use Illuminate\Support\Str;

class StripePaymentGateway extends AbstractPaymentGateway
{
    public function getName(): string
    {
        return 'stripe';
    }

    public function getDisplayName(): string
    {
        return $this->config('display_name', 'Stripe');
    }

    public function process(Order $order, float $amount, array $details = []): PaymentResult
    {
        $token = (string) ($details['payment_method_id'] ?? $details['token'] ?? '');
        $simulateFailure = (bool) ($details['simulate_failure'] ?? false);

        if ($simulateFailure || $token === 'tok_chargeDeclined') {
            return PaymentResult::failure(
                message: 'Stripe charge failed: Your card was declined.',
                rawResponse: [
                    'gateway' => $this->getName(),
                    'error_type' => 'card_error',
                    'decline_code' => 'generic_decline',
                    'order_number' => $order->order_number,
                    'amount' => $amount,
                    'timestamp' => now()->toIso8601String(),
                ]
            );
        }

        $paymentIntentId = 'pi_'.Str::lower(Str::random(24));

        return PaymentResult::success(
            transactionId: $paymentIntentId,
            message: 'Stripe payment intent confirmed successfully.',
            rawResponse: [
                'gateway' => $this->getName(),
                'payment_intent_id' => $paymentIntentId,
                'order_number' => $order->order_number,
                'amount' => $amount,
                'currency' => $order->currency,
                'status' => 'succeeded',
                'timestamp' => now()->toIso8601String(),
            ]
        );
    }
}
