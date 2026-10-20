<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'payment_reference' => 'PAY-'.date('Ymd').'-'.strtoupper(Str::random(8)),
            'order_id' => Order::factory()->confirmed(),
            'amount' => fake()->randomFloat(2, 20, 200),
            'currency' => 'USD',
            'payment_method' => fake()->randomElement(PaymentMethod::values()),
            'status' => PaymentStatus::SUCCESSFUL,
            'transaction_id' => 'txn_'.Str::random(16),
            'gateway_response' => [
                'status' => 'succeeded',
                'gateway' => 'mock',
            ],
            'paid_at' => now(),
        ];
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentStatus::FAILED,
            'transaction_id' => null,
            'failure_reason' => 'Simulated card decline or gateway rejection.',
            'paid_at' => null,
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentStatus::PENDING,
            'transaction_id' => null,
            'paid_at' => null,
        ]);
    }
}
