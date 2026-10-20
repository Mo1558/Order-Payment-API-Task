<?php

namespace App\Services\Payment;

use App\Exceptions\UnsupportedPaymentGatewayException;
use App\Models\PaymentGateway as PaymentGatewayModel;
use App\Services\Payment\Contracts\PaymentGatewayInterface;
use Closure;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Schema;
use Throwable;

class PaymentGatewayManager
{
    /**
     * The application instance.
     */
    protected Application $app;

    /**
     * Custom registered gateway drivers.
     *
     * @var array<string, Closure|string|PaymentGatewayInterface>
     */
    protected array $customDrivers = [];

    /**
     * Resolved gateway instances.
     *
     * @var array<string, PaymentGatewayInterface>
     */
    protected array $instances = [];

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Resolve a payment gateway by name.
     *
     *
     * @throws UnsupportedPaymentGatewayException
     */
    public function gateway(?string $name = null): PaymentGatewayInterface
    {
        $name = $name ?: $this->getDefaultGateway();

        if (isset($this->instances[$name])) {
            return $this->instances[$name];
        }

        return $this->instances[$name] = $this->resolve($name);
    }

    /**
     * Resolve the given gateway.
     *
     *
     * @throws UnsupportedPaymentGatewayException
     */
    protected function resolve(string $name): PaymentGatewayInterface
    {
        // 1. Check custom drivers registered at runtime
        if (isset($this->customDrivers[$name])) {
            return $this->buildCustomDriver($name, $this->customDrivers[$name]);
        }

        // 2. Check config
        $config = config("payments.gateways.{$name}");
        if (! $config || ! isset($config['driver'])) {
            throw new UnsupportedPaymentGatewayException($name);
        }

        $driverClass = $config['driver'];
        if (! class_exists($driverClass) || ! is_subclass_of($driverClass, PaymentGatewayInterface::class)) {
            throw new UnsupportedPaymentGatewayException($name);
        }

        // 3. Check database configuration / overrides if available
        $dbConfig = $this->getDatabaseConfig($name);
        if ($dbConfig !== null) {
            if (isset($dbConfig['is_active']) && ! $dbConfig['is_active']) {
                throw new UnsupportedPaymentGatewayException($name);
            }
            if (! empty($dbConfig['config']) && is_array($dbConfig['config'])) {
                $config = array_merge($config, $dbConfig['config']);
            }
        }

        /** @var PaymentGatewayInterface $gateway */
        $gateway = $this->app->make($driverClass);
        $gateway->setConfiguration($config);

        if (! $gateway->isAvailable()) {
            throw new UnsupportedPaymentGatewayException($name);
        }

        return $gateway;
    }

    /**
     * Build custom driver instance.
     *
     *
     * @throws UnsupportedPaymentGatewayException
     */
    protected function buildCustomDriver(string $name, Closure|string|PaymentGatewayInterface $driver): PaymentGatewayInterface
    {
        if ($driver instanceof PaymentGatewayInterface) {
            $gateway = $driver;
        } elseif ($driver instanceof Closure) {
            $gateway = $driver($this->app);
        } elseif (is_string($driver) && class_exists($driver)) {
            $gateway = $this->app->make($driver);
        } else {
            throw new UnsupportedPaymentGatewayException($name);
        }

        if (! ($gateway instanceof PaymentGatewayInterface)) {
            throw new UnsupportedPaymentGatewayException($name);
        }

        // Apply config if exists
        $config = config("payments.gateways.{$name}", []);
        $dbConfig = $this->getDatabaseConfig($name);
        if ($dbConfig && ! empty($dbConfig['config'])) {
            $config = array_merge($config, $dbConfig['config']);
        }

        $gateway->setConfiguration($config);

        return $gateway;
    }

    /**
     * Retrieve gateway configuration from database safely.
     *
     * @return array<string, mixed>|null
     */
    protected function getDatabaseConfig(string $name): ?array
    {
        try {
            if (! Schema::hasTable('payment_gateways')) {
                return null;
            }

            /** @var PaymentGatewayModel|null $record */
            $record = PaymentGatewayModel::where('name', $name)->first();
            if (! $record) {
                return null;
            }

            return [
                'is_active' => $record->is_active,
                'config' => $record->config ?? [],
            ];
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Register a custom gateway driver.
     *
     * @return $this
     */
    public function extend(string $name, Closure|string|PaymentGatewayInterface $driver): static
    {
        $this->customDrivers[$name] = $driver;
        unset($this->instances[$name]);

        return $this;
    }

    /**
     * Alias for extend to register gateways cleanly.
     *
     * @return $this
     */
    public function register(string $name, Closure|string|PaymentGatewayInterface $driver): static
    {
        return $this->extend($name, $driver);
    }

    /**
     * Check if a gateway is supported.
     */
    public function has(string $name): bool
    {
        try {
            $this->gateway($name);

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Get list of available active gateways.
     *
     * @return array<int, array{name: string, display_name: string}>
     */
    public function getAvailableGateways(): array
    {
        $configured = array_keys(config('payments.gateways', []));
        $custom = array_keys($this->customDrivers);
        $all = array_unique(array_merge($configured, $custom));

        $available = [];
        foreach ($all as $name) {
            try {
                $gw = $this->gateway($name);
                if ($gw->isAvailable()) {
                    $available[] = [
                        'name' => $gw->getName(),
                        'display_name' => $gw->getDisplayName(),
                    ];
                }
            } catch (Throwable) {
                // Gateway not available or disabled
                continue;
            }
        }

        return $available;
    }

    /**
     * Get default gateway name.
     */
    public function getDefaultGateway(): string
    {
        return config('payments.default', 'credit_card');
    }
}
