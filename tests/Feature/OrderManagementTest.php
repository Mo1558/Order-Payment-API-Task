<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_can_create_order_with_items_and_calculated_total(): void
    {
        $payload = [
            'customer_name' => 'Alice Cooper',
            'customer_email' => 'alice@music.com',
            'customer_phone' => '+1-555-8888',
            'shipping_address' => '77 Sunset Strip, Los Angeles, CA',
            'notes' => 'Handle with care.',
            'items' => [
                ['product_name' => 'Studio Headphones', 'quantity' => 2, 'price' => 75.00],
                ['product_name' => 'Audio Cable 3m', 'quantity' => 1, 'price' => 15.50],
            ],
        ];

        $response = $this->actingAsJwt($this->user)->postJson('/api/orders', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Order created successfully.',
                'data' => [
                    'customer_name' => 'Alice Cooper',
                    'customer_email' => 'alice@music.com',
                    'status' => 'pending',
                    'total_amount' => 165.50,
                ],
            ])
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'order_number',
                    'items' => [
                        '*' => ['id', 'product_name', 'quantity', 'price', 'subtotal'],
                    ],
                ],
            ]);

        $this->assertDatabaseHas('orders', [
            'customer_name' => 'Alice Cooper',
            'total_amount' => 165.50,
            'status' => 'pending',
        ]);
        $this->assertDatabaseHas('order_items', ['product_name' => 'Studio Headphones', 'subtotal' => 150.00]);
        $this->assertDatabaseHas('order_items', ['product_name' => 'Audio Cable 3m', 'subtotal' => 15.50]);
    }

    public function test_order_creation_validation_fails_without_items(): void
    {
        $response = $this->actingAsJwt($this->user)->postJson('/api/orders', [
            'customer_name' => 'Alice Cooper',
            'customer_email' => 'alice@music.com',
            'items' => [],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['items']);
    }

    public function test_can_view_orders_with_pagination(): void
    {
        Order::factory()->count(20)->create();

        $response = $this->actingAsJwt($this->user)->getJson('/api/orders?per_page=10');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
                'pagination' => [
                    'current_page',
                    'per_page',
                    'total',
                    'last_page',
                ],
            ]);

        $this->assertCount(10, $response->json('data'));
        $this->assertEquals(20, $response->json('pagination.total'));
    }

    public function test_can_filter_orders_by_status(): void
    {
        Order::factory()->count(3)->create(['status' => OrderStatus::PENDING]);
        Order::factory()->count(2)->confirmed()->create();
        Order::factory()->count(1)->cancelled()->create();

        $response = $this->actingAsJwt($this->user)->getJson('/api/orders?status=confirmed');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
        foreach ($response->json('data') as $order) {
            $this->assertEquals('confirmed', $order['status']);
        }
    }

    public function test_can_view_single_order(): void
    {
        $order = Order::factory()->withItems(2)->create();

        $response = $this->actingAsJwt($this->user)->getJson("/api/orders/{$order->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                ],
            ])
            ->assertJsonStructure([
                'data' => ['items', 'payments'],
            ]);
    }

    public function test_returns_404_for_non_existent_order(): void
    {
        $response = $this->actingAsJwt($this->user)->getJson('/api/orders/99999');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Order not found.',
            ]);
    }

    public function test_can_update_order_details(): void
    {
        $order = Order::factory()->create([
            'status' => OrderStatus::PENDING,
            'customer_name' => 'Original Name',
        ]);

        $response = $this->actingAsJwt($this->user)->putJson("/api/orders/{$order->id}", [
            'customer_name' => 'Updated Name',
            'status' => 'confirmed',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'customer_name' => 'Updated Name',
                    'status' => 'confirmed',
                ],
            ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'customer_name' => 'Updated Name',
            'status' => 'confirmed',
        ]);
    }

    public function test_can_delete_order_without_payments(): void
    {
        $order = Order::factory()->withItems(1)->create();
        $orderId = $order->id;

        $response = $this->actingAsJwt($this->user)->deleteJson("/api/orders/{$orderId}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Order successfully deleted.',
            ]);

        $this->assertDatabaseMissing('orders', ['id' => $orderId]);
    }

    public function test_cannot_delete_order_with_associated_payments(): void
    {
        $order = Order::factory()->confirmed()->create();
        Payment::factory()->create(['order_id' => $order->id]);

        $response = $this->actingAsJwt($this->user)->deleteJson("/api/orders/{$order->id}");

        // Business rule check: returns 422
        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);

        $this->assertStringContainsString('cannot be deleted', strtolower($response->json('message')));
        $this->assertDatabaseHas('orders', ['id' => $order->id]);
    }
}
