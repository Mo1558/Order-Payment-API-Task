<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_number' => 'ORD-'.date('Ymd').'-'.strtoupper(Str::random(8)),
            'user_id' => User::factory(),
            'customer_name' => fake()->name(),
            'customer_email' => fake()->safeEmail(),
            'customer_phone' => fake()->phoneNumber(),
            'shipping_address' => fake()->address(),
            'notes' => fake()->sentence(),
            'status' => OrderStatus::PENDING,
            'total_amount' => 0.00,
            'currency' => 'USD',
        ];
    }

    /**
     * Indicate that the order is confirmed.
     */
    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::CONFIRMED,
        ]);
    }

    /**
     * Indicate that the order is cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::CANCELLED,
        ]);
    }

    /**
     * Configure the model factory to create items automatically.
     */
    public function withItems(int $count = 2): static
    {
        return $this->afterCreating(function (Order $order) use ($count) {
            $total = 0.00;
            for ($i = 0; $i < $count; $i++) {
                $qty = fake()->numberBetween(1, 3);
                $price = fake()->randomFloat(2, 10, 100);
                $subtotal = round($qty * $price, 2);
                $total += $subtotal;

                $order->items()->create([
                    'product_name' => fake()->words(3, true),
                    'quantity' => $qty,
                    'price' => $price,
                    'subtotal' => $subtotal,
                ]);
            }
            $order->update(['total_amount' => round($total, 2)]);
        });
    }
}
