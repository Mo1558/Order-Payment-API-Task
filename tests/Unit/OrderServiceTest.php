<?php

namespace Tests\Unit;

use App\Enums\OrderStatus;
use App\Exceptions\BusinessRuleException;
use App\Exceptions\OrderDeletionException;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Order\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderServiceTest extends TestCase
{
    use RefreshDatabase;

    private OrderService $orderService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->orderService = app(OrderService::class);
    }

    public function test_creates_order_and_calculates_total_correctly(): void
    {
        $data = [
            'customer_name' => 'John Doe',
            'customer_email' => 'john@example.com',
            'items' => [
                ['product_name' => 'Item A', 'quantity' => 2, 'price' => 50.00], // 100.00
                ['product_name' => 'Item B', 'quantity' => 3, 'price' => 15.50], // 46.50
            ],
        ];

        $order = $this->orderService->createOrder($data);

        $this->assertInstanceOf(Order::class, $order);
        $this->assertEquals(OrderStatus::PENDING, $order->status);
        $this->assertEquals(146.50, (float) $order->total_amount);
        $this->assertCount(2, $order->items);
        $this->assertEquals(100.00, (float) $order->items[0]->subtotal);
        $this->assertEquals(46.50, (float) $order->items[1]->subtotal);
    }

    public function test_cannot_delete_order_with_associated_payments(): void
    {
        $order = Order::factory()->confirmed()->create();
        Payment::factory()->create(['order_id' => $order->id]);

        $this->expectException(OrderDeletionException::class);
        $this->orderService->deleteOrder($order);
    }

    public function test_can_delete_order_without_associated_payments(): void
    {
        $order = Order::factory()->withItems(2)->create();
        $orderId = $order->id;

        $result = $this->orderService->deleteOrder($order);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('orders', ['id' => $orderId]);
        $this->assertDatabaseMissing('order_items', ['order_id' => $orderId]);
    }

    public function test_cannot_modify_items_if_order_has_payments(): void
    {
        $order = Order::factory()->confirmed()->create(['total_amount' => 100.00]);
        Payment::factory()->create(['order_id' => $order->id]);

        $this->expectException(BusinessRuleException::class);

        $this->orderService->updateOrder($order, [
            'items' => [
                ['product_name' => 'New Item', 'quantity' => 1, 'price' => 200.00],
            ],
        ]);
    }
}
