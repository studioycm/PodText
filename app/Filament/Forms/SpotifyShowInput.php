<?php

namespace App\Filament\Forms;

use App\Enums\ImageUploadPurpose;
use App\Models\ContentGroup;
use App\Models\User;
use App\Support\Media\EpisodeSpotifyLookup;
use App\Support\Media\ExternalImageFailureMessage;
use App\Support\Media\MediaAcquisitionManager;
use App\Support\Slugs\HebrewSlugger;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Illuminate\Auth\Access\AuthorizationException;
use Throwable;

class SpotifyShowInput
{
    public static function make(): TextInput
    {
        return TextInput::make('spotify_show')
            ->label(__('admin.fields.spotify_show'))
            ->helperText(__('admin.helpers.spotify_show'))
            ->dehydrated(false)
            ->columnSpanFull()
            ->suffixAction(
                Action::make('fetchSpotifyShow')
                    ->label(__('admin.actions.fetch_spotify_show'))
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->modalHeading(__('admin.modals.fetch_spotify_show'))
                    ->modalSubmitActionLabel(__('admin.actions.fetch_spotify_show'))
                    ->schema([
                        Checkbox::make('overwrite_non_empty_fields')
                            ->label(__('admin.fields.spotify_overwrite_non_empty_fields'))
                            ->helperText(__('admin.helpers.spotify_overwrite_non_empty_fields')),
                    ])
                    ->action(fn (array $data, Set $set, Get $get): null => self::fetch($set, $get, $data)),
            );
    }

    /** @param array<string, mixed> $options */
    private static function fetch(Set $set, Get $get, array $options): null
    {
        try {
            $data = app(EpisodeSpotifyLookup::class)->lookupShow((string) $get('spotify_show'));
        } catch (Throwable $throwable) {
            Notification::make()
                ->danger()
                ->title(__('admin.notifications.spotify_show_lookup_failed'))
                ->body($throwable->getMessage())
                ->send();

            return null;
        }

        $overwrite = (bool) ($options['overwrite_non_empty_fields'] ?? false);

        foreach (['title', 'description_markdown'] as $field) {
            if (filled($data[$field] ?? null) && ($overwrite || blank($get($field)))) {
                $set($field, $data[$field]);
            }
        }

        if (($overwrite || blank($get('slug'))) && filled($data['title'] ?? null)) {
            $set('slug', HebrewSlugger::unique(
                (string) $data['title'],
                fn (string $slug): bool => ContentGroup::query()->where('slug', $slug)->exists(),
            ));
        }

        if (
            filled($data['thumbnail'] ?? null)
            && ($overwrite || blank($get('cover_media_reference_key')))
        ) {
            $actor = auth()->user();

            if ($actor instanceof User) {
                try {
                    $media = app(MediaAcquisitionManager::class)->acquireExternalUrl(
                        (string) $data['thumbnail'],
                        ImageUploadPurpose::ContentGroupCover,
                        $actor,
                        ['title' => $data['title'] ?? null],
                    );
                    $set('cover_media_reference_key', $media->reference_key);
                } catch (AuthorizationException $throwable) {
                    throw $throwable;
                } catch (Throwable $throwable) {
                    if (! ExternalImageFailureMessage::isExpected($throwable)) {
                        report($throwable);
                    }

                    Notification::make()
                        ->warning()
                        ->title(__('admin.notifications.spotify_image_acquisition_failed'))
                        ->body(ExternalImageFailureMessage::for($throwable))
                        ->send();
                }
            }
        }

        Notification::make()
            ->success()
            ->title(__('admin.notifications.spotify_show_lookup_filled'))
            ->send();

        return null;
    }
}
