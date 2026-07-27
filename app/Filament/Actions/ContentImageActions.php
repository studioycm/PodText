<?php

namespace App\Filament\Actions;

use App\Enums\MediaAttachmentRole;
use App\Enums\MediaNamingStrategy;
use App\Filament\Forms\Components\PathCuratorPicker;
use App\Filament\Forms\MediaPickerField;
use App\Jobs\DownloadExternalContentItemImage;
use App\Jobs\ExportContentImagesZip;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\User;
use App\Settings\AdminUxSettings;
use App\Support\Media\ImageFileNamer;
use App\Support\Media\MediaAttachmentFormState;
use App\Support\Media\OwnerImageChangedException;
use App\Support\Media\OwnerImageChoicePresentation;
use App\Support\Media\OwnerImagePresentation;
use App\Support\Media\OwnerImagePresenter;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use JsonException;

class ContentImageActions
{
    private const OWNER_IMAGE_BASELINE_VERSION = 1;

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
        $presenter = app(OwnerImagePresenter::class);
        $presentationFor = static fn (ContentGroup|ContentItem $record): OwnerImagePresentation => $presenter->present($record, $role);
        $picker = MediaPickerField::make($field, $family)
            ->label(fn (ContentGroup|ContentItem $record): string => self::ownerImageHeading(
                $record,
                $role,
                $presentationFor($record),
            ))
            ->hiddenLabel()
            ->helperText(fn (ContentGroup|ContentItem $record): string => $presentationFor($record)->unsafeFingerprint !== null
                ? __('admin.helpers.unsafe_legacy_media_repair')
                : $helper)
            ->inlineOwnerWorkspace()
            ->ownerChoice(fn (
                PathCuratorPicker $component,
                ContentGroup|ContentItem $record,
            ): OwnerImageChoicePresentation => $presenter->choice(
                $record,
                $role,
                $component->getState(),
                [
                    'commit' => self::ownerImageSubmitLabel($record, $role),
                    'cancel' => __('admin.actions.cancel'),
                ],
            ))
            ->columnSpanFull();
        $inlineWorkspace = $picker->getInlineWorkspaceComponent()
            ->data(function (ContentGroup|ContentItem $record) use ($picker, $presentationFor, $role): array {
                $state = $picker->getState();
                $presentation = $presentationFor($record);

                return [
                    'purpose' => $role->purpose()->value,
                    'selectedIds' => is_int($state) || (is_string($state) && ctype_digit($state))
                        ? [(int) $state]
                        : [],
                    'isMultiple' => false,
                    'maxItems' => $picker->getMaxItems(),
                    'isOwnerChoice' => true,
                    'savedMediaId' => (int) ($presentation->media['id'] ?? 0) === $presentation->expectedMediaId
                        ? $presentation->expectedMediaId
                        : null,
                    'isInlineOwnerWorkspace' => true,
                ];
            });

        return Action::make($name)
            ->label(fn (ContentGroup|ContentItem|null $record): string => $record === null
                ? $label
                : self::ownerImageHeading($record, $role, $presentationFor($record)))
            ->icon(Heroicon::OutlinedPhoto)
            ->extraAttributes(fn (ContentGroup|ContentItem|null $record): array => [
                'data-owner-image-action' => $role->value,
                'data-owner-image-state' => $record !== null && $presentationFor($record)->hasDirectAttachment
                    ? 'change'
                    : 'add',
            ])
            ->modalHeading(fn (ContentGroup|ContentItem $record): string => self::ownerImageHeading(
                $record,
                $role,
                $presentationFor($record),
            ))
            ->modalWidth(Width::SevenExtraLarge)
            ->modalAutofocus(false)
            ->modalSubmitActionLabel(fn (ContentGroup|ContentItem $record): string => self::ownerImageSubmitLabel($record, $role))
            ->stickyModalHeader()
            ->stickyModalFooter()
            ->extraModalWindowAttributes([
                'class' => 'podtext-owner-image-modal',
                'data-owner-image-modal' => 'true',
            ])
            ->fillForm(function (ContentGroup|ContentItem $record) use ($field, $name, $presentationFor, $presenter, $role): array {
                $presentation = $presentationFor($record);

                return [
                    $field => $presenter->pickerIdentity($record, $role),
                    'owner_image_baseline_token' => self::ownerImageBaselineToken(
                        $record,
                        $role,
                        $name,
                        $field,
                        $presentation,
                    ),
                ];
            })
            ->schema([
                Hidden::make('owner_image_baseline_token'),
                $picker,
                $inlineWorkspace,
            ])
            ->action(function (
                ContentGroup|ContentItem $record,
                array $data,
                Action $action,
            ) use ($field, $name, $presenter, $role, $successTitle): void {
                $actor = auth()->user();
                abort_unless($actor instanceof User, 403);
                $nestingIndex = $action->getNestingIndex() ?? 0;
                $validationField = "mountedActions.{$nestingIndex}.data.{$field}";
                $referenceKey = is_string($data[$field] ?? null) ? $data[$field] : null;
                $baseline = self::ownerImageBaseline(
                    $data['owner_image_baseline_token'] ?? null,
                    $record,
                    $role,
                    $actor,
                    $name,
                    $field,
                );

                if ($baseline === null) {
                    $action->getLivewire()->addError(
                        $validationField,
                        __('admin.validation.owner_image_baseline_invalid'),
                    );
                    $action->halt();

                    return;
                }

                $fingerprint = $baseline['unsafe_fingerprint'];
                $expectedMediaId = $baseline['expected_media_id'];
                $expectedLegacyPath = $baseline['expected_legacy_path'];

                try {
                    if ($referenceKey === null && $expectedMediaId !== null && $fingerprint !== null) {
                        app(MediaAttachmentFormState::class)->detachUnsafe(
                            $record,
                            $role,
                            $fingerprint,
                            $actor,
                        );
                    } else {
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
                    }
                } catch (OwnerImageChangedException) {
                    $presenter->refresh($record, $role);
                    $presentation = $presenter->choice(
                        $record,
                        $role,
                        $referenceKey,
                        [
                            'commit' => self::ownerImageSubmitLabel($record, $role),
                            'cancel' => __('admin.actions.cancel'),
                        ],
                    );
                    $livewire = $action->getLivewire();
                    $livewire->mountedActions[$nestingIndex]['data']['owner_image_baseline_token'] = self::ownerImageBaselineToken(
                        $record,
                        $role,
                        $name,
                        $field,
                        $presentation,
                    );
                    $currentEvidence = collect([
                        $presentation->directMedia['label'] ?? null,
                        $presentation->directMedia['reference_key'] ?? null,
                    ])->filter()->implode(' · ');
                    $livewire->addError(
                        $validationField,
                        implode(' ', array_filter([
                            __('admin.validation.owner_image_changed'),
                            $currentEvidence,
                        ])),
                    );
                    $action->halt();
                }

                Notification::make()
                    ->success()
                    ->title($successTitle)
                    ->send();
            });
    }

    private static function ownerImageBaselineToken(
        ContentGroup|ContentItem $record,
        MediaAttachmentRole $role,
        string $action,
        string $field,
        OwnerImagePresentation|OwnerImageChoicePresentation $presentation,
    ): string {
        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);

        return Crypt::encryptString(json_encode([
            'v' => self::OWNER_IMAGE_BASELINE_VERSION,
            'action' => $action,
            'field' => $field,
            'owner_type' => $record instanceof ContentGroup ? 'content_group' : 'content_item',
            'owner_id' => (int) $record->getKey(),
            'role' => $role->value,
            'actor_id' => (int) $actor->getKey(),
            'expected_media_id' => $presentation->expectedMediaId,
            'expected_legacy_path' => $presentation->expectedLegacyPath,
            'unsafe_fingerprint' => $presentation->unsafeFingerprint,
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * @return array{expected_media_id: int|null, expected_legacy_path: string|null, unsafe_fingerprint: string|null}|null
     */
    private static function ownerImageBaseline(
        mixed $token,
        ContentGroup|ContentItem $record,
        MediaAttachmentRole $role,
        User $actor,
        string $action,
        string $field,
    ): ?array {
        if (! is_string($token) || blank($token)) {
            return null;
        }

        try {
            $payload = json_decode(
                Crypt::decryptString($token),
                true,
                flags: JSON_THROW_ON_ERROR,
            );
        } catch (DecryptException|JsonException) {
            return null;
        }

        if (! is_array($payload) || array_keys($payload) !== [
            'v',
            'action',
            'field',
            'owner_type',
            'owner_id',
            'role',
            'actor_id',
            'expected_media_id',
            'expected_legacy_path',
            'unsafe_fingerprint',
        ]) {
            return null;
        }

        if (
            $payload['v'] !== self::OWNER_IMAGE_BASELINE_VERSION
            || $payload['action'] !== $action
            || $payload['field'] !== $field
            || $payload['owner_type'] !== ($record instanceof ContentGroup ? 'content_group' : 'content_item')
            || $payload['owner_id'] !== (int) $record->getKey()
            || $payload['role'] !== $role->value
            || $payload['actor_id'] !== (int) $actor->getKey()
            || (! is_int($payload['expected_media_id']) && $payload['expected_media_id'] !== null)
            || (! is_string($payload['expected_legacy_path']) && $payload['expected_legacy_path'] !== null)
            || (! is_string($payload['unsafe_fingerprint']) && $payload['unsafe_fingerprint'] !== null)
        ) {
            return null;
        }

        return [
            'expected_media_id' => $payload['expected_media_id'],
            'expected_legacy_path' => $payload['expected_legacy_path'],
            'unsafe_fingerprint' => $payload['unsafe_fingerprint'],
        ];
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

    private static function ownerImageHeading(
        ContentGroup|ContentItem $record,
        MediaAttachmentRole $role,
        OwnerImagePresentation $presentation,
    ): string {
        return match (true) {
            $record instanceof ContentGroup && $role === MediaAttachmentRole::Cover => __(
                $presentation->hasDirectAttachment
                    ? 'admin.owner_image.actions.change_podcast_cover'
                    : 'admin.owner_image.actions.add_podcast_cover',
            ),
            $record instanceof ContentItem && $role === MediaAttachmentRole::PrimaryImage => __(
                $presentation->hasDirectAttachment
                    ? 'admin.owner_image.actions.change_episode_image'
                    : 'admin.owner_image.actions.add_episode_image',
            ),
            default => throw new \InvalidArgumentException('The media attachment role is incompatible with this owner.'),
        };
    }

    private static function ownerImageSubmitLabel(
        ContentGroup|ContentItem $record,
        MediaAttachmentRole $role,
    ): string {
        return match (true) {
            $record instanceof ContentGroup && $role === MediaAttachmentRole::Cover => __('admin.owner_image.actions.save_podcast_cover'),
            $record instanceof ContentItem && $role === MediaAttachmentRole::PrimaryImage => __('admin.owner_image.actions.save_episode_image'),
            default => throw new \InvalidArgumentException('The media attachment role is incompatible with this owner.'),
        };
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
