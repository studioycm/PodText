<?php

namespace App\Filament\Forms\Components;

use App\Enums\PublicationStatus;
use App\Support\Publication\PublicationDateAutofill;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

/**
 * The one home for choosing a publication status, shared by episodes,
 * podcasts, transcriptions, the workspace and the inline create modals.
 *
 * Two grouped buttons rather than a select: the question has exactly two
 * answers, and a dropdown spent two clicks and a menu on it. The colours are
 * not restated here — ToggleButtons falls back to the enum when none are
 * given, and PublicationStatus implements HasColor (draft grey, published
 * green), so this control and the table badge cannot drift apart.
 */
class PublicationStatusField
{
    public static function make(string $name = 'status', string $publishedAtField = 'published_at'): ToggleButtons
    {
        return ToggleButtons::make($name)
            ->options(PublicationStatus::class)
            ->grouped()
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
