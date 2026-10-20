<?php

namespace Tests\Unit;

use App\Exceptions\UnsupportedPaymentGatewayException;
use App\Models\Order;
use App\Models\PaymentGateway as PaymentGatewayModel;
use App\Services\Payment\Contracts\PaymentGatewayInterface;
use App\Services\Payment\DTOs\PaymentResult;
use App\Services\Payment\Gateways\AbstractPaymentGateway;
use App\Services\Payment\Gateways\CreditCardPaymentGateway;
use App\Services\Payment\Gateways\PayPalPaymentGateway;
use App\Services\Payment\Gateways\StripePaymentGateway;
use App\Services\Payment\PaymentGatewayManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentGatewayManagerTest extends TestCase
{
    use RefreshDatabase;

    private PaymentGatewayManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = app(PaymentGatewayManager::class);
    }

    public function test_resolves_default_credit_card_gateway(): void
    {
        $gateway = $this->manager->gateway('credit_card');

        $this->assertInstanceOf(CreditCardPaymentGateway::class, $gateway);
        $this->assertEquals('credit_card', $gateway->getName());
    }

    public function test_resolves_paypal_gateway(): void
    {
        $gateway = $this->manager->gateway('paypal');

        $this->assertInstanceOf(PayPalPaymentGateway::class, $gateway);
        $this->assertEquals('paypal', $gateway->getName());
    }

    public function test_resolves_stripe_gateway(): void
    {
        $gateway = $this->manager->gateway('stripe');

        $this->assertInstanceOf(StripePaymentGateway::class, $gateway);
        $this->assertEquals('stripe', $gateway->getName());
    }

    public function test_throws_exception_for_unsupported_gateway(): void
    {
        $this->expectException(UnsupportedPaymentGatewayException::class);

        $this->manager->gateway('non_existent_gateway');
    }

    public function test_can_dynamically_register_new_gateway_using_strategy_pattern(): void
    {
        // Custom gateway implementing PaymentGatewayInterface
        $customGateway = new class extends AbstractPaymentGateway
        {
            public function getName(): string
            {
                return 'crypto';
            }

            public function getDisplayName(): string
            {
                return 'Cryptocurrency Payment';
            }

            public function process(Order $order, float $amount, array $details = []): PaymentResult
            {
                return PaymentResult::success(
                    transactionId: 'crypto_tx_12345',
                    message: 'Crypto payment confirmed on blockchain.'
                );
            }
        };

        // Register new gateway dynamically with zero controller/core modifications
        $this->manager->register('crypto', fn () => $customGateway);

        $resolved = $this->manager->gateway('crypto');

        $this->assertInstanceOf(PaymentGatewayInterface::class, $resolved);
        $this->assertEquals('crypto', $resolved->getName());
        $this->assertEquals('Cryptocurrency Payment', $resolved->getDisplayName());
    }

    public function test_database_disabled_gateway_throws_unsupported_exception(): void
    {
        // Disable PayPal in database
        PaymentGatewayModel::create([
            'name' => 'paypal',
            'display_name' => 'PayPal Express',
            'is_active' => false,
        ]);

        $this->expectException(UnsupportedPaymentGatewayException::class);

        $this->manager->gateway('paypal');
    }

    public function test_get_available_gateways_returns_list_of_active_gateways(): void
    {
        $gateways = $this->manager->getAvailableGateways();

        $names = array_column($gateways, 'name');
        $this->assertContains('credit_card', $names);
        $this->assertContains('paypal', $names);
        $this->assertContains('stripe', $names);
    }
}
