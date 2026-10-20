<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_payment_cannot_be_processed_for_pending_order(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::PENDING, 'total_amount' => 100.00]);

        $response = $this->actingAsJwt($this->user)->postJson('/api/payments', [
            'order_id' => $order->id,
            'payment_method' => 'credit_card',
            'amount' => 100.00,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);

        $this->assertStringContainsString('confirmed', strtolower($response->json('message')));
        $this->assertDatabaseMissing('payments', ['order_id' => $order->id]);
    }

    public function test_payment_cannot_be_processed_for_cancelled_order(): void
    {
        $order = Order::factory()->cancelled()->create(['total_amount' => 100.00]);

        $response = $this->actingAsJwt($this->user)->postJson('/api/payments', [
            'order_id' => $order->id,
            'payment_method' => 'credit_card',
            'amount' => 100.00,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);

        $this->assertStringContainsString('confirmed', strtolower($response->json('message')));
    }

    public function test_payment_can_be_processed_for_confirmed_order_using_credit_card(): void
    {
        $order = Order::factory()->confirmed()->create(['total_amount' => 250.00]);

        $response = $this->actingAsJwt($this->user)->postJson('/api/payments', [
            'order_id' => $order->id,
            'payment_method' => 'credit_card',
            'amount' => 250.00,
            'details' => [
                'card_number' => '4111111111114242',
                'exp_month' => '12',
                'exp_year' => '2028',
                'cvv' => '123',
            ],
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Payment processed successfully.',
                'data' => [
                    'order_id' => $order->id,
                    'amount' => 250.00,
                    'payment_method' => 'credit_card',
                    'status' => 'successful',
                ],
            ]);

        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'status' => 'successful',
            'payment_method' => 'credit_card',
        ]);
    }

    public function test_failed_payment_simulation_is_recorded_with_failed_status(): void
    {
        $order = Order::factory()->confirmed()->create(['total_amount' => 150.00]);

        $response = $this->actingAsJwt($this->user)->postJson('/api/payments', [
            'order_id' => $order->id,
            'payment_method' => 'credit_card',
            'amount' => 150.00,
            'details' => [
                'card_number' => '4000000000000000', // ends in 0000 -> triggers decline simulation
            ],
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'order_id' => $order->id,
                    'status' => 'failed',
                ],
            ]);

        $this->assertNotNull($response->json('data.failure_reason'));
        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'status' => 'failed',
        ]);
    }

    public function test_payment_processing_with_paypal_gateway(): void
    {
        $order = Order::factory()->confirmed()->create(['total_amount' => 120.00]);

        $response = $this->actingAsJwt($this->user)->postJson('/api/payments', [
            'order_id' => $order->id,
            'payment_method' => 'paypal',
            'amount' => 120.00,
            'details' => [
                'payer_email' => 'buyer@paypal.com',
            ],
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'payment_method' => 'paypal',
                    'status' => 'successful',
                ],
            ]);
    }

    public function test_payment_processing_with_stripe_gateway(): void
    {
        $order = Order::factory()->confirmed()->create(['total_amount' => 85.00]);

        $response = $this->actingAsJwt($this->user)->postJson('/api/payments', [
            'order_id' => $order->id,
            'payment_method' => 'stripe',
            'amount' => 85.00,
            'details' => [
                'payment_method_id' => 'pm_card_visa',
            ],
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'payment_method' => 'stripe',
                    'status' => 'successful',
                ],
            ]);
    }

    public function test_can_view_all_payments_with_pagination(): void
    {
        Payment::factory()->count(10)->create();

        $response = $this->actingAsJwt($this->user)->getJson('/api/payments?per_page=5');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
                'pagination' => ['current_page', 'per_page', 'total'],
            ]);

        $this->assertCount(5, $response->json('data'));
    }

    public function test_can_view_single_payment_by_id(): void
    {
        $payment = Payment::factory()->create();

        $response = $this->actingAsJwt($this->user)->getJson("/api/payments/{$payment->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $payment->id,
                    'payment_reference' => $payment->payment_reference,
                ],
            ]);
    }

    public function test_can_retrieve_payments_for_specific_order(): void
    {
        $order = Order::factory()->confirmed()->create();
        Payment::factory()->count(2)->create(['order_id' => $order->id]);
        Payment::factory()->create(); // Another order's payment

        $response = $this->actingAsJwt($this->user)->getJson("/api/orders/{$order->id}/payments");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertCount(2, $response->json('data'));
    }
}
