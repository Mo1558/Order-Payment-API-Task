<?php

namespace App\Services\Payment\Gateways;

use App\Services\Payment\Contracts\PaymentGatewayInterface;

abstract class AbstractPaymentGateway implements PaymentGatewayInterface
{
    /**
     * @var array<string, mixed>
     */
    protected array $config = [];

    public function setConfiguration(array $config): static
    {
        $this->config = array_merge($this->config, $config);

        return $this;
    }

    public function getConfiguration(): array
    {
        return $this->config;
    }

    /**
     * Get a specific configuration value.
     */
    protected function config(string $key, mixed $default = null): mixed
    {
        return data_get($this->config, $key, $default);
    }

    /**
     * Check if the gateway is explicitly enabled.
     */
    public function isAvailable(): bool
    {
        return (bool) $this->config('enabled', true);
    }
}
