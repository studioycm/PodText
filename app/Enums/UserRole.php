<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum UserRole: string implements HasColor, HasLabel
{
    case SuperAdmin = 'super-admin';
    case Admin = 'admin';
    case Moderator = 'moderator';
    case Transcriber = 'transcriber';
    case User = 'user';

    public function getLabel(): string
    {
        return __("admin.user_roles.{$this->value}");
    }

    public function getColor(): string
    {
        return match ($this) {
            self::SuperAdmin => 'danger',
            self::Admin => 'warning',
            self::Moderator => 'info',
            self::Transcriber => 'success',
            self::User => 'gray',
        };
    }

    public function rank(): int
    {
        return match ($this) {
            self::SuperAdmin => 500,
            self::Admin => 400,
            self::Moderator => 300,
            self::Transcriber => 200,
            self::User => 100,
        };
    }

    public function isAtLeast(self $role): bool
    {
        return $this->rank() >= $role->rank();
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $role): array => [$role->value => $role->getLabel()])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
