<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Seed Default Demo User
        $user = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Demo Admin',
                'password' => Hash::make('password123'),
                'email_verified_at' => now(),
            ]
        );

        // 2. Seed Supported Payment Gateways
        $gateways = [
            [
                'name' => 'credit_card',
                'display_name' => 'Credit / Debit Card',
                'is_active' => true,
                'config' => [
                    'mode' => 'sandbox',
                    'accepted_brands' => ['visa', 'mastercard', 'amex'],
                ],
            ],
            [
                'name' => 'paypal',
                'display_name' => 'PayPal Express Checkout',
                'is_active' => true,
                'config' => [
                    'mode' => 'sandbox',
                    'client_id' => 'sb_client_id_mock_123',
                ],
            ],
            [
                'name' => 'stripe',
                'display_name' => 'Stripe Payments',
                'is_active' => true,
                'config' => [
                    'mode' => 'test',
                    'publishable_key' => 'pk_test_mock_123',
                ],
            ],
        ];

        foreach ($gateways as $gw) {
            PaymentGateway::updateOrCreate(['name' => $gw['name']], $gw);
        }

        // 3. Seed Sample Orders with Items and Payments

        // Order 1: Pending (Cannot be paid yet according to business rule)
        $orderPending = Order::create([
            'order_number' => 'ORD-20261020-0001',
            'user_id' => $user->id,
            'customer_name' => 'Alice Johnson',
            'customer_email' => 'alice@example.com',
            'customer_phone' => '+1-555-0101',
            'shipping_address' => '123 Market St, San Francisco, CA',
            'notes' => 'Please leave at the front door.',
            'status' => OrderStatus::PENDING,
            'total_amount' => 179.98,
            'currency' => 'USD',
        ]);
        $orderPending->items()->createMany([
            ['product_name' => 'Mechanical Keyboard RGB', 'quantity' => 1, 'price' => 129.99, 'subtotal' => 129.99],
            ['product_name' => 'Desk Mousepad XL', 'quantity' => 2, 'price' => 24.995, 'subtotal' => 49.99],
        ]);

        // Order 2: Confirmed (Ready for payment)
        $orderConfirmed = Order::create([
            'order_number' => 'ORD-20261020-0002',
            'user_id' => $user->id,
            'customer_name' => 'Bob Smith',
            'customer_email' => 'bob@example.com',
            'customer_phone' => '+1-555-0202',
            'shipping_address' => '456 Tech Ave, Austin, TX',
            'notes' => 'Expedited shipping requested.',
            'status' => OrderStatus::CONFIRMED,
            'total_amount' => 299.00,
            'currency' => 'USD',
        ]);
        $orderConfirmed->items()->createMany([
            ['product_name' => '27-inch 4K Monitor', 'quantity' => 1, 'price' => 299.00, 'subtotal' => 299.00],
        ]);

        // Order 3: Confirmed & Paid (Cannot be deleted because of payments)
        $orderPaid = Order::create([
            'order_number' => 'ORD-20261020-0003',
            'user_id' => $user->id,
            'customer_name' => 'Carol Williams',
            'customer_email' => 'carol@example.com',
            'customer_phone' => '+1-555-0303',
            'shipping_address' => '789 Pine Rd, Seattle, WA',
            'notes' => 'Gift wrapping requested.',
            'status' => OrderStatus::CONFIRMED,
            'total_amount' => 89.50,
            'currency' => 'USD',
        ]);
        $orderPaid->items()->createMany([
            ['product_name' => 'Wireless Gaming Mouse', 'quantity' => 1, 'price' => 89.50, 'subtotal' => 89.50],
        ]);
        Payment::create([
            'payment_reference' => 'PAY-20261020-'.strtoupper(Str::random(8)),
            'order_id' => $orderPaid->id,
            'amount' => 89.50,
            'currency' => 'USD',
            'payment_method' => PaymentMethod::CREDIT_CARD->value,
            'status' => PaymentStatus::SUCCESSFUL,
            'transaction_id' => 'cc_txn_'.Str::lower(Str::random(16)),
            'gateway_response' => [
                'gateway' => 'credit_card',
                'status' => 'succeeded',
                'card_brand' => 'Visa',
                'last4' => '4242',
            ],
            'paid_at' => now(),
        ]);

        // Order 4: Cancelled
        $orderCancelled = Order::create([
            'order_number' => 'ORD-20261020-0004',
            'user_id' => $user->id,
            'customer_name' => 'David Brown',
            'customer_email' => 'david@example.com',
            'customer_phone' => '+1-555-0404',
            'shipping_address' => '321 Elm St, Denver, CO',
            'notes' => 'Customer requested cancellation.',
            'status' => OrderStatus::CANCELLED,
            'total_amount' => 45.00,
            'currency' => 'USD',
        ]);
        $orderCancelled->items()->createMany([
            ['product_name' => 'USB-C Fast Charger 65W', 'quantity' => 1, 'price' => 45.00, 'subtotal' => 45.00],
        ]);
    }
}
