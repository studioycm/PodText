<?php

namespace App\Filament\Resources\Media\Tables;

use App\Enums\MediaDiagnosticReason;
use App\Filament\Resources\Media\MediaResource;
use App\Filament\Resources\Media\Pages\ListMedia;
use App\Models\Media;
use App\Models\User;
use App\Support\Media\CuratorImageUploadPolicy;
use App\Support\Media\MediaBulkDeleteCensus;
use App\Support\Media\MediaDetailsViewModel;
use App\Support\Media\MediaFilesystemMutationCoordinator;
use App\Support\Media\MediaInventoryDiagnostics;
use App\Support\Media\MediaLibraryTaskQuery;
use App\Support\Media\MediaOperationReceipts;
use App\Support\Media\MediaOwnerTitleApplier;
use App\Support\Media\MediaRecordProjector;
use App\Support\Media\MediaRecordScope;
use App\Support\Media\MediaReferenceFinder;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Support\Enums\Size;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\Layout\Grid;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Number;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use RuntimeException;

class MediaTable
{
    /** @var array<string, array{0: string, 1: string}> */
    private const SORT_MODES = [
        'newest' => ['created_at', 'desc'],
        'oldest' => ['created_at', 'asc'],
        'title' => ['title', 'asc'],
        'filename' => ['card_stored_filename', 'asc'],
    ];

    public static function configure(Table $table): Table
    {
        return $table
            ->extraAttributes(['class' => 'podtext-media-gallery'])
            ->defaultSort('created_at', 'desc')
            ->defaultSortOptionLabel(__('admin.media_library.added_newest_first'))
            ->defaultPaginationPageOption(12)
            ->paginationPageOptions([8, 12, 16, 24])
            ->recordActionsPosition(RecordActionsPosition::AfterContent)
            ->recordUrl(null)
            ->recordAction('mediaDetails')
            ->columns([
                Grid::make(1)
                    ->extraAttributes(fn (ListMedia $livewire): array => [
                        'data-testid' => 'media-library-card',
                        'class' => $livewire->cardsPerRow >= 4
                            ? 'min-w-0 overflow-hidden podtext-card-dense'
                            : 'min-w-0 overflow-hidden',
                    ])
                    ->schema([
                        Stack::make([
                            ImageColumn::make('preview_url')
                                ->label(__('admin.fields.preview'))
                                ->state(fn (Media $record): ?string => app(MediaInventoryDiagnostics::class)->previewUrl($record))
                                ->alt(fn (Media $record): string => self::displayIdentity($record))
                                ->imageHeight('12rem')
                                ->imageWidth('100%')
                                ->checkFileExistence(false)
                                ->extraImgAttributes(fn (ListMedia $livewire): array => [
                                    'data-testid' => 'media-library-card-image',
                                    'loading' => 'lazy',
                                    'class' => $livewire->cardsPerRow >= 4
                                        ? 'w-full bg-gray-50 object-contain dark:bg-gray-900'
                                        : 'w-full rounded-md bg-gray-50 object-contain dark:bg-gray-900',
                                    'style' => 'object-fit: contain;',
                                ]),
                            Stack::make([
                                TextInputColumn::make('title')
                                    ->label(__('admin.fields.title'))
                                    ->placeholder(__('admin.media_library.inline_title_placeholder'))
                                    ->sortable()
                                    ->rules(['nullable', 'string', 'max:255'])
                                    ->disabled(fn (Media $record): bool => ! Gate::allows('update', $record))
                                    ->extraAttributes([
                                        'data-testid' => 'media-library-card-title-input',
                                    ]),
                                TextColumn::make('card_original_filename')
                                    ->label(__('admin.owner_image.metadata.original_filename'))
                                    ->state(fn (Media $record): ?string => is_string($original = data_get($record->exif, 'original_filename'))
                                        && filled($original)
                                            ? $original
                                            : null)
                                    ->formatStateUsing(fn (string $state): HtmlString => new HtmlString(
                                        '<span class="text-gray-500 dark:text-gray-400">'.e(__('admin.media_library.card_row_original')).': </span>'
                                        .'<span dir="ltr" style="unicode-bidi: isolate; overflow-wrap: anywhere;">'.e($state).'</span>',
                                    ))
                                    ->html()
                                    ->searchable(
                                        query: fn (Builder $query, string $search, ListMedia $livewire): Builder => app(MediaLibraryTaskQuery::class)
                                            ->applySearchTerm($query, $search, $livewire->searchScope),
                                    )
                                    ->wrap(),
                                TextColumn::make('card_stored_filename')
                                    ->label(__('admin.owner_image.metadata.stored_filename'))
                                    ->state(fn (Media $record): string => basename((string) $record->path))
                                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query
                                        ->orderBy('name', $direction))
                                    ->icon(Heroicon::OutlinedDocument)
                                    ->tooltip(fn (Media $record): string => basename((string) $record->path))
                                    ->formatStateUsing(fn (string $state): HtmlString => new HtmlString(
                                        '<span dir="ltr" style="unicode-bidi: isolate; display: block; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">'.e($state).'</span>',
                                    ))
                                    ->html()
                                    ->extraAttributes([
                                        'data-testid' => 'media-library-card-stored-filename',
                                    ])
                                    ->copyable()
                                    ->copyableState(fn (Media $record): string => basename((string) $record->path))
                                    ->copyMessage(__('admin.owner_image.copy_success')),
                                TextColumn::make('card_primary_issue')
                                    ->label(__('admin.media_library.needs_attention'))
                                    ->state(function (Media $record): ?string {
                                        $reason = app(MediaInventoryDiagnostics::class)->reasons($record)[0] ?? null;

                                        return is_string($reason)
                                            ? __("admin.media_library.repair_{$reason}")
                                            : null;
                                    })
                                    ->description(function (Media $record): ?string {
                                        $additional = max(
                                            count(app(MediaInventoryDiagnostics::class)->reasons($record)) - 1,
                                            0,
                                        );

                                        return $additional > 0
                                            ? trans_choice(
                                                'admin.media_library.additional_issue_count',
                                                $additional,
                                                ['count' => $additional],
                                            )
                                            : null;
                                    })
                                    ->icon(Heroicon::OutlinedExclamationTriangle)
                                    ->color('warning')
                                    ->url(fn (Media $record): ?string => app(MediaInventoryDiagnostics::class)->needsRepair($record)
                                        ? MediaResource::getUrl('review-issues', ['record' => $record])
                                        : null)
                                    ->extraAttributes([
                                        'data-testid' => 'media-library-card-primary-issue',
                                    ])
                                    ->wrap(),
                                TextColumn::make('card_file_summary')
                                    ->label(__('admin.owner_image.media_metadata'))
                                    ->state(fn (Media $record): string => collect([
                                        ($record->width && $record->height)
                                            ? "{$record->width}×{$record->height}"
                                            : null,
                                        Number::fileSize((int) ($record->size ?? 0)),
                                    ])->filter()->implode(' · '))
                                    ->tooltip(fn (Media $record): ?string => filled($record->ext)
                                        ? mb_strtoupper((string) $record->ext)
                                        : null)
                                    ->formatStateUsing(fn (string $state): HtmlString => new HtmlString(
                                        '<span dir="ltr" style="unicode-bidi: isolate;">'.e($state).'</span>',
                                    ))
                                    ->html()
                                    ->extraAttributes([
                                        'data-testid' => 'media-library-card-file-summary',
                                    ])
                                    ->color('gray')
                                    ->wrap(),
                                TextColumn::make('created_at')
                                    ->label(__('admin.media_library.added'))
                                    ->dateTime('d/m/Y H:i', 'Asia/Jerusalem')
                                    ->icon(Heroicon::OutlinedCalendarDays)
                                    ->color('gray')
                                    ->sortable(
                                        query: fn (Builder $query, string $direction): Builder => $query
                                            ->orderBy('created_at', $direction)
                                            ->orderBy($query->getModel()->getQualifiedKeyName(), $direction),
                                    ),
                                TextColumn::make('card_known_references')
                                    ->label(__('admin.media_library.known_references'))
                                    ->state(function (Media $record): HtmlString {
                                        $references = $record->disk === 'public'
                                            ? app(MediaReferenceFinder::class)->referencesForMedia($record)
                                            : null;
                                        $values = match (true) {
                                            $references === null => __('admin.media_library.known_reference_count_unavailable'),
                                            $references === [] => __('admin.media_library.details_no_usages'),
                                            default => implode(' · ', $references),
                                        };

                                        return new HtmlString(
                                            '<span class="block min-w-0">'
                                            .'<span class="block text-gray-500 dark:text-gray-400">'.e(__('admin.media_library.card_row_references')).':</span>'
                                            .'<span class="block" dir="auto">'.e($values).'</span>'
                                            .'</span>',
                                        );
                                    })
                                    ->html()
                                    ->tooltip(function (Media $record): ?string {
                                        if ($record->disk !== 'public') {
                                            return null;
                                        }

                                        $references = app(MediaReferenceFinder::class)->referencesForMedia($record);

                                        return $references === [] ? null : implode("\n", $references);
                                    })
                                    ->extraAttributes([
                                        'data-testid' => 'media-library-card-known-references',
                                    ])
                                    ->wrap(),
                            ])
                                ->space(1)
                                ->extraAttributes(fn (ListMedia $livewire): array => [
                                    'class' => $livewire->cardsPerRow >= 4 ? 'min-w-0 p-1.5' : 'min-w-0 p-3',
                                ]),
                        ])->space(2),
                    ]),
            ])
            ->contentGrid(fn (ListMedia $livewire): array => [
                'md' => min(2, $livewire->cardsPerRow),
                'lg' => $livewire->cardsPerRow,
                '2xl' => $livewire->cardsPerRow,
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label(__('admin.fields.mime_type'))
                    ->options(fn (): array => collect(app(CuratorImageUploadPolicy::class)->globalMimeTypes())
                        ->mapWithKeys(fn (string $mimeType): array => [$mimeType => $mimeType])
                        ->all())
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;

                        if (blank($value)) {
                            return $query;
                        }

                        if (
                            ! is_string($value)
                            || ! in_array(
                                $value,
                                app(CuratorImageUploadPolicy::class)->globalMimeTypes(),
                                true,
                            )
                        ) {
                            return $query->whereRaw('1 = 0');
                        }

                        return $query->where(
                            $query->getModel()->qualifyColumn('type'),
                            $value,
                        );
                    }),
                SelectFilter::make('reason')
                    ->label(__('admin.media_library.attention_reason'))
                    ->options(MediaDiagnosticReason::class)
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;

                        if (blank($value)) {
                            return $query;
                        }

                        if (! is_string($value)) {
                            return $query->whereRaw('1 = 0');
                        }

                        return app(MediaLibraryTaskQuery::class)->applyReason($query, $value);
                    }),
            ])
            ->recordActions([
                Action::make('mediaDetails')
                    ->label(__('admin.owner_image.actions.open_details'))
                    ->icon(Heroicon::OutlinedInformationCircle)
                    ->iconButton()
                    ->authorize(fn (Media $record): bool => Gate::allows('view', $record))
                    ->slideOver()
                    ->modalWidth(Width::Medium)
                    ->modalHeading(__('admin.owner_image.actions.open_details'))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('admin.actions.close'))
                    ->modalContent(fn (Media $record): View => view(
                        'livewire.admin.media-details-slide-over',
                        MediaDetailsViewModel::make($record),
                    )),
                EditAction::make()
                    ->label(__('admin.media_library.open_edit_page'))
                    ->tooltip(__('admin.media_library.open_edit_page'))
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->iconButton()
                    ->color('primary')
                    ->authorize(fn (Media $record): bool => Gate::allows('update', $record))
                    ->extraAttributes(fn (Media $record, ListMedia $livewire): array => [
                        'id' => 'media-record-'.(int) $record->getKey(),
                        'autofocus' => $livewire->shouldFocusMedia($record),
                    ])
                    ->url(fn (Media $record, ListMedia $livewire): string => $livewire->editUrlForMedia($record)),
                ActionGroup::make([
                    Action::make('reviewIssues')
                        ->label(__('admin.media_issue_review.review_issues'))
                        ->icon(Heroicon::OutlinedExclamationTriangle)
                        ->color('warning')
                        ->visible(fn (Media $record): bool => app(MediaInventoryDiagnostics::class)->needsRepair($record))
                        ->url(fn (Media $record): string => MediaResource::getUrl('review-issues', ['record' => $record])),
                    Action::make('view')
                        ->label(__('admin.actions.view'))
                        ->icon(Heroicon::OutlinedEye)
                        ->authorize(fn (Media $record): bool => Gate::allows('view', $record))
                        ->disabled(fn (Media $record): bool => self::fileIsMissing($record))
                        ->tooltip(fn (Media $record): ?string => self::fileIsMissing($record)
                            ? __('admin.media_library.op_file_missing')
                            : null)
                        ->url(fn (Media $record): string => route('admin.media-files.view', ['media' => $record->getKey()]), true),
                    Action::make('download')
                        ->label(__('admin.actions.download'))
                        ->icon(Heroicon::OutlinedArrowDownTray)
                        ->authorize(fn (Media $record): bool => Gate::allows('download', $record))
                        ->disabled(fn (Media $record): bool => self::fileIsMissing($record))
                        ->tooltip(fn (Media $record): ?string => self::fileIsMissing($record)
                            ? __('admin.media_library.op_file_missing')
                            : null)
                        ->action(function (Media $record) {
                            $user = auth()->user();
                            abort_unless($user instanceof User, 403);
                            $trusted = app(MediaRecordScope::class)->findInventoryOrFail((int) $record->getKey());
                            Gate::forUser($user)->authorize('download', $trusted);

                            return redirect()->to(route('admin.media-files.download', ['media' => $trusted->getKey()]));
                        }),
                    Action::make('titleByOwner')
                        ->label(__('admin.media_library.title_by_owner'))
                        ->icon(Heroicon::OutlinedIdentification)
                        ->color('gray')
                        ->authorize(fn (Media $record): bool => Gate::allows('update', $record))
                        ->requiresConfirmation()
                        ->modalDescription(function (Media $record): string {
                            $title = app(MediaOwnerTitleApplier::class)->composedTitle($record);

                            return $title === null
                                ? __('admin.media_library.title_by_owner_none')
                                : __('admin.media_library.title_by_owner_single_preview', ['title' => $title]);
                        })
                        ->action(function (Media $record): void {
                            $user = auth()->user();
                            abort_unless($user instanceof User, 403);

                            $result = app(MediaOwnerTitleApplier::class)->apply(new Collection([$record]), $user);
                            app(MediaOperationReceipts::class)->titleByOwnerFinished($user, $result['titled'], $result['skipped']);
                        }),
                    Action::make('rename')
                        ->label(__('admin.media_library.rename'))
                        ->icon(Heroicon::OutlinedTag)
                        ->color('gray')
                        ->authorize('rename')
                        ->authorizationTooltip()
                        ->requiresConfirmation()
                        ->modalContent(fn (Media $record): View => self::operationConsequence($record, 'rename'))
                        ->modalSubmitActionLabel(__('admin.media_library.rename_submit'))
                        ->action(function (Media $record): void {
                            $user = auth()->user();
                            abort_unless($user instanceof User, 403);
                            $oldName = basename((string) $record->path);

                            try {
                                $updated = app(MediaFilesystemMutationCoordinator::class)->rename($record, $user);
                            } catch (ValidationException $exception) {
                                app(MediaOperationReceipts::class)->operationFailed($exception->getMessage());

                                return;
                            } catch (RuntimeException) {
                                app(MediaOperationReceipts::class)->operationFailed();

                                return;
                            }

                            app(MediaInventoryDiagnostics::class)->forget($record);
                            app(MediaOperationReceipts::class)->renameSucceeded($user, $oldName, basename((string) $updated->path));
                        }),
                    Action::make('swap')
                        ->label(__('admin.media_library.swap'))
                        ->icon(Heroicon::OutlinedArrowsRightLeft)
                        ->color('warning')
                        ->authorize('swap')
                        ->authorizationTooltip()
                        ->modalContent(fn (Media $record): View => self::operationConsequence($record, 'swap'))
                        ->modalSubmitActionLabel(__('admin.media_library.swap_submit'))
                        ->schema([
                            FileUpload::make('replacement')
                                ->label(__('admin.media_library.replacement'))
                                ->acceptedFileTypes(fn (Media $record): array => app(CuratorImageUploadPolicy::class)
                                    ->mimeTypesFor(app(CuratorImageUploadPolicy::class)->purposeForPath((string) $record->path)))
                                ->maxSize(CuratorImageUploadPolicy::MAX_KILOBYTES)
                                ->storeFiles(false)
                                ->required(),
                        ])
                        ->action(function (Media $record, array $data): void {
                            $user = auth()->user();
                            abort_unless($user instanceof User, 403);
                            $replacement = $data['replacement'] ?? null;
                            abort_unless($replacement instanceof TemporaryUploadedFile, 422);
                            $oldSize = (int) $record->size;

                            try {
                                $updated = app(MediaFilesystemMutationCoordinator::class)->swap($record, $replacement, $user);
                            } catch (ValidationException $exception) {
                                app(MediaOperationReceipts::class)->operationFailed($exception->getMessage());

                                return;
                            } catch (RuntimeException) {
                                app(MediaOperationReceipts::class)->operationFailed();

                                return;
                            }

                            app(MediaInventoryDiagnostics::class)->forget($record);
                            app(MediaOperationReceipts::class)->swapSucceeded($user, $oldSize, (int) $updated->size);
                        }),
                    Action::make('delete')
                        ->label(__('admin.media_library.delete_permanently'))
                        ->icon(Heroicon::OutlinedTrash)
                        ->color('danger')
                        ->authorize('delete')
                        ->authorizationTooltip()
                        ->requiresConfirmation()
                        ->modalContent(fn (Media $record): View => self::operationConsequence($record, 'delete'))
                        ->modalSubmitActionLabel(__('admin.media_library.delete_permanently'))
                        ->action(function (Media $record, ListMedia $livewire): void {
                            $user = auth()->user();
                            abort_unless($user instanceof User, 403);
                            $name = (string) ($record->title ?: $record->name);

                            try {
                                app(MediaFilesystemMutationCoordinator::class)->delete($record, $user);
                            } catch (ValidationException $exception) {
                                app(MediaOperationReceipts::class)->operationFailed($exception->getMessage());

                                return;
                            } catch (RuntimeException) {
                                app(MediaOperationReceipts::class)->operationFailed();

                                return;
                            }

                            app(MediaInventoryDiagnostics::class)->forget($record);
                            $livewire->forgetMediaTaskCaches();
                            app(MediaOperationReceipts::class)->deleteSucceeded($user, $name);
                        }),
                ])
                    ->label(__('admin.media_library.more_actions'))
                    ->icon(Heroicon::OutlinedEllipsisVertical)
                    ->color('gray')
                    ->tooltip(__('admin.media_library.more_actions'))
                    ->iconButton(),
                Action::make('cardStatus')
                    ->label(fn (Media $record): string => app(MediaInventoryDiagnostics::class)->needsRepair($record)
                        ? __('admin.media_library.needs_attention')
                        : __('admin.media_library.ready'))
                    ->color(fn (Media $record): string => app(MediaInventoryDiagnostics::class)->needsRepair($record)
                        ? 'warning'
                        : 'success')
                    ->outlined()
                    ->size(Size::ExtraSmall)
                    ->tooltip(fn (Media $record): ?string => ($reasons = app(MediaInventoryDiagnostics::class)->reasons($record)) === []
                        ? null
                        : collect($reasons)
                            ->map(fn (string $reason): string => __("admin.media_library.repair_{$reason}"))
                            ->implode(' · '))
                    ->disabled(fn (Media $record): bool => ! app(MediaInventoryDiagnostics::class)->needsRepair($record))
                    ->url(fn (Media $record): ?string => app(MediaInventoryDiagnostics::class)->needsRepair($record)
                        ? MediaResource::getUrl('review-issues', ['record' => $record])
                        : null)
                    ->extraAttributes([
                        'data-testid' => 'media-library-card-attention-status',
                        'style' => 'margin-inline-start: auto;',
                    ]),
            ])
            ->emptyStateHeading(
                fn (ListMedia $livewire): string => $livewire->hasMediaViewConstraints()
                    ? __('admin.media_library.empty_view_heading')
                    : __('admin.media_library.empty_inventory_heading'),
            )
            ->emptyStateDescription(
                fn (ListMedia $livewire): string => $livewire->hasMediaViewConstraints()
                    ? __('admin.media_library.empty_view_description')
                    : __('admin.media_library.empty_inventory_description'),
            )
            ->emptyStateActions([
                Action::make('resetMediaView')
                    ->label(__('admin.media_library.reset_view'))
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->color('gray')
                    ->url(MediaResource::getUrl('index'))
                    ->visible(fn (ListMedia $livewire): bool => $livewire->hasMediaViewConstraints()),
                Action::make('create')
                    ->label(__('admin.actions.create'))
                    ->icon(Heroicon::OutlinedPlus)
                    ->authorize(fn (): bool => Gate::allows('create', MediaResource::getModel()))
                    ->url(MediaResource::getUrl('create'))
                    ->visible(fn (ListMedia $livewire): bool => ! $livewire->hasMediaViewConstraints()),
            ])
            ->toolbarActions([
                ActionGroup::make(collect(self::SORT_MODES)->map(
                    fn (array $mode, string $key): Action => Action::make('sortMode'.ucfirst($key))
                        ->label(__("admin.media_library.sort_modes.{$key}"))
                        ->action(fn (ListMedia $livewire) => $livewire->sortTable($mode[0], $mode[1])),
                )->all())
                    ->label(function (ListMedia $livewire): string {
                        $current = collect(self::SORT_MODES)
                            ->search(fn (array $mode): bool => "{$mode[0]}:{$mode[1]}" === $livewire->tableSort);

                        return __('admin.media_library.sort_menu', [
                            'mode' => __('admin.media_library.sort_modes.'.($current ?: 'newest')),
                        ]);
                    })
                    ->icon(Heroicon::OutlinedArrowsUpDown)
                    ->button()
                    ->color('gray'),
                ActionGroup::make(collect(['all', 'title', 'owner', 'filename'])->map(
                    fn (string $scope): Action => Action::make('searchScope'.ucfirst($scope))
                        ->label(__("admin.media_library.search_scopes.{$scope}"))
                        ->action(fn (ListMedia $livewire) => $livewire->setSearchScope($scope)),
                )->all())
                    ->label(fn (ListMedia $livewire): string => __("admin.media_library.search_scopes.{$livewire->searchScope}"))
                    ->tooltip(fn (ListMedia $livewire): string => __('admin.media_library.search_scope', [
                        'scope' => __("admin.media_library.search_scopes.{$livewire->searchScope}"),
                    ]))
                    ->icon(Heroicon::OutlinedMagnifyingGlass)
                    ->button()
                    ->color('gray'),
                ActionGroup::make(collect([1, 2, 3, 4, 6, 8])->map(
                    fn (int $count): Action => Action::make("cardsPerRow{$count}")
                        ->label(__('admin.media_library.cards_per_row_option', ['count' => $count]))
                        ->action(fn (ListMedia $livewire) => $livewire->setCardsPerRow($count)),
                )->all())
                    ->label(fn (ListMedia $livewire): string => (string) $livewire->cardsPerRow)
                    ->tooltip(fn (ListMedia $livewire): string => __('admin.media_library.cards_per_row', [
                        'count' => $livewire->cardsPerRow,
                    ]))
                    ->icon(Heroicon::OutlinedSquares2x2)
                    ->button()
                    ->color('gray'),
                BulkActionGroup::make([
                    BulkAction::make('deleteSelected')
                        ->label(__('admin.media_library.delete_selected_permanently'))
                        ->icon(Heroicon::OutlinedTrash)
                        ->color('danger')
                        ->requiresConfirmation()
                        ->authorize(fn (): bool => Gate::allows('deleteAny', MediaResource::getModel()))
                        ->modalContent(function (Collection $records): View {
                            $user = auth()->user();
                            abort_unless($user instanceof User, 403);

                            return app(MediaBulkDeleteCensus::class)->censusView($records, $user);
                        })
                        ->modalSubmitActionLabel(__('admin.media_library.bulk_delete_submit'))
                        ->action(function (Collection $records, ListMedia $livewire): void {
                            $user = auth()->user();
                            abort_unless($user instanceof User, 403);

                            $result = app(MediaBulkDeleteCensus::class)->execute($records, $user);

                            $records->each(
                                fn (Media $record) => app(MediaInventoryDiagnostics::class)->forget($record),
                            );
                            $livewire->forgetMediaTaskCaches();

                            app(MediaOperationReceipts::class)->bulkDeleteFinished(
                                $user,
                                $result['deleted'],
                                $result['blocked'],
                                $result['failed'],
                            );
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('titleByOwnerSelected')
                        ->label(__('admin.media_library.title_by_owner'))
                        ->icon(Heroicon::OutlinedIdentification)
                        ->color('gray')
                        ->authorize(fn (): bool => Gate::allows('viewAny', MediaResource::getModel()))
                        ->modalContent(fn (Collection $records): View => view(
                            'filament.resources.media.title-by-owner-census',
                            app(MediaOwnerTitleApplier::class)->preview($records),
                        ))
                        ->schema([
                            TextInput::make('title_prefix')
                                ->label(__('admin.media_library.title_by_owner_prefix'))
                                ->helperText(__('admin.media_library.title_by_owner_affix_help'))
                                ->maxLength(100),
                            TextInput::make('title_suffix')
                                ->label(__('admin.media_library.title_by_owner_suffix'))
                                ->helperText(__('admin.media_library.title_by_owner_affix_help'))
                                ->maxLength(100),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $user = auth()->user();
                            abort_unless($user instanceof User, 403);

                            $result = app(MediaOwnerTitleApplier::class)->apply(
                                $records,
                                $user,
                                (string) ($data['title_prefix'] ?? ''),
                                (string) ($data['title_suffix'] ?? ''),
                            );

                            app(MediaOperationReceipts::class)->titleByOwnerFinished(
                                $user,
                                $result['titled'],
                                $result['skipped'],
                            );
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }

    private static function displayIdentity(Media $media): string
    {
        $originalFilename = data_get($media->exif, 'original_filename');

        foreach ([
            $media->title,
            is_string($originalFilename) ? $originalFilename : null,
            basename((string) $media->path),
            $media->name,
        ] as $candidate) {
            if (is_string($candidate) && filled($candidate)) {
                return $candidate;
            }
        }

        return (string) $media->getKey();
    }

    private static function fileIsMissing(Media $media): bool
    {
        return in_array(
            MediaDiagnosticReason::MissingFile->value,
            app(MediaInventoryDiagnostics::class)->reasons($media),
            true,
        );
    }

    private static function operationConsequence(Media $media, string $operation): View
    {
        return view('filament.resources.media.operation-consequence', [
            'projection' => app(MediaRecordProjector::class)->project($media),
            'storedFilename' => basename((string) $media->path),
            'operation' => $operation,
        ]);
    }
}
