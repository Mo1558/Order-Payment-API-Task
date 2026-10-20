<?php

namespace App\Services\Payment\Contracts;

use App\Models\Order;
use App\Services\Payment\DTOs\PaymentResult;

interface PaymentGatewayInterface
{
    /**
     * Process payment for the given order and amount.
     *
     * @param  array<string, mixed>  $details
     */
    public function process(Order $order, float $amount, array $details = []): PaymentResult;

    /**
     * Get the gateway identifier (e.g., 'credit_card', 'paypal', 'stripe').
     */
    public function getName(): string;

    /**
     * Get the human-readable display name.
     */
    public function getDisplayName(): string;

    /**
     * Determine if this gateway is available and properly configured.
     */
    public function isAvailable(): bool;

    /**
     * Set gateway runtime configuration.
     *
     * @param  array<string, mixed>  $config
     */
    public function setConfiguration(array $config): static;

    /**
     * Get the current gateway configuration.
     *
     * @return array<string, mixed>
     */
    public function getConfiguration(): array;
}
