<?php

namespace App\Services\Order;

use App\Enums\OrderStatus;
use App\Exceptions\BusinessRuleException;
use App\Exceptions\OrderDeletionException;
use App\Models\Order;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderService
{
    /**
     * Create a new order with items and calculate total.
     *
     * @param  array<string, mixed>  $data
     */
    public function createOrder(array $data, ?User $user = null): Order
    {
        return DB::transaction(function () use ($data, $user) {
            $orderNumber = 'ORD-'.date('Ymd').'-'.strtoupper(Str::random(8));

            // Calculate items subtotals and grand total
            $itemsData = $data['items'] ?? [];
            $totalAmount = 0.00;
            $preparedItems = [];

            foreach ($itemsData as $item) {
                $quantity = (int) ($item['quantity'] ?? 1);
                $price = round((float) ($item['price'] ?? 0.00), 2);
                $subtotal = round($quantity * $price, 2);
                $totalAmount += $subtotal;

                $preparedItems[] = [
                    'product_name' => $item['product_name'],
                    'quantity' => $quantity,
                    'price' => $price,
                    'subtotal' => $subtotal,
                ];
            }

            // Create Order
            $order = Order::create([
                'order_number' => $orderNumber,
                'user_id' => $user?->id ?? $data['user_id'] ?? null,
                'customer_name' => $data['customer_name'] ?? $user?->name,
                'customer_email' => $data['customer_email'] ?? $user?->email,
                'customer_phone' => $data['customer_phone'] ?? null,
                'shipping_address' => $data['shipping_address'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => OrderStatus::PENDING,
                'total_amount' => round($totalAmount, 2),
                'currency' => $data['currency'] ?? 'USD',
            ]);

            // Save items
            if (! empty($preparedItems)) {
                $order->items()->createMany($preparedItems);
            }

            return $order->load(['items', 'payments', 'user']);
        });
    }

    /**
     * Update existing order details.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws BusinessRuleException
     */
    public function updateOrder(Order $order, array $data): Order
    {
        return DB::transaction(function () use ($order, $data) {
            // If items are being modified
            if (isset($data['items'])) {
                if ($order->hasPayments()) {
                    throw new BusinessRuleException(
                        'Cannot modify order items because this order already has associated payments.'
                    );
                }

                // Delete old items and re-insert new items
                $order->items()->delete();

                $totalAmount = 0.00;
                $preparedItems = [];
                foreach ($data['items'] as $item) {
                    $quantity = (int) ($item['quantity'] ?? 1);
                    $price = round((float) ($item['price'] ?? 0.00), 2);
                    $subtotal = round($quantity * $price, 2);
                    $totalAmount += $subtotal;

                    $preparedItems[] = [
                        'product_name' => $item['product_name'],
                        'quantity' => $quantity,
                        'price' => $price,
                        'subtotal' => $subtotal,
                    ];
                }

                $order->items()->createMany($preparedItems);
                $order->total_amount = round($totalAmount, 2);
            }

            // Update main order fields
            $fillableFields = [
                'customer_name',
                'customer_email',
                'customer_phone',
                'shipping_address',
                'notes',
                'status',
                'currency',
            ];

            foreach ($fillableFields as $field) {
                if (array_key_exists($field, $data)) {
                    $order->{$field} = $data[$field];
                }
            }

            $order->save();

            return $order->load(['items', 'payments', 'user']);
        });
    }

    /**
     * Delete an order.
     *
     * Business Rule: Orders cannot be deleted if they have associated payments.
     *
     *
     * @throws OrderDeletionException
     */
    public function deleteOrder(Order $order): bool
    {
        if ($order->hasPayments()) {
            throw new OrderDeletionException(
                "Order #{$order->order_number} cannot be deleted because it has associated payments."
            );
        }

        return DB::transaction(function () use ($order) {
            $order->items()->delete();

            return (bool) $order->delete();
        });
    }

    /**
     * View orders with filtering and pagination.
     *
     * @param  array<string, mixed>  $filters
     */
    public function getOrders(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Order::query()->with(['items', 'payments', 'user']);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (! empty($filters['search'])) {
            $search = '%'.$filters['search'].'%';
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', $search)
                    ->orWhere('customer_name', 'like', $search)
                    ->orWhere('customer_email', 'like', $search);
            });
        }

        return $query->latest('id')->paginate($perPage);
    }
}
