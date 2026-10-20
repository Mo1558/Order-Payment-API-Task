<?php

namespace Tests\Unit;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Services\Payment\Gateways\CreditCardPaymentGateway;
use App\Services\Payment\Gateways\PayPalPaymentGateway;
use App\Services\Payment\Gateways\StripePaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentGatewaysTest extends TestCase
{
    use RefreshDatabase;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();
        $this->order = Order::factory()->confirmed()->create([
            'total_amount' => 150.00,
        ]);
    }

    public function test_credit_card_processes_successful_payment(): void
    {
        $gateway = new CreditCardPaymentGateway;
        $result = $gateway->process($this->order, 150.00, [
            'card_number' => '4111111111114242',
            'exp_month' => '12',
            'exp_year' => '2028',
            'cvv' => '123',
        ]);

        $this->assertTrue($result->successful);
        $this->assertEquals(PaymentStatus::SUCCESSFUL, $result->status);
        $this->assertStringStartsWith('cc_txn_', $result->transactionId);
    }

    public function test_credit_card_declines_card_ending_with_0000(): void
    {
        $gateway = new CreditCardPaymentGateway;
        $result = $gateway->process($this->order, 150.00, [
            'card_number' => '4000000000000000',
        ]);

        $this->assertFalse($result->successful);
        $this->assertEquals(PaymentStatus::FAILED, $result->status);
        $this->assertStringContainsString('declined', strtolower($result->message));
    }

    public function test_paypal_processes_successful_payment(): void
    {
        $gateway = new PayPalPaymentGateway;
        $result = $gateway->process($this->order, 150.00, [
            'payer_email' => 'buyer@example.com',
        ]);

        $this->assertTrue($result->successful);
        $this->assertEquals(PaymentStatus::SUCCESSFUL, $result->status);
        $this->assertStringStartsWith('PAYID-', $result->transactionId);
    }

    public function test_paypal_declines_when_email_contains_fail(): void
    {
        $gateway = new PayPalPaymentGateway;
        $result = $gateway->process($this->order, 150.00, [
            'payer_email' => 'fail_account@example.com',
        ]);

        $this->assertFalse($result->successful);
        $this->assertEquals(PaymentStatus::FAILED, $result->status);
    }

    public function test_stripe_processes_successful_payment(): void
    {
        $gateway = new StripePaymentGateway;
        $result = $gateway->process($this->order, 150.00, [
            'payment_method_id' => 'pm_card_visa',
        ]);

        $this->assertTrue($result->successful);
        $this->assertEquals(PaymentStatus::SUCCESSFUL, $result->status);
        $this->assertStringStartsWith('pi_', $result->transactionId);
    }

    public function test_stripe_declines_tok_charge_declined(): void
    {
        $gateway = new StripePaymentGateway;
        $result = $gateway->process($this->order, 150.00, [
            'payment_method_id' => 'tok_chargeDeclined',
        ]);

        $this->assertFalse($result->successful);
        $this->assertEquals(PaymentStatus::FAILED, $result->status);
        $this->assertStringContainsString('declined', strtolower($result->message));
    }
}
