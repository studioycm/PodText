<?php

namespace App\Filament\Resources\Media\Tables;

use App\Filament\Resources\Media\MediaResource;
use App\Filament\Resources\Support\ResourceTableActions;
use App\Models\Media;
use App\Models\User;
use App\Support\Media\CuratorImageUploadPolicy;
use App\Support\Media\MediaFilesystemMutationCoordinator;
use App\Support\Media\MediaInventoryDiagnostics;
use App\Support\Media\MediaRecordScope;
use Filament\Actions\Action;
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
use Filament\Tables\Filters\Filter;
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
        return ResourceTableActions::iconOnly($table)
            ->defaultSort('id', 'desc')
            ->defaultPaginationPageOption(25)
            ->paginationPageOptions([25])
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
                                ->alt(fn (Media $record): string => (string) (
                                    data_get($record->exif, 'original_filename')
                                    ?: $record->title
                                    ?: $record->name
                                ))
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
                                    ->state(fn (Media $record): string => (string) ($record->title ?: $record->name))
                                    ->searchable(['title', 'name'])
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
                                    ->wrap(),
                                TextColumn::make('card_location')
                                    ->label(__('admin.owner_image.metadata.directory'))
                                    ->state(fn (Media $record): string => collect([
                                        $record->disk,
                                        $record->directory,
                                    ])->filter()->implode(' · '))
                                    ->icon(Heroicon::OutlinedFolder)
                                    ->wrap(),
                                TextColumn::make('repair_status')
                                    ->label(__('admin.media_library.repair_status'))
                                    ->state(fn (Media $record): string => app(MediaInventoryDiagnostics::class)->needsRepair($record)
                                        ? __('admin.media_library.needs_repair')
                                        : __('admin.media_library.ready'))
                                    ->badge()
                                    ->color(fn (Media $record): string => app(MediaInventoryDiagnostics::class)->needsRepair($record) ? 'warning' : 'success')
                                    ->tooltip(fn (Media $record): ?string => app(MediaInventoryDiagnostics::class)->needsRepair($record)
                                        ? collect(app(MediaInventoryDiagnostics::class)->reasons($record))
                                            ->map(fn (string $reason): string => __("admin.media_library.repair_{$reason}"))
                                            ->implode(' · ')
                                        : null),
                                TextColumn::make('created_at')
                                    ->label(__('admin.fields.created_at'))
                                    ->dateTime('d/m/Y H:i', 'Asia/Jerusalem')
                                    ->icon(Heroicon::OutlinedCalendarDays)
                                    ->sortable(),
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
                        ->all()),
                Filter::make('needs_repair')
                    ->label(__('admin.media_library.needs_repair'))
                    ->query(fn (Builder $query): Builder => app(MediaInventoryDiagnostics::class)
                        ->applyNeedsRepairFilter($query)),
            ])
            ->recordActions([
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
                    }),
                Action::make('delete')
                    ->label(__('admin.actions.delete'))
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->authorize(fn (Media $record): bool => Gate::allows('delete', $record))
                    ->requiresConfirmation()
                    ->action(function (Media $record): void {
                        $user = auth()->user();
                        abort_unless($user instanceof User, 403);
                        app(MediaFilesystemMutationCoordinator::class)->delete($record, $user);
                    }),
                EditAction::make()
                    ->authorize(fn (Media $record): bool => Gate::allows('update', $record))
                    ->url(fn (Media $record): string => MediaResource::getUrl('edit', ['record' => $record])),
            ])
            ->emptyStateActions([
                Action::make('create')
                    ->label(__('admin.actions.create'))
                    ->icon(Heroicon::OutlinedPlus)
                    ->authorize(fn (): bool => Gate::allows('create', MediaResource::getModel()))
                    ->url(MediaResource::getUrl('create')),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('deleteSelected')
                        ->label(__('admin.media_library.delete_selected'))
                        ->icon(Heroicon::OutlinedTrash)
                        ->color('danger')
                        ->requiresConfirmation()
                        ->authorize(fn (): bool => Gate::allows('deleteAny', MediaResource::getModel()))
                        ->action(function (Collection $records): void {
                            $user = auth()->user();
                            abort_unless($user instanceof User, 403);
                            app(MediaFilesystemMutationCoordinator::class)->deleteMany($records, $user);
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }
}
