<?php

namespace App\Support\PublicFront;

class PublicFrontConfigResult
{
    /**
     * @param  array<string, mixed>  $config
     * @param  array<PublicFrontInvalidConfig>  $invalidConfig
     */
    public function __construct(
        private readonly array $config,
        private readonly array $invalidConfig = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function config(): array
    {
        return $this->config;
    }

    /**
     * @return array<string, mixed>
     */
    public function group(string $key): array
    {
        $group = $this->config[$key] ?? [];

        return is_array($group) ? $group : [];
    }

    /**
     * @return array<PublicFrontInvalidConfig>
     */
    public function invalidConfig(): array
    {
        return $this->invalidConfig;
    }

    /**
     * @return array<array{path: string, reason: string, value_preview: string}>
     */
    public function invalidConfigArray(): array
    {
        return array_map(
            fn (PublicFrontInvalidConfig $invalidConfig): array => $invalidConfig->toArray(),
            $this->invalidConfig,
        );
    }

    public function hasInvalidConfig(): bool
    {
        return $this->invalidConfig !== [];
    }
}
