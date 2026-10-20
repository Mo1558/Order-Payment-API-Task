<?php

namespace App\Services\Payment;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\PaymentProcessingException;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentService
{
    public function __construct(
        protected PaymentGatewayManager $gatewayManager
    ) {}

    /**
     * Process payment for an order.
     *
     * @param  array<string, mixed>  $details
     *
     * @throws PaymentProcessingException
     */
    public function processPayment(
        Order $order,
        string $paymentMethod,
        ?float $amount = null,
        array $details = []
    ): Payment {
        // Business Rule: Payments can only be processed for orders in the confirmed status.
        if ($order->status !== OrderStatus::CONFIRMED) {
            throw new PaymentProcessingException(
                "Payments can only be processed for orders in the 'confirmed' status. Current order status is '{$order->status->value}'."
            );
        }

        // Business Rule: Check if order is already fully paid
        if ($order->isFullyPaid()) {
            throw new PaymentProcessingException(
                "Order #{$order->order_number} is already fully paid."
            );
        }

        // Resolve amount: default to remaining balance or order total
        $remainingBalance = max(0.00, round((float) $order->total_amount - $order->paidAmount(), 2));
        $chargeAmount = ($amount !== null && $amount > 0) ? round($amount, 2) : $remainingBalance;

        if ($chargeAmount <= 0) {
            throw new PaymentProcessingException('Payment amount must be greater than zero.');
        }

        // Generate unique payment reference
        $paymentReference = 'PAY-'.date('Ymd').'-'.strtoupper(Str::random(8));

        return DB::transaction(function () use ($order, $paymentMethod, $chargeAmount, $paymentReference, $details) {
            // 1. Create pending payment record
            $payment = Payment::create([
                'payment_reference' => $paymentReference,
                'order_id' => $order->id,
                'amount' => $chargeAmount,
                'currency' => $order->currency ?? 'USD',
                'payment_method' => $paymentMethod,
                'status' => PaymentStatus::PENDING,
            ]);

            // 2. Resolve gateway strategy
            $gateway = $this->gatewayManager->gateway($paymentMethod);

            // 3. Process payment through gateway strategy
            $result = $gateway->process($order, $chargeAmount, $details);

            // 4. Update payment record with gateway response
            $payment->update([
                'status' => $result->status,
                'transaction_id' => $result->transactionId,
                'gateway_response' => $result->rawResponse,
                'failure_reason' => $result->successful ? null : $result->message,
                'paid_at' => $result->successful ? now() : null,
            ]);

            return $payment->fresh();
        });
    }

    /**
     * Get payment gateway manager instance.
     */
    public function getGatewayManager(): PaymentGatewayManager
    {
        return $this->gatewayManager;
    }
}
