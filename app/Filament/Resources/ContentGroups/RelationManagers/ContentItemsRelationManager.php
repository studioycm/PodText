<?php

namespace App\Filament\Resources\ContentGroups\RelationManagers;

use App\Enums\MediaAttachmentRole;
use App\Enums\PublicationStatus;
use App\Filament\Actions\ContentImageActions;
use App\Filament\Actions\EditEffectiveTranscriptionAction;
use App\Filament\Resources\ContentItems\ContentItemResource;
use App\Filament\Resources\ContentItems\Tables\ContentItemsTable;
use App\Filament\Resources\Support\ResourceTableActions;
use App\Filament\Support\Concerns\UndoesPublicationToggle;
use App\Filament\Tables\OwnerImageColumn;
use App\Models\ContentItem;
use App\Models\User;
use App\Support\Media\MediaAttachmentFormState;
use App\Support\Media\MediaRecordScope;
use App\Support\Media\OwnerImageChangedException;
use App\Support\Media\OwnerImageChoicePresentation;
use App\Support\Media\OwnerImagePresenter;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use JsonException;
use Livewire\Attributes\Locked;

class ContentItemsRelationManager extends RelationManager
{
    use UndoesPublicationToggle;

    private const int OWNER_IMAGE_BASELINE_VERSION = 2;

    protected static string $relationship = 'contentItems';

    protected static bool $isLazy = false;

    // Filament applies deferral inside getTabComponent(), which this class
    // overrides — so the override honours the flag explicitly below.
    protected static bool $isBadgeDeferred = true;

    #[Locked]
    public ?int $ownerImageExpectedMediaId = null;

    private ?string $pendingPrimaryImageMediaReferenceKey = null;

    /**
     * @var array{expected_media_id: int|null}|null
     */
    private ?array $pendingOwnerImageBaseline = null;

    public function form(Schema $schema): Schema
    {
        return ContentItemResource::form($schema);
    }

    public function table(Table $table): Table
    {
        return ResourceTableActions::iconOnly($table)
            ->heading(__('admin.relations.episodes'))
            ->recordTitleAttribute('title')
            ->modifyQueryUsing(fn (Builder $query, HasTable $livewire): Builder => ContentItemsTable::primeEpisodeQuery($query, $livewire)
                ->with(['categories', 'tags'])
                ->latest('published_at')
                ->latest('id'))
            ->columns([
                OwnerImageColumn::contentItem(),
                TextColumn::make('title')
                    ->label(__('admin.fields.title'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('effective_type_label')
                    ->label(__('admin.fields.effective_type_label'))
                    ->state(fn (ContentItem $record): string => $record->effectiveTypeLabelSingular())
                    ->badge()
                    // R1: the public-state badge earns the default slot; the
                    // type label stays one toggle away, as on the main table.
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('effective_transcribers')
                    ->label(__('admin.fields.transcribers'))
                    ->state(fn (ContentItem $record): string => implode(', ', $record->effectiveTranscription()?->transcriberNames() ?? []))
                    ->badge()
                    ->separator(', '),
                ContentItemsTable::publicStateColumn(),
                TextColumn::make('effective_transcription_context')
                    ->label(__('admin.fields.effective_transcription'))
                    ->state(fn (ContentItem $record): ?string => EditEffectiveTranscriptionAction::contextStateFor($record))
                    ->placeholder(__('admin.labels.none'))
                    ->badge()
                    ->color(fn (ContentItem $record): string => EditEffectiveTranscriptionAction::contextColorFor($record))
                    ->toggleable(),
                TextColumn::make('categories.name')
                    ->label(__('admin.fields.categories'))
                    ->badge()
                    ->separator(', ')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('tags.name')
                    ->label(__('admin.fields.tags'))
                    ->badge()
                    ->separator(', ')
                    ->toggleable(isToggledHiddenByDefault: true),
                ContentItemsTable::statusToggleColumn(),
                ContentItemsTable::effectivePublishedAtColumn(),
                TextColumn::make('featuredTranscription.title')
                    ->label(__('admin.fields.featured_transcription'))
                    ->placeholder(__('admin.labels.none'))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('transcriptions_count')
                    ->label(__('admin.tabs.transcriptions'))
                    ->counts('transcriptions')
                    ->numeric()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('is_pinned')
                    ->label(__('admin.fields.is_pinned'))
                    ->state(fn (ContentItem $record): string => $record->isCurrentlyPinned() ? __('admin.labels.active') : __('admin.labels.inactive'))
                    ->badge()
                    ->color(fn (ContentItem $record): string => $record->isCurrentlyPinned() ? 'warning' : 'gray')
                    ->toggleable(),
                ContentItemsTable::updatedAtSinceColumn(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('admin.fields.status'))
                    ->options(PublicationStatus::class),
                SelectFilter::make('categories')
                    ->label(__('admin.fields.categories'))
                    ->relationship('categories', 'name')
                    ->multiple()
                    ->searchable()
                    ->optionsLimit(50),
                SelectFilter::make('content_tags')
                    ->label(__('admin.fields.tags'))
                    ->relationship('tags', 'name', modifyQueryUsing: fn (Builder $query): Builder => $query->where('type', 'content'))
                    ->multiple()
                    ->searchable()
                    ->optionsLimit(50),
                ContentItemsTable::pinnedToggleFilter(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label(__('admin.actions.classic_create'))
                    ->modalHeading(__('admin.modals.create_episode'))
                    ->createAnother(false)
                    ->modalWidth(Width::SevenExtraLarge)
                    ->databaseTransaction()
                    ->mutateDataUsing(function (array $data): array {
                        unset(
                            $data['relation_owner_image_baseline_token'],
                            $data['relation_owner_image_pending_identity'],
                        );

                        [$data, $this->pendingPrimaryImageMediaReferenceKey] = app(MediaAttachmentFormState::class)->prepare(
                            $data,
                            'primary_image_media_reference_key',
                            null,
                            MediaAttachmentRole::PrimaryImage,
                        );

                        return $data;
                    })
                    ->after(function (ContentItem $record): void {
                        $actor = auth()->user();
                        abort_unless($actor instanceof User, 403);

                        app(MediaAttachmentFormState::class)->persist(
                            $record,
                            $this->pendingPrimaryImageMediaReferenceKey,
                            MediaAttachmentRole::PrimaryImage,
                            $actor,
                        );
                    }),
            ])
            ->recordUrl(fn (ContentItem $record): string => ContentItemResource::getUrl('workspace', ['record' => $record]))
            ->recordActions([
                Action::make('openEpisodeWorkspace')
                    ->label(__('admin.actions.open_episode_workspace'))
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->url(fn (ContentItem $record): string => ContentItemResource::getUrl('workspace', ['record' => $record])),
                EditEffectiveTranscriptionAction::make(),
                ...ContentItemsTable::remedyActions(),
                ActionGroup::make([
                    ContentImageActions::contentItemImage(),
                    ContentImageActions::downloadExternalImage(),
                    ContentImageActions::downloadExternalImage(overwrite: true),
                    ContentItemsTable::addTranscriptionAction(),
                    EditAction::make()
                        ->label(__('admin.actions.classic_edit'))
                        ->icon(Heroicon::OutlinedDocumentText)
                        ->modalHeading(__('admin.modals.edit_episode'))
                        ->modalWidth(Width::SevenExtraLarge)
                        ->databaseTransaction()
                        ->mutateRecordDataUsing(function (array $data, ContentItem $record): array {
                            $presentation = $this->captureOwnerImageBaseline($record);
                            $pickerIdentity = app(OwnerImagePresenter::class)->pickerIdentity(
                                $record,
                                MediaAttachmentRole::PrimaryImage,
                            );
                            $data['primary_image_media_reference_key'] = $pickerIdentity;
                            $data['relation_owner_image_pending_identity'] = $pickerIdentity;
                            $data['relation_owner_image_baseline_token'] = $this->ownerImageBaselineToken(
                                $record,
                                $presentation,
                            );

                            return $data;
                        })
                        ->mutateDataUsing(function (array $data, ContentItem $record, EditAction $action): array {
                            $actor = auth()->user();
                            abort_unless($actor instanceof User, 403);
                            $this->pendingOwnerImageBaseline = $this->ownerImageBaseline(
                                $data['relation_owner_image_baseline_token'] ?? null,
                                $record,
                                $actor,
                            );
                            $rawIdentity = $data['relation_owner_image_pending_identity'] ?? null;
                            unset($data['relation_owner_image_baseline_token']);
                            unset($data['relation_owner_image_pending_identity']);

                            if ($this->pendingOwnerImageBaseline === null) {
                                $index = $action->getNestingIndex() ?? 0;
                                $action->getLivewire()->addError(
                                    "mountedActions.{$index}.data.primary_image_media_reference_key",
                                    __('admin.validation.owner_image_baseline_invalid'),
                                );
                                $action->halt(true);
                            }

                            if (
                                blank($data['primary_image_media_reference_key'] ?? null)
                                && (is_int($rawIdentity) || (is_string($rawIdentity) && ctype_digit($rawIdentity)))
                            ) {
                                $media = app(MediaRecordScope::class)->findInventoryOrFail((int) $rawIdentity);
                                $data['primary_image_media_reference_key'] = $media->reference_key;
                            }

                            [$data, $this->pendingPrimaryImageMediaReferenceKey] = app(MediaAttachmentFormState::class)->prepare(
                                $data,
                                'primary_image_media_reference_key',
                                $record,
                                MediaAttachmentRole::PrimaryImage,
                            );

                            return $data;
                        })
                        ->after(function (ContentItem $record, EditAction $action): void {
                            $actor = auth()->user();
                            abort_unless($actor instanceof User, 403);

                            try {
                                $baseline = $this->pendingOwnerImageBaseline;
                                abort_unless(is_array($baseline), 409);

                                app(MediaAttachmentFormState::class)->persist(
                                    $record,
                                    $this->pendingPrimaryImageMediaReferenceKey,
                                    MediaAttachmentRole::PrimaryImage,
                                    $actor,
                                    'primary_image_media_reference_key',
                                    $baseline['expected_media_id'],
                                    enforceExpectedIdentity: true,
                                );
                            } catch (OwnerImageChangedException) {
                                $presentation = $this->captureOwnerImageBaseline($record);
                                $livewire = $action->getLivewire();
                                $livewire->mountedActions[$action->getNestingIndex() ?? 0]['data']['relation_owner_image_baseline_token'] = $this->ownerImageBaselineToken(
                                    $record,
                                    $presentation,
                                );
                                $index = $action->getNestingIndex() ?? 0;
                                $livewire->addError(
                                    "mountedActions.{$index}.data.primary_image_media_reference_key",
                                    __('admin.validation.owner_image_changed'),
                                );
                                $action->halt(true);
                            }
                        }),
                    Action::make('openResource')
                        ->label(__('admin.actions.open_content_item_resource'))
                        ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                        ->url(fn (ContentItem $record): string => ContentItemResource::getUrl('edit', ['record' => $record]))
                        ->openUrlInNewTab(false),
                    DeleteAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getTabComponent(Model $ownerRecord, string $pageClass): Tab
    {
        return Tab::make(__('admin.relations.episodes'))
            ->icon(Heroicon::OutlinedDocumentText)
            ->badge(static fn (): string => (string) $ownerRecord->contentItems()->count())
            ->deferBadge(static::isBadgeDeferred($ownerRecord, $pageClass))
            ->badgeColor('info')
            ->badgeTooltip(__('admin.tabs.content_items_badge_tooltip'));
    }

    private function captureOwnerImageBaseline(ContentItem $record): OwnerImageChoicePresentation
    {
        $presenter = app(OwnerImagePresenter::class);
        $presenter->refresh($record, MediaAttachmentRole::PrimaryImage);
        $presentation = $presenter->choice(
            $record,
            MediaAttachmentRole::PrimaryImage,
            $presenter->pickerIdentity($record, MediaAttachmentRole::PrimaryImage),
            [
                'commit' => __('admin.actions.classic_edit'),
                'cancel' => __('admin.actions.cancel'),
            ],
        );

        $this->ownerImageExpectedMediaId = $presentation->expectedMediaId;

        return $presentation;
    }

    private function ownerImageBaselineToken(
        ContentItem $record,
        OwnerImageChoicePresentation $presentation,
    ): string {
        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);

        return Crypt::encryptString(json_encode([
            'v' => self::OWNER_IMAGE_BASELINE_VERSION,
            'action' => 'edit',
            'field' => 'primary_image_media_reference_key',
            'owner_type' => 'content_item',
            'owner_id' => (int) $record->getKey(),
            'role' => MediaAttachmentRole::PrimaryImage->value,
            'actor_id' => (int) $actor->getKey(),
            'expected_media_id' => $presentation->expectedMediaId,
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * @return array{expected_media_id: int|null}|null
     */
    private function ownerImageBaseline(mixed $token, ContentItem $record, User $actor): ?array
    {
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
        ]) {
            return null;
        }

        if (
            $payload['v'] !== self::OWNER_IMAGE_BASELINE_VERSION
            || $payload['action'] !== 'edit'
            || $payload['field'] !== 'primary_image_media_reference_key'
            || $payload['owner_type'] !== 'content_item'
            || $payload['owner_id'] !== (int) $record->getKey()
            || $payload['role'] !== MediaAttachmentRole::PrimaryImage->value
            || $payload['actor_id'] !== (int) $actor->getKey()
            || (! is_int($payload['expected_media_id']) && $payload['expected_media_id'] !== null)
        ) {
            return null;
        }

        return [
            'expected_media_id' => $payload['expected_media_id'],
        ];
    }
}
