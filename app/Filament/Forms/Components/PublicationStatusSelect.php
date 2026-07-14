<?php

namespace App\Filament\Forms\Components;

use App\Enums\PublicationStatus;
use App\Support\Publication\PublicationDateAutofill;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

class PublicationStatusSelect
{
    public static function make(string $name = 'status', string $publishedAtField = 'published_at'): Select
    {
        return Select::make($name)
            ->options(PublicationStatus::class)
            ->preload()
            ->default(PublicationStatus::Draft->value)
            ->live()
            ->afterStateUpdated(function (Set $set, Get $get, mixed $state) use ($publishedAtField): void {
                $publishedAt = PublicationDateAutofill::valueFor($state, $get($publishedAtField));

                if ($publishedAt === $get($publishedAtField)) {
                    return;
                }

                $set($publishedAtField, $publishedAt);
            })
            ->required();
    }
}
