<?php

namespace App\Services\Payment\Gateways;

use App\Models\Order;
use App\Services\Payment\DTOs\PaymentResult;
use Illuminate\Support\Str;

class CreditCardPaymentGateway extends AbstractPaymentGateway
{
    public function getName(): string
    {
        return 'credit_card';
    }

    public function getDisplayName(): string
    {
        return $this->config('display_name', 'Credit / Debit Card');
    }

    public function process(Order $order, float $amount, array $details = []): PaymentResult
    {
        $cardNumber = (string) ($details['card_number'] ?? '');
        $simulateFailure = (bool) ($details['simulate_failure'] ?? false);

        // Simulation check: failure requested or card number ending in 0000
        if ($simulateFailure || str_ends_with($cardNumber, '0000')) {
            return PaymentResult::failure(
                message: 'Transaction declined: card declined by issuing bank or insufficient funds.',
                rawResponse: [
                    'gateway' => $this->getName(),
                    'error_code' => 'card_declined',
                    'order_number' => $order->order_number,
                    'amount' => $amount,
                    'currency' => $order->currency,
                    'last4' => substr($cardNumber, -4),
                    'timestamp' => now()->toIso8601String(),
                ]
            );
        }

        // Generate simulated transaction reference
        $transactionId = 'cc_txn_'.Str::lower(Str::random(16));

        return PaymentResult::success(
            transactionId: $transactionId,
            message: 'Credit card payment processed successfully.',
            rawResponse: [
                'gateway' => $this->getName(),
                'transaction_id' => $transactionId,
                'order_number' => $order->order_number,
                'amount' => $amount,
                'currency' => $order->currency,
                'card_brand' => $this->detectCardBrand($cardNumber),
                'last4' => substr($cardNumber, -4) ?: '4242',
                'timestamp' => now()->toIso8601String(),
            ]
        );
    }

    private function detectCardBrand(string $cardNumber): string
    {
        if (str_starts_with($cardNumber, '4')) {
            return 'Visa';
        }
        if (str_starts_with($cardNumber, '5')) {
            return 'Mastercard';
        }
        if (str_starts_with($cardNumber, '3')) {
            return 'American Express';
        }

        return 'CreditCard';
    }
}
