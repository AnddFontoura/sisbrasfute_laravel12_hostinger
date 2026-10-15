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
}
