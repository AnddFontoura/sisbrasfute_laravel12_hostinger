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
}
