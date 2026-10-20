<?php

namespace Tests\Unit;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\PaymentProcessingException;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Payment\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    private PaymentService $paymentService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->paymentService = app(PaymentService::class);
    }

    public function test_payment_fails_if_order_status_is_pending(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::PENDING]);

        $this->expectException(PaymentProcessingException::class);
        $this->expectExceptionMessage("Payments can only be processed for orders in the 'confirmed' status");

        $this->paymentService->processPayment($order, 'credit_card', 100.00);
    }

    public function test_payment_fails_if_order_status_is_cancelled(): void
    {
        $order = Order::factory()->cancelled()->create();

        $this->expectException(PaymentProcessingException::class);
        $this->expectExceptionMessage("Payments can only be processed for orders in the 'confirmed' status");

        $this->paymentService->processPayment($order, 'credit_card', 100.00);
    }

    public function test_payment_succeeds_when_order_is_confirmed(): void
    {
        $order = Order::factory()->confirmed()->create(['total_amount' => 100.00]);

        $payment = $this->paymentService->processPayment($order, 'credit_card', 100.00, [
            'card_number' => '4242424242424242',
        ]);

        $this->assertInstanceOf(Payment::class, $payment);
        $this->assertEquals(PaymentStatus::SUCCESSFUL, $payment->status);
        $this->assertEquals(100.00, (float) $payment->amount);
        $this->assertNotNull($payment->transaction_id);
        $this->assertNotNull($payment->paid_at);
    }

    public function test_cannot_process_payment_for_already_fully_paid_order(): void
    {
        $order = Order::factory()->confirmed()->create(['total_amount' => 100.00]);
        Payment::factory()->create([
            'order_id' => $order->id,
            'amount' => 100.00,
            'status' => PaymentStatus::SUCCESSFUL,
        ]);

        $this->expectException(PaymentProcessingException::class);
        $this->expectExceptionMessage('already fully paid');

        $this->paymentService->processPayment($order, 'credit_card', 50.00);
    }
}
