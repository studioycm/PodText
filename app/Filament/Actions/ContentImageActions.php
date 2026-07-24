<?php

namespace App\Filament\Actions;

use App\Enums\MediaAttachmentRole;
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
use App\Support\Media\MediaAttachmentFormState;
use App\Support\Media\OwnerImagePresentation;
use App\Support\Media\OwnerImagePresenter;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\View as SchemaView;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;

class ContentImageActions
{
    public static function contentGroupCover(): Action
    {
        return self::imagePickerAction(
            name: 'chooseContentGroupCover',
            field: 'cover_media_reference_key',
            family: ImageFileNamer::CONTENT_GROUP_COVER,
            role: MediaAttachmentRole::Cover,
            label: __('admin.actions.add_replace_image'),
            helper: __('admin.helpers.cover_path'),
            successTitle: __('admin.notifications.content_group_cover_saved'),
        );
    }

    public static function contentGroupCoverDetails(): Action
    {
        return self::imagePickerAction(
            name: 'contentGroupCoverDetails',
            field: 'cover_media_reference_key',
            family: ImageFileNamer::CONTENT_GROUP_COVER,
            role: MediaAttachmentRole::Cover,
            label: __('admin.owner_image.actions.open_details'),
            helper: __('admin.helpers.cover_path'),
            successTitle: __('admin.notifications.content_group_cover_saved'),
        );
    }

    public static function contentItemImage(): Action
    {
        return self::imagePickerAction(
            name: 'chooseContentItemImage',
            field: 'primary_image_media_reference_key',
            family: ImageFileNamer::CONTENT_ITEM_IMAGE,
            role: MediaAttachmentRole::PrimaryImage,
            label: __('admin.actions.add_replace_image'),
            helper: __('admin.helpers.content_item_image_path'),
            successTitle: __('admin.notifications.content_item_image_saved'),
        );
    }

    public static function contentItemImageDetails(): Action
    {
        return self::imagePickerAction(
            name: 'contentItemImageDetails',
            field: 'primary_image_media_reference_key',
            family: ImageFileNamer::CONTENT_ITEM_IMAGE,
            role: MediaAttachmentRole::PrimaryImage,
            label: __('admin.owner_image.actions.open_details'),
            helper: __('admin.helpers.content_item_image_path'),
            successTitle: __('admin.notifications.content_item_image_saved'),
        );
    }

    public static function downloadExternalImage(bool $overwrite = false): Action
    {
        return Action::make($overwrite ? 'downloadExternalImageOverwrite' : 'downloadExternalImage')
            ->label($overwrite ? __('admin.actions.download_external_image_overwrite') : __('admin.actions.download_external_image'))
            ->icon($overwrite ? Heroicon::OutlinedArrowPath : Heroicon::OutlinedCloudArrowDown)
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

                self::queueExternalImage($record, $user, $overwrite);
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
        MediaAttachmentRole $role,
        string $label,
        string $helper,
        string $successTitle,
    ): Action {
        $presentations = [];
        $presentationFor = static function (ContentGroup|ContentItem $record) use (&$presentations, $role): OwnerImagePresentation {
            $key = implode(':', [$record::class, $record->getKey(), $role->value]);

            return $presentations[$key] ??= app(OwnerImagePresenter::class)->present($record, $role);
        };
        $picker = MediaPickerField::make($field, $family)
            ->label($label)
            ->helperText(fn (ContentGroup|ContentItem $record): string => app(MediaAttachmentFormState::class)->diagnostic($record, $role) !== null
                ? __('admin.helpers.unsafe_legacy_media_repair')
                : $helper)
            ->inlineOwnerWorkspace()
            ->columnSpanFull();

        $action = Action::make($name)
            ->label($label)
            ->icon(Heroicon::OutlinedPhoto)
            ->modalHeading(__('admin.owner_image.heading'))
            ->modalDescription(__('admin.owner_image.description'))
            ->modalWidth(Width::FiveExtraLarge)
            ->modalSubmitActionLabel(__('admin.owner_image.actions.change_image'))
            ->fillForm(function (ContentGroup|ContentItem $record) use ($field, $presentationFor, $role): array {
                $presentation = $presentationFor($record);

                return [
                    $field => app(MediaAttachmentFormState::class)->pickerIdentity($record, $role),
                    'legacy_media_repair_fingerprint' => $presentation->unsafeFingerprint,
                    'expected_media_id' => $presentation->expectedMediaId,
                    'expected_legacy_path' => $presentation->expectedLegacyPath,
                    'can_remove_direct' => $presentation->canRemoveDirect,
                    'can_import_external' => $presentation->canImportExternal,
                ];
            })
            ->schema([
                Hidden::make('legacy_media_repair_fingerprint'),
                Hidden::make('expected_media_id'),
                Hidden::make('expected_legacy_path'),
                Hidden::make('can_remove_direct'),
                Hidden::make('can_import_external'),
                Tabs::make(__('admin.owner_image.heading'))
                    ->tabs([
                        Tab::make(__('admin.owner_image.tabs.replace'))
                            ->icon(Heroicon::OutlinedPhoto)
                            ->extraAttributes([
                                'data-testid' => 'owner-image-tab-replace',
                            ])
                            ->schema([
                                $picker,
                                $picker->getInlineWorkspaceComponent(),
                            ]),
                        Tab::make(__('admin.owner_image.tabs.details'))
                            ->icon(Heroicon::OutlinedInformationCircle)
                            ->extraAttributes([
                                'data-testid' => 'owner-image-tab-details',
                            ])
                            ->schema([
                                SchemaView::make('filament.actions.current-content-image')
                                    ->viewData(fn (ContentGroup|ContentItem $record): array => [
                                        'presentation' => $presentationFor($record),
                                    ]),
                            ]),
                    ])
                    ->extraAttributes([
                        'data-testid' => 'owner-image-workspace-tabs',
                    ])
                    ->columnSpanFull(),
            ])
            ->extraModalFooterActions(fn (Action $action): array => self::ownerImageFooterActions($action))
            ->action(function (
                ContentGroup|ContentItem $record,
                array $arguments,
                array $data,
                Action $action,
            ) use ($field, $role, $successTitle): void {
                $actor = auth()->user();
                abort_unless($actor instanceof User, 403);
                $operation = is_string($arguments['operation'] ?? null)
                    ? $arguments['operation']
                    : 'change';
                $fingerprint = is_string($data['legacy_media_repair_fingerprint'] ?? null)
                    ? $data['legacy_media_repair_fingerprint']
                    : null;
                $expectedMediaId = is_numeric($data['expected_media_id'] ?? null)
                    ? (int) $data['expected_media_id']
                    : null;
                $expectedLegacyPath = is_string($data['expected_legacy_path'] ?? null)
                    ? $data['expected_legacy_path']
                    : null;
                $nestingIndex = $action->getNestingIndex() ?? 0;
                $validationField = "mountedActions.{$nestingIndex}.data.{$field}";

                if ($operation === 'remove') {
                    if ($fingerprint !== null) {
                        app(MediaAttachmentFormState::class)->detachUnsafe($record, $role, $fingerprint, $actor);
                    } else {
                        app(MediaAttachmentFormState::class)->detachDirectIfUnchanged(
                            $record,
                            $role,
                            $actor,
                            $expectedMediaId,
                            $expectedLegacyPath,
                            $validationField,
                        );
                    }

                    Notification::make()
                        ->success()
                        ->title(__('admin.owner_image.notifications.automatic_image_enabled'))
                        ->send();

                    return;
                }

                if ($operation === 'import_external') {
                    abort_unless($record instanceof ContentItem, 422);
                    self::queueExternalImage($record, $actor, overwrite: false);

                    return;
                }

                $referenceKey = is_string($data[$field] ?? null) ? $data[$field] : null;
                app(MediaAttachmentFormState::class)->persist(
                    $record,
                    $referenceKey,
                    $role,
                    $actor,
                    $fingerprint,
                    $validationField,
                    $expectedMediaId,
                    $expectedLegacyPath,
                    enforceExpectedIdentity: true,
                );

                Notification::make()
                    ->success()
                    ->title($successTitle)
                    ->send();
            });

        return self::applyConfiguredContainer($action);
    }

    public static function detachUnsafeOwnerImage(MediaAttachmentRole $role): Action
    {
        return Action::make($role === MediaAttachmentRole::Cover ? 'detachUnsafeCoverToDefault' : 'detachUnsafePrimaryImageToDefault')
            ->label(__('admin.actions.detach_unsafe_media_to_default'))
            ->icon(Heroicon::OutlinedLinkSlash)
            ->color('warning')
            ->requiresConfirmation()
            ->fillForm(fn (ContentGroup|ContentItem $record): array => [
                'legacy_media_repair_fingerprint' => app(MediaAttachmentFormState::class)->diagnostic($record, $role)?->fingerprint,
            ])
            ->schema([Hidden::make('legacy_media_repair_fingerprint')])
            ->visible(fn (ContentGroup|ContentItem $record): bool => app(MediaAttachmentFormState::class)->diagnostic($record, $role) !== null)
            ->action(function (ContentGroup|ContentItem $record, array $data) use ($role): void {
                $actor = auth()->user();
                abort_unless($actor instanceof User, 403);
                $fingerprint = $data['legacy_media_repair_fingerprint'] ?? null;
                abort_unless(is_string($fingerprint) && filled($fingerprint), 409);
                app(MediaAttachmentFormState::class)->detachUnsafe($record, $role, $fingerprint, $actor);
                Notification::make()->success()->title(__('admin.notifications.unsafe_media_detached_to_default'))->send();
            });
    }

    /**
     * @return array<int, Action>
     */
    private static function ownerImageFooterActions(Action $action): array
    {
        $data = $action->getData();
        $actions = [];

        if ((bool) ($data['can_remove_direct'] ?? false)) {
            $actions[] = $action
                ->makeModalSubmitAction('removeDirectImage', ['operation' => 'remove'])
                ->label(__('admin.owner_image.actions.use_automatic_image'))
                ->icon(Heroicon::OutlinedLinkSlash)
                ->color('warning');
        }

        if ((bool) ($data['can_import_external'] ?? false)) {
            $actions[] = $action
                ->makeModalSubmitAction('importExternalImage', ['operation' => 'import_external'])
                ->label(__('admin.owner_image.actions.import_external'))
                ->icon(Heroicon::OutlinedCloudArrowDown)
                ->color('gray');
        }

        return $actions;
    }

    private static function queueExternalImage(ContentItem $record, User $actor, bool $overwrite): void
    {
        abort_unless(filled($record->external_thumbnail_url), 422);

        DownloadExternalContentItemImage::dispatch(
            contentItemId: (int) $record->getKey(),
            userId: (int) $actor->getKey(),
            overwrite: $overwrite,
            expectedUrl: (string) $record->external_thumbnail_url,
        );

        Notification::make()
            ->success()
            ->title(__('admin.notifications.external_image_download_queued'))
            ->send();
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
