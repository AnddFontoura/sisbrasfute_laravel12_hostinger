<?php

namespace App\Service;

use App\Models\SystemConfig;

class SystemConfigService extends BaseService
{
    private array $cache = [];

    public function get(string $key, mixed $default = null): mixed
    {
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        $config = SystemConfig::where('key', $key)->first();
        $value = $config?->value ?? $default;
        $this->cache[$key] = $value;

        return $value;
    }

    public function set(string $key, string $value): void
    {
        SystemConfig::updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );

        $this->cache[$key] = $value;
    }

    public function getFeeType(): string
    {
        return $this->get('fee_type', 'fixed');
    }

    public function getFeeValue(): int
    {
        return (int) $this->get('fee_value', '500');
    }

    /**
     * Payment gateways that can be selected in the admin panel.
     *
     * @var array<int, string>
     */
    public const PAYMENT_GATEWAYS = ['efi', 'inter', 'null'];

    /**
     * Returns the active payment gateway identifier. Defaults to EFI.
     */
    public function getPaymentGateway(string $default = 'efi'): string
    {
        $value = (string) $this->get('payment_gateway', $default);

        return in_array($value, self::PAYMENT_GATEWAYS, true) ? $value : $default;
    }

    /**
     * Persists the active payment gateway selection.
     */
    public function setPaymentGateway(string $gateway): void
    {
        if (!in_array($gateway, self::PAYMENT_GATEWAYS, true)) {
            throw new \InvalidArgumentException("Gateway de pagamento inválido: {$gateway}");
        }

        $this->set('payment_gateway', $gateway);
    }

    /**
     * Payment methods each gateway is technically capable of handling. This is
     * a fixed capability map (not admin-editable); the admin can only enable or
     * disable methods WITHIN each gateway's supported set.
     *
     * @var array<string, array<int, string>>
     */
    public const GATEWAY_SUPPORTED_METHODS = [
        'efi' => ['pix', 'boleto', 'card'],
        'inter' => ['pix', 'boleto'],
        'null' => ['pix', 'boleto', 'card'],
    ];

    /**
     * Returns the methods a given gateway is capable of handling.
     *
     * @return array<int, string>
     */
    public function getSupportedMethods(string $gateway): array
    {
        return self::GATEWAY_SUPPORTED_METHODS[$gateway] ?? [];
    }

    /**
     * Config key holding the admin-enabled methods for a given gateway.
     */
    private function enabledMethodsKey(string $gateway): string
    {
        return "payment_methods_{$gateway}";
    }

    /**
     * Returns the payment methods currently ENABLED by the admin for the given
     * gateway, intersected with what the gateway actually supports. When no
     * admin setting exists yet, every supported method is considered enabled.
     *
     * @return array<int, string>
     */
    public function getEnabledMethods(string $gateway): array
    {
        $supported = $this->getSupportedMethods($gateway);

        if ($supported === []) {
            return [];
        }

        $raw = $this->get($this->enabledMethodsKey($gateway));

        if ($raw === null || $raw === '') {
            // Default: all supported methods enabled.
            return array_values($supported);
        }

        $decoded = is_array($raw) ? $raw : json_decode((string) $raw, true);

        if (!is_array($decoded)) {
            return array_values($supported);
        }

        // Keep only values the gateway supports, preserving the supported order.
        return array_values(array_filter(
            $supported,
            fn (string $method) => in_array($method, $decoded, true)
        ));
    }

    /**
     * Persists the enabled payment methods for a gateway. Only methods the
     * gateway supports are stored; at least one method must remain enabled.
     *
     * @param array<int, string> $methods
     */
    public function setEnabledMethods(string $gateway, array $methods): void
    {
        $supported = $this->getSupportedMethods($gateway);

        if ($supported === []) {
            throw new \InvalidArgumentException("Gateway de pagamento inválido: {$gateway}");
        }

        $invalid = array_diff($methods, $supported);
        if ($invalid !== []) {
            throw new \InvalidArgumentException(
                "Métodos não suportados pelo gateway {$gateway}: " . implode(', ', $invalid)
            );
        }

        // Normalize: dedupe and keep the gateway's canonical order.
        $normalized = array_values(array_filter(
            $supported,
            fn (string $method) => in_array($method, $methods, true)
        ));

        if ($normalized === []) {
            throw new \InvalidArgumentException('Ao menos um método de pagamento deve permanecer habilitado.');
        }

        $this->set($this->enabledMethodsKey($gateway), json_encode($normalized));
    }

    /**
     * Returns the methods enabled for the currently active gateway. These are
     * the methods the frontend should offer and the backend will accept for a
     * gateway charge.
     *
     * @return array<int, string>
     */
    public function getEnabledMethodsForActiveGateway(): array
    {
        return $this->getEnabledMethods($this->getPaymentGateway());
    }

    /**
     * Environments a gateway can run against.
     *
     * @var array<int, string>
     */
    public const PAYMENT_ENVIRONMENTS = ['sandbox', 'production'];

    /**
     * Config key holding the admin-selected environment for a given gateway.
     */
    private function environmentKey(string $gateway): string
    {
        return "payment_environment_{$gateway}";
    }

    /**
     * Returns the environment (sandbox/production) selected for a gateway. When
     * no admin setting exists, falls back to the gateway's configured default
     * (config services.<gateway>.default_environment), then to "production".
     */
    public function getEnvironment(string $gateway): string
    {
        $configDefault = (string) config("services.{$gateway}.default_environment", 'production');
        $default = in_array($configDefault, self::PAYMENT_ENVIRONMENTS, true) ? $configDefault : 'production';

        $value = (string) $this->get($this->environmentKey($gateway), $default);

        return in_array($value, self::PAYMENT_ENVIRONMENTS, true) ? $value : $default;
    }

    /**
     * Persists the environment selection for a gateway.
     */
    public function setEnvironment(string $gateway, string $environment): void
    {
        if (!in_array($gateway, self::PAYMENT_GATEWAYS, true)) {
            throw new \InvalidArgumentException("Gateway de pagamento inválido: {$gateway}");
        }

        if (!in_array($environment, self::PAYMENT_ENVIRONMENTS, true)) {
            throw new \InvalidArgumentException("Ambiente inválido: {$environment}");
        }

        $this->set($this->environmentKey($gateway), $environment);
    }

    /**
     * Environment selected for the currently active gateway.
     */
    public function getActiveGatewayEnvironment(): string
    {
        return $this->getEnvironment($this->getPaymentGateway());
    }

    /**
     * Whether a given gateway method is enabled on the active gateway.
     */
    public function isMethodEnabled(string $method): bool
    {
        return in_array($method, $this->getEnabledMethodsForActiveGateway(), true);
    }
}
