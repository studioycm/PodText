<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ImportConnectionProvider: string implements HasLabel
{
    case GoogleDrive = 'google_drive';
    case Spotify = 'spotify';
    case Manual = 'manual';

    public function getLabel(): string
    {
        return __("admin.importer.providers.{$this->value}");
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $provider): array => [$provider->value => $provider->getLabel()])
            ->all();
    }

    /** The one home of "a null imports.provider reads as manual" (legacy pre-column rows). */
    public static function fromImportValue(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::Manual;
    }

    public function defaultAuthType(): ImportConnectionAuthType
    {
        return match ($this) {
            self::GoogleDrive => ImportConnectionAuthType::ServiceAccount,
            self::Spotify => ImportConnectionAuthType::ClientCredentials,
            self::Manual => ImportConnectionAuthType::None,
        };
    }

    /**
     * @return array<int, ImportConnectionAuthType>
     */
    public function authTypes(): array
    {
        return match ($this) {
            self::GoogleDrive => [
                ImportConnectionAuthType::ServiceAccount,
                ImportConnectionAuthType::OAuth,
            ],
            self::Spotify => [
                ImportConnectionAuthType::ClientCredentials,
            ],
            self::Manual => [
                ImportConnectionAuthType::None,
            ],
        };
    }

    /**
     * @return array<string, string>
     */
    public function authTypeOptions(): array
    {
        return collect($this->authTypes())
            ->mapWithKeys(fn (ImportConnectionAuthType $authType): array => [$authType->value => $authType->getLabel()])
            ->all();
    }
}
