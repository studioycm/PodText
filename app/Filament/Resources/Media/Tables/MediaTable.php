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
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
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
                ImageColumn::make('preview_url')
                    ->label(__('admin.fields.preview'))
                    ->state(fn (Media $record): ?string => app(MediaInventoryDiagnostics::class)->previewUrl($record))
                    ->imageHeight(64)
                    ->imageWidth(64),
                TextColumn::make('title')
                    ->label(__('admin.fields.title'))
                    ->placeholder(__('admin.placeholders.empty'))
                    ->searchable(),
                TextColumn::make('type')
                    ->label(__('admin.fields.mime_type'))
                    ->badge(),
                TextColumn::make('size')
                    ->label(__('admin.fields.file_size'))
                    ->formatStateUsing(fn (?int $state): string => Number::fileSize($state ?? 0)),
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
                    ->sortable(),
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
