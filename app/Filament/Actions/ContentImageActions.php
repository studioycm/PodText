<?php

namespace App\Filament\Actions;

use App\Enums\MediaNamingStrategy;
use App\Enums\Tb1PickerContainer;
use App\Filament\Forms\MediaPickerField;
use App\Jobs\DownloadExternalContentItemImage;
use App\Jobs\ExportContentImagesZip;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\User;
use App\Settings\AdminUxSettings;
use App\Support\Media\ImageFileNamer;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

class ContentImageActions
{
    public static function contentGroupCover(): Action
    {
        return self::imagePickerAction(
            name: 'chooseContentGroupCover',
            field: 'cover_path',
            family: ImageFileNamer::CONTENT_GROUP_COVER,
            label: __('admin.actions.choose_cover_image'),
            helper: __('admin.helpers.cover_path'),
            successTitle: __('admin.notifications.content_group_cover_saved'),
        );
    }

    public static function contentItemImage(): Action
    {
        return self::imagePickerAction(
            name: 'chooseContentItemImage',
            field: 'image_path',
            family: ImageFileNamer::CONTENT_ITEM_IMAGE,
            label: __('admin.actions.choose_episode_image'),
            helper: __('admin.helpers.content_item_image_path'),
            successTitle: __('admin.notifications.content_item_image_saved'),
        );
    }

    public static function downloadExternalImage(bool $overwrite = false): Action
    {
        return Action::make($overwrite ? 'downloadExternalImageOverwrite' : 'downloadExternalImage')
            ->label($overwrite ? __('admin.actions.download_external_image_overwrite') : __('admin.actions.download_external_image'))
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color($overwrite ? 'warning' : 'gray')
            ->visible(fn (ContentItem $record): bool => filled($record->external_thumbnail_url)
                && ($overwrite ? filled($record->image_path) : blank($record->image_path)))
            ->requiresConfirmation($overwrite)
            ->modalHeading($overwrite ? __('admin.modals.download_external_image_overwrite') : __('admin.modals.download_external_image'))
            ->action(function (ContentItem $record) use ($overwrite): void {
                $user = auth()->user();

                if (! $user instanceof User) {
                    return;
                }

                DownloadExternalContentItemImage::dispatch(
                    contentItemId: (int) $record->getKey(),
                    userId: (int) $user->getKey(),
                    overwrite: $overwrite,
                );

                Notification::make()
                    ->success()
                    ->title(__('admin.notifications.external_image_download_queued'))
                    ->send();
            });
    }

    public static function exportContentImagesHeader(): Action
    {
        return self::exportContentImagesAction('downloadContentImages', null)
            ->label(__('admin.actions.download_content_images'))
            ->icon(Heroicon::OutlinedArchiveBoxArrowDown);
    }

    public static function exportContentImagesRecord(): Action
    {
        return self::exportContentImagesAction('downloadPodcastImages', fn (ContentGroup $record): int => (int) $record->getKey())
            ->label(__('admin.actions.download_podcast_images'))
            ->icon(Heroicon::OutlinedArchiveBoxArrowDown)
            ->color('gray');
    }

    private static function imagePickerAction(
        string $name,
        string $field,
        string $family,
        string $label,
        string $helper,
        string $successTitle,
    ): Action {
        $action = Action::make($name)
            ->label($label)
            ->icon(Heroicon::OutlinedPhoto)
            ->modalHeading($label)
            ->modalSubmitActionLabel(__('admin.actions.save'))
            ->fillForm(fn (Model $record): array => [
                $field => $record->getAttribute($field),
            ])
            ->schema([
                MediaPickerField::make($field, $family)
                    ->label($label)
                    ->helperText($helper)
                    ->columnSpanFull(),
            ])
            ->action(function (Model $record, array $data) use ($field, $successTitle): void {
                $record->update([
                    $field => $data[$field] ?? null,
                ]);

                Notification::make()
                    ->success()
                    ->title($successTitle)
                    ->send();
            });

        return self::applyConfiguredContainer($action);
    }

    private static function exportContentImagesAction(string $name, ?\Closure $contentGroupId): Action
    {
        return Action::make($name)
            ->requiresConfirmation()
            ->modalHeading(__('admin.modals.download_content_images'))
            ->modalSubmitActionLabel(__('admin.actions.download_content_images'))
            ->schema([
                Select::make('media_naming_strategy')
                    ->label(__('admin.fields.media_naming_strategy'))
                    ->helperText(__('admin.helpers.media_naming_strategy'))
                    ->options(fn (): array => collect(MediaNamingStrategy::cases())
                        ->mapWithKeys(fn (MediaNamingStrategy $strategy): array => [
                            $strategy->value => __("admin.media_naming_strategies.{$strategy->value}"),
                        ])
                        ->all())
                    ->default(fn (): string => self::defaultEgressNamingStrategy()->value)
                    ->native(false)
                    ->required(),
            ])
            ->action(function (array $data, ?ContentGroup $record = null) use ($contentGroupId): void {
                $user = auth()->user();

                if (! $user instanceof User) {
                    return;
                }

                $scopedGroupId = $contentGroupId instanceof \Closure && $record instanceof ContentGroup
                    ? $contentGroupId($record)
                    : null;

                ExportContentImagesZip::dispatch(
                    userId: (int) $user->getKey(),
                    contentGroupId: $scopedGroupId,
                    strategy: MediaNamingStrategy::fromSetting($data['media_naming_strategy'] ?? null)->value,
                );

                Notification::make()
                    ->success()
                    ->title(__('admin.notifications.content_images_export_queued'))
                    ->send();
            });
    }

    private static function applyConfiguredContainer(Action $action): Action
    {
        try {
            $container = Tb1PickerContainer::tryFrom(app(AdminUxSettings::class)->tb1_picker_container);
        } catch (\Throwable) {
            $container = Tb1PickerContainer::Modal;
        }

        return $container === Tb1PickerContainer::SlideOver
            ? $action->slideOver()
            : $action;
    }

    private static function defaultEgressNamingStrategy(): MediaNamingStrategy
    {
        try {
            return MediaNamingStrategy::fromSetting(app(AdminUxSettings::class)->media_naming_strategy);
        } catch (\Throwable) {
            return MediaNamingStrategy::Slug;
        }
    }
}
