<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use App\Services\Payment\DTOs\PaymentResult;
use App\Services\Payment\Gateways\AbstractPaymentGateway;
use App\Services\Payment\PaymentGatewayManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentGatewayExtensibilityTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_can_retrieve_available_payment_gateways(): void
    {
        $response = $this->actingAsJwt($this->user)->getJson('/api/payment-gateways');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'data' => [
                    '*' => ['name', 'display_name'],
                ],
            ]);
    }

    public function test_can_save_and_update_gateway_configuration_in_database(): void
    {
        $payload = [
            'name' => 'square',
            'display_name' => 'Square Payments',
            'is_active' => true,
            'config' => [
                'app_id' => 'sq_app_123',
                'access_token' => 'sq_token_456',
            ],
        ];

        $response = $this->actingAsJwt($this->user)->postJson('/api/payment-gateways', $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'square',
                    'display_name' => 'Square Payments',
                    'is_active' => true,
                ],
            ]);

        $this->assertDatabaseHas('payment_gateways', [
            'name' => 'square',
            'display_name' => 'Square Payments',
        ]);
    }

    public function test_custom_gateway_registered_via_strategy_pattern_works_through_api(): void
    {
        // 1. Define custom gateway
        $cryptoGateway = new class extends AbstractPaymentGateway
        {
            public function getName(): string
            {
                return 'bitcoin';
            }

            public function getDisplayName(): string
            {
                return 'Bitcoin Lightning Network';
            }

            public function process(Order $order, float $amount, array $details = []): PaymentResult
            {
                return PaymentResult::success(
                    transactionId: 'btc_ln_'.bin2hex(random_bytes(8)),
                    message: 'Bitcoin Lightning invoice settled.',
                    rawResponse: [
                        'gateway' => 'bitcoin',
                        'network' => 'lightning',
                        'fee_sats' => 1,
                    ]
                );
            }
        };

        // 2. Register custom gateway into PaymentGatewayManager
        /** @var PaymentGatewayManager $manager */
        $manager = app(PaymentGatewayManager::class);
        $manager->register('bitcoin', fn () => $cryptoGateway);

        // 3. Create confirmed order
        $order = Order::factory()->confirmed()->create(['total_amount' => 50.00]);

        // 4. Process payment using the new gateway through the REST API
        $response = $this->actingAsJwt($this->user)->postJson('/api/payments', [
            'order_id' => $order->id,
            'payment_method' => 'bitcoin',
            'amount' => 50.00,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'payment_method' => 'bitcoin',
                    'status' => 'successful',
                ],
            ]);

        $this->assertStringStartsWith('btc_ln_', $response->json('data.transaction_id'));
    }
}
