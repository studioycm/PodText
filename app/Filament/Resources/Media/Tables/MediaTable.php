<?php

namespace App\Filament\Resources\Media\Tables;

use App\Enums\MediaDiagnosticReason;
use App\Filament\Resources\Media\MediaResource;
use App\Filament\Resources\Media\Pages\ListMedia;
use App\Models\Media;
use App\Models\User;
use App\Support\Media\CuratorImageUploadPolicy;
use App\Support\Media\MediaFilesystemMutationCoordinator;
use App\Support\Media\MediaInventoryDiagnostics;
use App\Support\Media\MediaLibraryTaskQuery;
use App\Support\Media\MediaRecordScope;
use App\Support\Media\MediaReferenceFinder;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\Layout\Grid;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Number;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class MediaTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->defaultSortOptionLabel(__('admin.media_library.added_newest_first'))
            ->defaultPaginationPageOption(25)
            ->paginationPageOptions([25])
            ->recordActionsPosition(RecordActionsPosition::AfterContent)
            ->description(fn (ListMedia $livewire): string => $livewire->activeTaskDescription())
            ->recordUrl(fn (Media $record, ListMedia $livewire): ?string => Gate::allows('update', $record)
                ? $livewire->editUrlForMedia($record)
                : null)
            ->extraRecordLinkAttributes(
                fn (Media $record, ListMedia $livewire): array => [
                    'id' => 'media-record-'.(int) $record->getKey(),
                    'autofocus' => $livewire->shouldFocusMedia($record),
                ],
            )
            ->columns([
                Grid::make(1)
                    ->extraAttributes([
                        'data-testid' => 'media-library-card',
                        'class' => 'min-w-0 overflow-hidden',
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
                                ->extraImgAttributes([
                                    'data-testid' => 'media-library-card-image',
                                    'loading' => 'lazy',
                                    'class' => 'w-full rounded-md bg-gray-50 object-contain dark:bg-gray-900',
                                    'style' => 'object-fit: contain;',
                                ]),
                            Stack::make([
                                TextColumn::make('card_title')
                                    ->label(__('admin.owner_image.metadata.title'))
                                    ->state(fn (Media $record): string => self::displayIdentity($record))
                                    ->searchable(
                                        query: fn (Builder $query, string $search): Builder => app(MediaLibraryTaskQuery::class)
                                            ->applySearchTerm($query, $search),
                                    )
                                    ->weight(FontWeight::SemiBold)
                                    ->wrap(),
                                TextColumn::make('card_original_filename')
                                    ->label(__('admin.owner_image.metadata.original_filename'))
                                    ->state(fn (Media $record): ?string => is_string($original = data_get($record->exif, 'original_filename'))
                                        && filled($original)
                                            ? $original
                                            : null)
                                    ->description(__('admin.owner_image.metadata.original_filename'), 'above')
                                    ->wrap(),
                                TextColumn::make('card_stored_filename')
                                    ->label(__('admin.owner_image.metadata.stored_filename'))
                                    ->state(fn (Media $record): string => basename((string) $record->path))
                                    ->description(__('admin.owner_image.metadata.stored_filename'), 'above')
                                    ->extraAttributes([
                                        'data-testid' => 'media-library-card-stored-filename',
                                        'dir' => 'ltr',
                                    ])
                                    ->copyable()
                                    ->copyMessage(__('admin.owner_image.copy_success'))
                                    ->wrap(),
                                TextColumn::make('card_known_references')
                                    ->label(__('admin.media_library.known_references'))
                                    ->description(__('admin.media_library.known_references'), 'above')
                                    ->state(function (Media $record): string {
                                        if ($record->disk !== 'public') {
                                            return __('admin.media_library.known_reference_count_unavailable');
                                        }

                                        $count = self::knownReferenceCount($record);

                                        return trans_choice(
                                            'admin.media_library.known_reference_count',
                                            $count,
                                            ['count' => $count],
                                        );
                                    })
                                    ->icon(Heroicon::OutlinedLink)
                                    ->extraAttributes([
                                        'data-testid' => 'media-library-card-known-references',
                                    ])
                                    ->wrap(),
                                TextColumn::make('repair_status')
                                    ->label(__('admin.media_library.repair_status'))
                                    ->state(fn (Media $record): string => app(MediaInventoryDiagnostics::class)->needsRepair($record)
                                        ? __('admin.media_library.needs_attention')
                                        : __('admin.media_library.ready'))
                                    ->badge()
                                    ->color(fn (Media $record): string => app(MediaInventoryDiagnostics::class)->needsRepair($record) ? 'warning' : 'success')
                                    ->tooltip(fn (Media $record): ?string => app(MediaInventoryDiagnostics::class)->needsRepair($record)
                                        ? collect(app(MediaInventoryDiagnostics::class)->reasons($record))
                                            ->map(fn (string $reason): string => __("admin.media_library.repair_{$reason}"))
                                            ->implode(' · ')
                                        : null)
                                    ->extraAttributes([
                                        'data-testid' => 'media-library-card-attention-status',
                                    ]),
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
                                    ->extraAttributes([
                                        'data-testid' => 'media-library-card-primary-issue',
                                    ])
                                    ->wrap(),
                                TextColumn::make('card_file_summary')
                                    ->label(__('admin.owner_image.media_metadata'))
                                    ->description(__('admin.owner_image.media_metadata'), 'above')
                                    ->state(fn (Media $record): string => collect([
                                        $record->type,
                                        filled($record->ext) ? mb_strtoupper((string) $record->ext) : null,
                                        ($record->width && $record->height)
                                            ? "{$record->width}×{$record->height}"
                                            : null,
                                        Number::fileSize((int) ($record->size ?? 0)),
                                    ])->filter()->implode(' · '))
                                    ->extraAttributes([
                                        'data-testid' => 'media-library-card-file-summary',
                                        'dir' => 'ltr',
                                    ])
                                    ->color('gray')
                                    ->wrap(),
                                TextColumn::make('card_location')
                                    ->label(__('admin.owner_image.metadata.directory'))
                                    ->state(fn (Media $record): string => collect([
                                        $record->disk,
                                        $record->directory,
                                    ])->filter()->implode(' · '))
                                    ->icon(Heroicon::OutlinedFolder)
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
                            ])
                                ->space(1)
                                ->extraAttributes([
                                    'class' => 'min-w-0 p-3',
                                ]),
                        ])->space(2),
                    ]),
            ])
            ->contentGrid([
                'md' => 2,
                'lg' => 3,
                '2xl' => 4,
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
                EditAction::make()
                    ->label(__('admin.media_library.open_details'))
                    ->icon(Heroicon::OutlinedInformationCircle)
                    ->button()
                    ->color('primary')
                    ->authorize(fn (Media $record): bool => Gate::allows('update', $record))
                    ->url(fn (Media $record, ListMedia $livewire): string => $livewire->editUrlForMedia($record)),
                ActionGroup::make([
                    Action::make('view')
                        ->label(__('admin.actions.view'))
                        ->icon(Heroicon::OutlinedEye)
                        ->authorize(fn (Media $record): bool => Gate::allows('view', $record))
                        ->url(fn (Media $record): string => route('admin.media-files.view', ['media' => $record->getKey()]), true),
                    Action::make('download')
                        ->label(__('admin.actions.download'))
                        ->icon(Heroicon::OutlinedArrowDownTray)
                        ->authorize(fn (Media $record): bool => Gate::allows('download', $record))
                        ->action(function (Media $record) {
                            $user = auth()->user();
                            abort_unless($user instanceof User, 403);
                            $trusted = app(MediaRecordScope::class)->findInventoryOrFail((int) $record->getKey());
                            Gate::forUser($user)->authorize('download', $trusted);

                            return redirect()->to(route('admin.media-files.download', ['media' => $trusted->getKey()]));
                        }),
                    Action::make('rename')
                        ->label(__('admin.media_library.rename'))
                        ->icon(Heroicon::OutlinedTag)
                        ->color('gray')
                        ->authorize(fn (Media $record): bool => Gate::allows('rename', $record))
                        ->requiresConfirmation()
                        ->action(function (Media $record): void {
                            $user = auth()->user();
                            abort_unless($user instanceof User, 403);
                            app(MediaFilesystemMutationCoordinator::class)->rename($record, $user);
                            app(MediaInventoryDiagnostics::class)->forget($record);
                        }),
                    Action::make('swap')
                        ->label(__('admin.media_library.swap'))
                        ->icon(Heroicon::OutlinedArrowsRightLeft)
                        ->color('warning')
                        ->authorize(fn (Media $record): bool => Gate::allows('swap', $record))
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
                            app(MediaFilesystemMutationCoordinator::class)->swap($record, $replacement, $user);
                            app(MediaInventoryDiagnostics::class)->forget($record);
                        }),
                    Action::make('delete')
                        ->label(__('admin.media_library.delete_permanently'))
                        ->icon(Heroicon::OutlinedTrash)
                        ->color('danger')
                        ->authorize(fn (Media $record): bool => Gate::allows('delete', $record))
                        ->requiresConfirmation()
                        ->action(function (Media $record, ListMedia $livewire): void {
                            $user = auth()->user();
                            abort_unless($user instanceof User, 403);
                            app(MediaFilesystemMutationCoordinator::class)->delete($record, $user);
                            app(MediaInventoryDiagnostics::class)->forget($record);
                            $livewire->forgetMediaTaskCaches();
                        }),
                ])
                    ->label(__('admin.media_library.more_actions'))
                    ->icon(Heroicon::OutlinedEllipsisVertical)
                    ->color('gray')
                    ->tooltip(__('admin.media_library.more_actions'))
                    ->iconButton(),
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
                BulkActionGroup::make([
                    BulkAction::make('deleteSelected')
                        ->label(__('admin.media_library.delete_selected_permanently'))
                        ->icon(Heroicon::OutlinedTrash)
                        ->color('danger')
                        ->requiresConfirmation()
                        ->authorize(fn (): bool => Gate::allows('deleteAny', MediaResource::getModel()))
                        ->action(function (Collection $records, ListMedia $livewire): void {
                            $user = auth()->user();
                            abort_unless($user instanceof User, 403);
                            app(MediaFilesystemMutationCoordinator::class)->deleteMany($records, $user);
                            $records->each(
                                fn (Media $record) => app(MediaInventoryDiagnostics::class)->forget($record),
                            );
                            $livewire->forgetMediaTaskCaches();
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

    private static function knownReferenceCount(Media $media): int
    {
        return count(app(MediaReferenceFinder::class)->referencesForMedia($media));
    }
}
