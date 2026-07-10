<?php

namespace App\Support\SettingsLifecycle;

use App\Settings\PublicContentSettings;
use App\Support\PublicFront\PublicFrontConfigCache;
use InvalidArgumentException;
use JsonException;

class PublicSettingsPackage
{
    public const SCHEMA_VERSION = 1;

    public function __construct(
        private readonly int $schemaVersion,
        private readonly string $generatedAt,
        private readonly string $appVersion,
        private readonly string $settingsGroup,
        private readonly string $settingsMigrationWatermark,
        private readonly array $payload,
        private readonly string $checksum,
    ) {}

    public static function fromCurrentSettings(): self
    {
        $settings = app(PublicContentSettings::class);
        $payload = $settings->getRepository()->getPropertiesInGroup(PublicContentSettings::group());
        $checksum = self::payloadChecksum($payload);

        return new self(
            schemaVersion: self::SCHEMA_VERSION,
            generatedAt: now()->toIso8601String(),
            appVersion: (string) config('app.version', app()->version()),
            settingsGroup: PublicContentSettings::group(),
            settingsMigrationWatermark: app(PublicFrontConfigCache::class)->settingsMigrationWatermark(),
            payload: $payload,
            checksum: $checksum,
        );
    }

    public static function fromArray(array $package): self
    {
        $package = app(SettingsPackageUpgradePipeline::class)->upgrade($package);

        if (! is_array($package['payload'] ?? null)) {
            throw new InvalidArgumentException('Settings package payload must be an array.');
        }

        return new self(
            schemaVersion: (int) ($package['schema_version'] ?? 0),
            generatedAt: (string) ($package['generated_at'] ?? ''),
            appVersion: (string) ($package['app_version'] ?? ''),
            settingsGroup: (string) ($package['settings_group'] ?? ''),
            settingsMigrationWatermark: (string) ($package['settings_migration_watermark'] ?? ''),
            payload: $package['payload'],
            checksum: (string) ($package['checksum'] ?? ''),
        );
    }

    public function schemaVersion(): int
    {
        return $this->schemaVersion;
    }

    public function settingsGroup(): string
    {
        return $this->settingsGroup;
    }

    public function settingsMigrationWatermark(): string
    {
        return $this->settingsMigrationWatermark;
    }

    public function payload(): array
    {
        return $this->payload;
    }

    public function checksum(): string
    {
        return $this->checksum;
    }

    public function payloadHash(): string
    {
        return self::payloadChecksum($this->payload);
    }

    public function checksumValid(): bool
    {
        return hash_equals($this->payloadHash(), $this->checksum);
    }

    public function toArray(): array
    {
        return [
            'schema_version' => $this->schemaVersion,
            'generated_at' => $this->generatedAt,
            'app_version' => $this->appVersion,
            'settings_group' => $this->settingsGroup,
            'settings_migration_watermark' => $this->settingsMigrationWatermark,
            'payload' => $this->payload,
            'checksum' => $this->checksum,
        ];
    }

    /**
     * @throws JsonException
     */
    public function toJson(): string
    {
        return self::json($this->toArray());
    }

    public static function payloadChecksum(array $payload): string
    {
        return hash('sha256', self::canonicalPayloadJson($payload));
    }

    public static function canonicalPayloadJson(array $payload): string
    {
        return self::json(self::sortKeysRecursively($payload));
    }

    private static function sortKeysRecursively(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(self::sortKeysRecursively(...), $value);
        }

        ksort($value);

        return array_map(self::sortKeysRecursively(...), $value);
    }

    /**
     * @throws JsonException
     */
    private static function json(mixed $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
