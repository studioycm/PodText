<?php

namespace App\Filament\Resources\ContentItems\Tables;

use App\Enums\EpisodePinScope;
use App\Enums\EpisodePublicState;
use App\Enums\PublicationStatus;
use App\Filament\Actions\ContentImageActions;
use App\Filament\Actions\EditEffectiveTranscriptionAction;
use App\Filament\Exports\ContentItemExporter;
use App\Filament\Imports\ContentItemImporter;
use App\Filament\Resources\ContentGroups\ContentGroupResource;
use App\Filament\Resources\ContentItems\ContentItemResource;
use App\Filament\Resources\Support\RelationshipOptionForms;
use App\Filament\Resources\Support\ResourceTableActions;
use App\Filament\Tables\OwnerImageColumn;
use App\Models\ContentItem;
use App\Support\Transcriptions\TranscriptionModeLabel;
use App\Support\UiFormats;
use App\Support\UiTimezone;
use Carbon\Exceptions\InvalidFormatException;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportAction;
use Filament\Actions\ExportBulkAction;
use Filament\Actions\ImportAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules\File;
use InvalidArgumentException;

class ContentItemsTable
{
    public static function configure(Table $table): Table
    {
        return ResourceTableActions::iconOnly($table)
            ->modifyQueryUsing(fn (Builder $query): Builder => self::primeEpisodeQuery($query))
            ->columns([
                OwnerImageColumn::contentItem()
                    ->toggleable(),
                TextColumn::make('title')
                    ->label(__('admin.fields.title'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('contentGroup.title')
                    ->label(__('admin.fields.content_group'))
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                self::publicStateColumn(),
                TextColumn::make('effective_transcription_context')
                    ->label(__('admin.fields.effective_transcription'))
                    ->state(fn (ContentItem $record): ?string => EditEffectiveTranscriptionAction::contextStateFor($record))
                    ->placeholder(__('admin.labels.none'))
                    ->badge()
                    ->color(fn (ContentItem $record): string => EditEffectiveTranscriptionAction::contextColorFor($record))
                    // Context, not a daily answer: «מצב ציבורי» already says
                    // when the transcript is what blocks the episode.
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('effective_transcribers')
                    ->label(__('admin.fields.transcribers'))
                    ->state(fn (ContentItem $record): string => self::effectiveTranscriberNames($record))
                    ->badge()
                    ->separator(', ')
                    ->toggleable(isToggledHiddenByDefault: true),
                self::statusSelectColumn(),
                self::effectivePublishedAtColumn(),
                self::updatedAtSinceColumn(),
                TextColumn::make('effective_type_label')
                    ->label(__('admin.fields.effective_type_label'))
                    ->state(fn (ContentItem $record): string => $record->effectiveTypeLabelSingular())
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),
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
                TextColumn::make('duration_seconds')
                    ->label(__('admin.fields.duration_seconds'))
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('is_pinned')
                    ->label(__('admin.fields.is_pinned'))
                    ->state(fn (ContentItem $record): string => $record->isCurrentlyPinned() ? __('admin.labels.active') : __('admin.labels.inactive'))
                    ->badge()
                    ->color(fn (ContentItem $record): string => $record->isCurrentlyPinned() ? 'warning' : 'gray')
                    // The pinned tab and filter answer this now.
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('pin_order')
                    ->label(__('admin.fields.pin_order'))
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('featuredTranscription.title')
                    ->label(__('admin.fields.featured_transcription'))
                    ->placeholder(__('admin.labels.none'))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('embed_provider')
                    ->label(__('admin.fields.embed_provider'))
                    ->badge()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('slug')
                    ->label(__('admin.fields.slug'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('media_url')
                    ->label(__('admin.fields.media_url'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('embed_url')
                    ->label(__('admin.fields.embed_url'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('reference_key')
                    ->label(__('admin.fields.reference_key'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->reorderableColumns()
            ->defaultSort('updated_at', 'desc')
            ->groups([
                Group::make('contentGroup.title')
                    ->label(__('admin.fields.content_group'))
                    ->collapsible(),
                Group::make('status')
                    ->label(__('admin.fields.status'))
                    ->collapsible(),
            ])
            ->filters([
                SelectFilter::make('content_group_id')
                    ->label(__('admin.fields.content_group'))
                    ->relationship('contentGroup', 'title')
                    ->searchable()
                    ->optionsLimit(50),
                self::statusToggleFilter(),
                // The date range is two columns wide, so it sits third to
                // pack the four-per-row grid into exactly two tidy rows.
                self::publishedBetweenFilter(),
                self::pinnedToggleFilter(),
                SelectFilter::make('transcriber_id')
                    ->label(__('admin.fields.transcribers'))
                    ->relationship('transcriptions.authors', 'name')
                    ->searchable()
                    ->optionsLimit(50),
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
            ], layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(4)
            ->recordUrl(fn (ContentItem $record): string => ContentItemResource::getUrl('workspace', ['record' => $record]))
            ->recordActions([
                Action::make('openEpisodeWorkspace')
                    ->label(__('admin.actions.open_episode_workspace'))
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->url(fn (ContentItem $record): string => ContentItemResource::getUrl('workspace', ['record' => $record])),
                EditEffectiveTranscriptionAction::make(),
                ...self::remedyActions(),
                ActionGroup::make([
                    self::addTranscriptionAction(),
                    ContentImageActions::contentItemImage(),
                    ContentImageActions::downloadExternalImage(),
                    ContentImageActions::downloadExternalImage(overwrite: true),
                    self::editPodcastAction(),
                    EditAction::make()
                        ->label(__('admin.actions.classic_edit'))
                        ->icon(Heroicon::OutlinedDocumentText),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    ExportBulkAction::make()
                        ->exporter(ContentItemExporter::class)
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Import and export live in the page header beside «פרק חדש», not in the
     * table's own header strip: they are page-level intake, not row work.
     *
     * @return array<int, Action>
     */
    public static function intakeActions(): array
    {
        return [
            ImportAction::make()
                ->importer(ContentItemImporter::class)
                ->maxRows(1000)
                ->chunkSize(10)
                ->fileRules([File::types(['csv', 'txt'])->max(10240)]),
            ExportAction::make()
                ->exporter(ContentItemExporter::class)
                ->maxRows(10000),
        ];
    }

    public static function primeEpisodeQuery(Builder $query): Builder
    {
        return $query
            ->with([
                'contentGroup.coverMediaAttachment.media',
                'featuredTranscription.authors',
                'featuredTranscription.author',
                'latestPublishedTranscription.authors',
                'latestPublishedTranscription.author',
                'primaryImageMediaAttachment.media',
            ])
            ->withCount('transcriptions')
            ->withExists([
                // The verdict judges prerequisites at the later of now and the
                // air time, so both flags are primed and the resolver picks
                // the one its row needs — portable, and no per-row query.
                'transcriptions as has_transcription_now' => fn (Builder $transcriptions): Builder => $transcriptions
                    ->releasedBy(now()),
                'transcriptions as has_transcription_by_air_time' => fn (Builder $transcriptions): Builder => $transcriptions
                    ->releasedBy('content_items.published_at'),
            ]);
    }

    public static function publicStateColumn(): TextColumn
    {
        return TextColumn::make('public_state')
            ->label(__('admin.fields.public_state'))
            ->state(fn (ContentItem $record): EpisodePublicState => EpisodePublicState::for($record))
            ->badge()
            ->tooltip(fn (ContentItem $record): ?string => EpisodePublicState::for($record) === EpisodePublicState::Scheduled
                ? __('admin.episode_public_state.scheduled_tooltip', [
                    'date' => $record->published_at?->timezone(UiTimezone::name())->format(UiFormats::dateTime()),
                ])
                : null)
            ->toggleable();
    }

    public static function statusSelectColumn(): SelectColumn
    {
        return SelectColumn::make('status')
            ->label(__('admin.fields.status'))
            ->options(PublicationStatus::class)
            ->selectablePlaceholder(false)
            ->rules(['required'])
            ->disabled(fn (ContentItem $record): bool => ! Gate::allows('update', $record))
            ->afterStateUpdated(function (ContentItem $record): void {
                self::notifyPublicationOutcome($record);
            })
            ->toggleable();
    }

    public static function effectivePublishedAtColumn(): TextColumn
    {
        return TextColumn::make('effective_published_at')
            ->label(__('admin.fields.published_at'))
            ->dateTime(UiFormats::dateTime(), UiTimezone::name())
            ->placeholder(__('admin.labels.none'))
            ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderByEffectivePublishedAt($direction))
            ->tooltip(fn (ContentItem $record): ?string => $record->published_at === null && $record->status === PublicationStatus::Published
                ? __('admin.helpers.effective_published_at_fallback')
                : null)
            ->action(self::changePublishedAtAction())
            ->toggleable();
    }

    public static function updatedAtSinceColumn(): TextColumn
    {
        return TextColumn::make('updated_at')
            ->label(__('admin.fields.updated_at'))
            ->since(UiTimezone::name())
            ->dateTimeTooltip(UiFormats::dateTime(), UiTimezone::name())
            ->sortable()
            ->toggleable();
    }

    public static function changePublishedAtAction(): Action
    {
        return Action::make('changePublishedAt')
            ->label(__('admin.actions.change_published_at'))
            ->icon(Heroicon::OutlinedCalendarDays)
            ->modalHeading(__('admin.modals.change_published_at'))
            ->modalDescription(__('admin.helpers.change_published_at'))
            ->authorize('update')
            ->schema([
                DateTimePicker::make('published_at')
                    ->label(__('admin.fields.published_at'))
                    ->helperText(__('admin.helpers.published_at_timezone', ['timezone' => UiTimezone::name()]))
                    ->seconds(false)
                    ->displayFormat(UiFormats::dateTime())
                    ->timezone(UiTimezone::name()),
            ])
            ->fillForm(fn (ContentItem $record): array => [
                'published_at' => $record->published_at,
            ])
            ->action(function (ContentItem $record, array $data): void {
                $record->update(['published_at' => $data['published_at'] ?? null]);

                self::notifyPublicationOutcome($record);
            });
    }

    /**
     * @return array<int, Action>
     */
    public static function remedyActions(): array
    {
        return [
            Action::make('publishBlockingPodcast')
                ->label(__('admin.actions.publish_blocking_podcast'))
                ->icon(Heroicon::OutlinedMegaphone)
                ->color('warning')
                ->visible(fn (ContentItem $record): bool => EpisodePublicState::for($record) === EpisodePublicState::BlockedGroup)
                ->requiresConfirmation()
                ->modalHeading(__('admin.modals.publish_blocking_podcast'))
                ->modalDescription(__('admin.helpers.publish_blocking_podcast'))
                ->action(function (ContentItem $record): void {
                    $group = $record->contentGroup;

                    // The click raced reality: re-check server-side before
                    // flipping anything (state-narrows-at-the-door).
                    if ($group === null || EpisodePublicState::for($record) !== EpisodePublicState::BlockedGroup) {
                        Notification::make()
                            ->warning()
                            ->title(__('admin.notifications.podcast_not_blocking'))
                            ->send();

                        return;
                    }

                    // A podcast can block either by being a draft or by
                    // carrying a future date — publishing it has to answer
                    // both, or the operator clicks through a confirmation
                    // that changes nothing.
                    $group->update([
                        'status' => PublicationStatus::Published,
                        'published_at' => $group->published_at?->gt(now())
                            ? now()
                            : $group->published_at,
                    ]);

                    self::notifyPublicationOutcome($record);
                }),
            Action::make('openBlockedTranscript')
                ->label(__('admin.actions.open_blocked_transcript'))
                ->icon(Heroicon::OutlinedChatBubbleLeftRight)
                ->color('warning')
                ->visible(fn (ContentItem $record): bool => EpisodePublicState::for($record) === EpisodePublicState::BlockedTranscription)
                ->url(fn (ContentItem $record): string => ContentItemResource::getUrl('workspace', ['record' => $record])),
        ];
    }

    public static function editPodcastAction(): Action
    {
        return Action::make('editPodcast')
            ->label(__('admin.actions.edit_podcast'))
            ->icon(Heroicon::OutlinedRectangleGroup)
            ->visible(fn (ContentItem $record): bool => $record->contentGroup !== null)
            ->url(fn (ContentItem $record): string => ContentGroupResource::getUrl('edit', ['record' => $record->content_group_id]));
    }

    public static function notifyPublicationOutcome(ContentItem $record): void
    {
        // Verdicts come from a clean re-read: a table-row instance can carry
        // a stale withExists attribute through refresh(), and the single
        // fallback query on one record is the honest price here.
        $fresh = $record->fresh()->load('contentGroup');
        $state = EpisodePublicState::for($fresh);

        $notification = Notification::make()
            ->title(match ($state) {
                EpisodePublicState::Visible => __('admin.notifications.episode_visible'),
                EpisodePublicState::Scheduled => __('admin.notifications.episode_scheduled', [
                    'date' => $fresh->published_at?->timezone(UiTimezone::name())->format(UiFormats::dateTime()),
                ]),
                EpisodePublicState::Draft => __('admin.notifications.episode_unpublished'),
                EpisodePublicState::BlockedGroup => __('admin.notifications.episode_blocked_group'),
                EpisodePublicState::BlockedTranscription => __('admin.notifications.episode_blocked_transcription'),
            });

        $state === EpisodePublicState::Visible || $state === EpisodePublicState::Scheduled
            ? $notification->success()
            : ($state->isBlocked() ? $notification->warning() : $notification->info());

        if ($record->wasChanged('published_at') && $fresh->published_at !== null) {
            $notification->body(__('admin.notifications.published_at_stamped', [
                'date' => $fresh->published_at->timezone(UiTimezone::name())->format(UiFormats::dateTime()),
            ]));
        }

        $notification->send();
    }

    /**
     * Status as a grouped toggle rather than a select (P-EL7). The schema
     * field keeps the name `value`, so the state path stays
     * `filters.status.value` — the exact shape the dashboard's funnel and
     * stats doorways already link with.
     */
    public static function statusToggleFilter(): Filter
    {
        return Filter::make('status')
            ->schema([
                ToggleButtons::make('value')
                    ->label(__('admin.fields.status'))
                    ->options([
                        'all' => __('admin.filters.status_options.all'),
                        ...collect(PublicationStatus::cases())
                            ->mapWithKeys(fn (PublicationStatus $status): array => [$status->value => $status->getLabel()])
                            ->all(),
                    ])
                    ->default('all')
                    ->grouped(),
            ])
            ->resetState(['value' => 'all'])
            ->query(fn (Builder $query, array $data): Builder => $query->when(
                PublicationStatus::tryFrom((string) ($data['value'] ?? 'all')),
                fn (Builder $query, PublicationStatus $status): Builder => $query->where('status', $status),
            ))
            ->indicateUsing(fn (array $data): ?string => PublicationStatus::tryFrom((string) ($data['value'] ?? 'all'))?->getLabel());
    }

    public static function pinnedToggleFilter(): Filter
    {
        return Filter::make('is_pinned')
            ->schema([
                ToggleButtons::make('value')
                    ->label(__('admin.fields.is_pinned'))
                    ->options(EpisodePinScope::options())
                    ->default(EpisodePinScope::All->value)
                    ->grouped(),
            ])
            // Without an explicit reset state, clearing the filter leaves the
            // ToggleButtons with nothing selected instead of returning to
            // «הכל» (Filament resets a string-state field to null).
            ->resetState(['value' => EpisodePinScope::All->value])
            ->query(fn (Builder $query, array $data): Builder => match (EpisodePinScope::fromFilter($data['value'] ?? null)) {
                EpisodePinScope::Pinned => $query->currentlyPinned(),
                EpisodePinScope::Unpinned => $query->whereNot(fn (Builder $pinned): Builder => $pinned->currentlyPinned()),
                EpisodePinScope::All => $query,
            })
            ->indicateUsing(fn (array $data): ?string => EpisodePinScope::fromFilter($data['value'] ?? null)->indicator());
    }

    public static function publishedBetweenFilter(): Filter
    {
        return Filter::make('published_between')
            ->schema([
                DatePicker::make('published_from')
                    ->label(__('admin.filters.published_from'))
                    ->native(false)
                    ->displayFormat(UiFormats::date()),
                DatePicker::make('published_until')
                    ->label(__('admin.filters.published_until'))
                    ->native(false)
                    ->displayFormat(UiFormats::date()),
            ])
            ->columnSpan(2)
            ->columns(2)
            ->query(function (Builder $query, array $data): Builder {
                // Jerusalem day walls converted to UTC instants — never a raw
                // whereDate over the UTC column (jerusalem-walls). The
                // published date the operator sees is the effective one, so
                // the range compares against the same COALESCE expression the
                // column sorts by, not the raw column beside it.
                return $query
                    ->when(
                        self::filterDay($data['published_from'] ?? null),
                        fn (Builder $query, Carbon $day): Builder => $query->whereRaw(
                            self::effectivePublishedAtExpression().' >= ?',
                            // The status binding belongs to the CASE inside
                            // the expression, so it comes first.
                            [PublicationStatus::Published->value, $day->startOfDay()->utc()],
                        ),
                    )
                    ->when(
                        self::filterDay($data['published_until'] ?? null),
                        fn (Builder $query, Carbon $day): Builder => $query->whereRaw(
                            self::effectivePublishedAtExpression().' <= ?',
                            [PublicationStatus::Published->value, $day->endOfDay()->utc()],
                        ),
                    );
            })
            ->indicateUsing(function (array $data): array {
                $indicators = [];

                foreach (['published_from', 'published_until'] as $field) {
                    $day = self::filterDay($data[$field] ?? null);

                    if (! $day instanceof Carbon) {
                        continue;
                    }

                    $indicators[] = Indicator::make(__("admin.filters.{$field}_indicator", [
                        'date' => $day->format(UiFormats::date()),
                    ]))->removeField($field);
                }

                return $indicators;
            });
    }

    /**
     * Table filter state is raw browser input — Filament hands back whatever
     * the query string or a `$wire.set` put there, with no schema validation
     * on this path. Anything that is not a parsable date narrows to null
     * instead of reaching Carbon and 500ing the page.
     */
    private static function filterDay(mixed $value): ?Carbon
    {
        if (! is_string($value) || blank($value)) {
            return null;
        }

        try {
            return Carbon::parse($value, UiTimezone::name());
        } catch (InvalidFormatException|InvalidArgumentException) {
            return null;
        }
    }

    /**
     * The SQL twin of `effective_published_at`: published rows with no date
     * fall back to their creation date. Callers bind the published status
     * value after their own bindings.
     */
    private static function effectivePublishedAtExpression(): string
    {
        return 'coalesce(published_at, case when status = ? then created_at end)';
    }

    public static function addTranscriptionAction(): Action
    {
        return Action::make('addTranscription')
            ->label(TranscriptionModeLabel::text('admin.actions.add_transcription'))
            ->icon(Heroicon::OutlinedDocumentPlus)
            ->schema([
                RelationshipOptionForms::configureTranscriberOptionsSelect(
                    Select::make('transcriber_ids'),
                    episodeLanguage: true,
                ),
                TextInput::make('title')
                    ->label(__('admin.fields.title'))
                    ->helperText(TranscriptionModeLabel::text('admin.helpers.transcription_title'))
                    ->maxLength(255),
                TextInput::make('language_code')
                    ->label(__('admin.fields.language_code'))
                    ->helperText(__('admin.helpers.language_code'))
                    ->default('he')
                    ->required()
                    ->maxLength(10),
                Select::make('status')
                    ->label(__('admin.fields.status'))
                    ->helperText(TranscriptionModeLabel::text('admin.helpers.transcription_status'))
                    ->options(PublicationStatus::class)
                    ->default(PublicationStatus::Draft->value)
                    ->required(),
                DateTimePicker::make('published_at')
                    ->label(__('admin.fields.published_at'))
                    ->helperText(TranscriptionModeLabel::text('admin.helpers.transcription_published_at', ['timezone' => UiTimezone::name()]))
                    ->displayFormat(UiFormats::dateTime())
                    ->timezone(UiTimezone::name()),
                MarkdownEditor::make('transcript_markdown')
                    ->label(__('admin.fields.transcript_markdown'))
                    ->helperText(TranscriptionModeLabel::text('admin.helpers.transcript_markdown'))
                    ->disableToolbarButtons(['attachFiles'])
                    ->fileAttachments(false)
                    ->required()
                    ->columnSpanFull(),
            ])
            ->action(function (ContentItem $record, array $data): void {
                $transcriberIds = $data['transcriber_ids'] ?? [];
                unset($data['transcriber_ids']);

                $transcription = $record->transcriptions()->create($data);
                $transcription->syncTranscribers($transcriberIds);

                Notification::make()
                    ->success()
                    ->title(TranscriptionModeLabel::text('admin.notifications.transcription_created'))
                    ->body(TranscriptionModeLabel::text('admin.notifications.first_transcription_featured'))
                    ->send();
            });
    }

    private static function effectiveTranscriberNames(ContentItem $record): string
    {
        return implode(', ', $record->effectiveTranscription()?->transcriberNames() ?? []);
    }
}
