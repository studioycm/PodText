<?php

namespace App\Models;

use App\Enums\ImportConnectionAuthType;
use App\Enums\ImportConnectionProvider;
use App\Enums\ImportConnectionStatus;
use Database\Factories\ImportConnectionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class ImportConnection extends Model
{
    /** @use HasFactory<ImportConnectionFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'provider',
        'auth_type',
        'credentials',
        'settings',
        'status',
        'last_tested_at',
    ];

    protected $attributes = [
        'provider' => 'manual',
        'auth_type' => 'none',
        'status' => 'untested',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $connection): void {
            $connection->credentials = self::normalizeArray($connection->credentials);
            $connection->settings = self::normalizeArray($connection->settings);
            $connection->validateProviderAuthType();
        });
    }

    protected function casts(): array
    {
        return [
            'auth_type' => ImportConnectionAuthType::class,
            'credentials' => 'encrypted:array',
            'last_tested_at' => 'datetime',
            'provider' => ImportConnectionProvider::class,
            'settings' => 'array',
            'status' => ImportConnectionStatus::class,
        ];
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings ?? [], $key, $default);
    }

    public function markTested(ImportConnectionStatus $status): void
    {
        $this->forceFill([
            'last_tested_at' => now(),
            'status' => $status,
        ])->save();
    }

    /**
     * @param  array<string, mixed>|null  $value
     * @return array<string, mixed>
     */
    public static function normalizeArray(?array $value): array
    {
        if ($value === null) {
            return [];
        }

        $normalized = [];

        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $item = self::normalizeArray($item);
            }

            if (is_string($item)) {
                $item = trim($item);
            }

            if ($item === null || $item === '' || $item === []) {
                continue;
            }

            $normalized[$key] = $item;
        }

        return Arr::undot(Arr::dot($normalized));
    }

    private function validateProviderAuthType(): void
    {
        $provider = $this->provider instanceof ImportConnectionProvider
            ? $this->provider
            : ImportConnectionProvider::tryFrom((string) $this->provider);

        $authType = $this->auth_type instanceof ImportConnectionAuthType
            ? $this->auth_type
            : ImportConnectionAuthType::tryFrom((string) $this->auth_type);

        if (! $provider || ! $authType || ! in_array($authType, $provider->authTypes(), true)) {
            throw ValidationException::withMessages([
                'auth_type' => __('admin.importer.validation.invalid_auth_type'),
            ]);
        }
    }
}
