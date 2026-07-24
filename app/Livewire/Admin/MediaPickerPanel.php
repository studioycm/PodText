<?php

namespace App\Livewire\Admin;

use App\Enums\ImageUploadPurpose;
use App\Enums\MediaAcquisitionDisposition;
use App\Models\Media;
use App\Models\User;
use App\Support\Media\CuratorImageUploadPolicy;
use App\Support\Media\ExternalImageFailureMessage;
use App\Support\Media\MediaAcquisitionManager;
use App\Support\Media\MediaFilesystemMutationCoordinator;
use App\Support\Media\MediaInventoryDiagnostics;
use App\Support\Media\MediaRecordProjector;
use App\Support\Media\MediaRecordScope;
use App\Support\Media\StorageImageCandidateBrowser;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Concerns\RestrictsFileUploadsToSchemaComponents;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use RuntimeException;
use Throwable;
use UnexpectedValueException;

class MediaPickerPanel extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use RestrictsFileUploadsToSchemaComponents;

    private const SELECTION_LIMIT = 50;

    private const SOURCES = ['upload', 'url', 'storage'];

    #[Locked]
    public string $purpose;

    #[Locked]
    public bool $isMultiple = false;

    #[Locked]
    public bool $isInlineOwnerWorkspace = false;

    #[Locked]
    public ?int $maxItems = null;

    /** @var array<int, array<string, int|string|null>> */
    #[Locked]
    public array $files = [];

    /** @var array<int, array{token: string, filename: string, source: string}> */
    #[Locked]
    public array $storageFiles = [];

    /** @var array<int, int> */
    public array $selectedIds = [];

    /** @var array<string, mixed> */
    public array $panelData = [];

    public string $search = '';

    public string $storageSearch = '';

    public bool $allMedia = false;

    #[Locked]
    public string $activeSource = 'upload';

    #[Locked]
    public bool $storageConfigured = false;

    #[Locked]
    public bool $storageLoaded = false;

    #[Locked]
    public int $currentPage = 1;

    #[Locked]
    public int $lastPage = 1;

    /**
     * @param  array<int, int|string>  $selectedIds
     */
    public function mount(
        string $purpose,
        array $selectedIds = [],
        bool $isMultiple = false,
        ?int $maxItems = null,
        bool $isInlineOwnerWorkspace = false,
    ): void {
        $resolvedPurpose = ImageUploadPurpose::tryFrom($purpose)
            ?? throw new UnexpectedValueException('The media picker purpose is invalid.');

        Gate::forUser($this->actor())->authorize('viewAny', $this->mediaModel());

        $this->purpose = $resolvedPurpose->value;
        $this->isMultiple = $isMultiple;
        $this->maxItems = $maxItems;
        $this->isInlineOwnerWorkspace = $isInlineOwnerWorkspace;
        $this->selectedIds = $this->trustedIds($selectedIds, 'view');
        $this->storageConfigured = app(StorageImageCandidateBrowser::class)->hasConfiguredSources();

        if (count($this->selectedIds) > $this->selectionLimit()) {
            abort(422);
        }

        $this->form->fill();
        $this->reloadFiles();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('panelData')
            ->components([
                Section::make(__('admin.media_library.upload_source'))
                    ->extraAttributes(fn (): array => $this->sourceSectionAttributes('upload'))
                    ->dehydrated(fn (): bool => $this->activeSource === 'upload')
                    ->validatedWhenNotDehydrated(false)
                    ->schema([
                        FileUpload::make('uploads')
                            ->label(__('admin.fields.media_files'))
                            ->helperText(fn (): ?string => $this->isInlineOwnerWorkspace
                                ? __('admin.media_library.inline_batch_help')
                                : null)
                            ->acceptedFileTypes(app(CuratorImageUploadPolicy::class)->mimeTypesFor($this->uploadPurpose()))
                            ->maxSize(app(CuratorImageUploadPolicy::class)->maxKilobytes())
                            ->multiple($this->isMultiple || $this->isInlineOwnerWorkspace)
                            ->maxFiles(
                                $this->isMultiple || $this->isInlineOwnerWorkspace
                                    ? app(CuratorImageUploadPolicy::class)->uploadBatchLimit()
                                    : 1,
                            )
                            ->maxParallelUploads(2)
                            ->extraInputAttributes([
                                'data-testid' => 'media-picker-upload-input',
                                'id' => 'media-picker-upload-input',
                            ])
                            ->uploadingMessage(__('admin.media_library.uploading_file'))
                            ->storeFiles(false),
                    ]),
                Section::make(__('admin.media_library.url_source'))
                    ->extraAttributes(fn (): array => $this->sourceSectionAttributes('url'))
                    ->dehydrated(fn (): bool => $this->activeSource === 'url')
                    ->validatedWhenNotDehydrated(false)
                    ->schema([
                        TextInput::make('external_url')
                            ->label(__('admin.media_library.url_field'))
                            ->helperText(__('admin.media_library.url_help'))
                            ->url()
                            ->rule('url:https')
                            ->maxLength(2048)
                            ->extraInputAttributes([
                                'data-testid' => 'media-picker-url-input',
                                'id' => 'media-picker-url-input',
                                'x-bind:disabled' => 'uploading',
                                'wire:loading.attr' => 'disabled',
                                'wire:offline.attr' => 'disabled',
                            ])
                            ->live(debounce: 400),
                    ]),
            ]);
    }

    public function activateSource(string $source): void
    {
        abort_unless(in_array($source, self::SOURCES, true), 422);

        $this->activeSource = $source;
        $this->resetValidation($this->sourceErrorKeys($source));

        if ($source === 'storage' && ! $this->storageLoaded) {
            $this->loadStorageFiles();
        }
    }

    public function refreshStorageFiles(): void
    {
        $this->activeSource = 'storage';
        $this->resetValidation($this->sourceErrorKeys('storage'));
        $this->loadStorageFiles();
    }

    public function updatedSearch(): void
    {
        $this->search = mb_substr(trim($this->search), 0, 100);
        $this->currentPage = 1;
        $this->reloadFiles();
    }

    public function showAllMedia(): void
    {
        $this->allMedia = true;
        $this->currentPage = 1;
        $this->reloadFiles();
    }

    public function updatedStorageSearch(): void
    {
        $this->storageSearch = mb_substr(trim($this->storageSearch), 0, 100);
        $this->resetValidation($this->sourceErrorKeys('storage'));
    }

    /** @return array<int, array{token: string, filename: string, source: string}> */
    public function filteredStorageFiles(): array
    {
        $search = mb_strtolower($this->storageSearch);

        if ($search === '') {
            return $this->storageFiles;
        }

        return array_values(array_filter(
            $this->storageFiles,
            fn (array $candidate): bool => str_contains(
                mb_strtolower($candidate['filename'].' '.$candidate['source']),
                $search,
            ),
        ));
    }

    public function showContextMedia(): void
    {
        $this->allMedia = false;
        $this->currentPage = 1;
        $this->reloadFiles();
    }

    public function loadMoreFiles(): void
    {
        if (filled($this->search) || $this->currentPage >= $this->lastPage) {
            return;
        }

        $this->currentPage++;
        $this->reloadFiles();
    }

    public function loadPreviousFiles(): void
    {
        if (filled($this->search) || $this->currentPage <= 1) {
            return;
        }

        $this->currentPage--;
        $this->reloadFiles();
    }

    public function toggleSelection(int|string $id): void
    {
        $media = $this->trustedRecord($id, 'select');
        $id = (int) $media->getKey();

        if (in_array($id, $this->selectedIds, true)) {
            if ($this->isInlineOwnerWorkspace) {
                return;
            }

            $this->selectedIds = array_values(array_diff($this->selectedIds, [$id]));

            return;
        }

        if (! $this->isMultiple) {
            $this->selectedIds = [$id];

            return;
        }

        if (count($this->selectedIds) >= $this->selectionLimit()) {
            abort(422);
        }

        $this->selectedIds[] = $id;
    }

    public function clearSelection(): void
    {
        if ($this->isInlineOwnerWorkspace) {
            return;
        }

        $this->selectedIds = [];
    }

    public function uploadFilesAction(): Action
    {
        return Action::make('uploadFiles')
            ->label(fn (): string => $this->isInlineOwnerWorkspace
                ? __('admin.media_library.upload_one_or_multiple')
                : ($this->isMultiple
                    ? __('admin.media_library.add_and_select')
                    : __('admin.media_library.add_and_choose')))
            ->icon(Heroicon::ArrowUpTray)
            ->disabled(fn (): bool => count(Arr::wrap($this->form->getRawState()['uploads'] ?? [])) === 0)
            ->extraAttributes([
                'wire:loading.attr' => 'disabled',
                'wire:offline.attr' => 'disabled',
            ])
            ->action(function (): void {
                $this->activeSource = 'upload';
                $this->resetValidation($this->sourceErrorKeys('upload'));
                $data = $this->form->getState();
                $uploads = array_values(Arr::wrap($data['uploads'] ?? []));
                $isAcquisitionOnlyBatch = $this->isInlineOwnerWorkspace && count($uploads) > 1;

                if (! $this->isMultiple && ! $this->isInlineOwnerWorkspace && count($uploads) > 1) {
                    $this->failSource(
                        'upload',
                        'panelData.uploads',
                        __('admin.media_library.single_upload_only'),
                    );
                }

                if (count($uploads) > app(CuratorImageUploadPolicy::class)->uploadBatchLimit()) {
                    abort(422);
                }

                if (
                    $this->isMultiple
                    && count($this->selectedIds) + count($uploads) > $this->selectionLimit()
                ) {
                    $this->failSource(
                        'upload',
                        'panelData.uploads',
                        __('admin.media_library.selection_limit_exceeded'),
                    );
                }

                $actor = $this->actor();

                if (count($uploads) > 1) {
                    Gate::forUser($actor)->authorize('bulkUpload', $this->mediaModel());
                }

                foreach ($uploads as $upload) {
                    if (! $upload instanceof TemporaryUploadedFile) {
                        throw new UnexpectedValueException('The picker upload state is invalid.');
                    }
                }

                try {
                    $result = app(MediaAcquisitionManager::class)->acquireUploads(
                        $uploads,
                        $this->uploadPurpose(),
                        $actor,
                    );
                } catch (InvalidArgumentException) {
                    $this->failSource(
                        'upload',
                        'panelData.uploads',
                        __('admin.media_library.upload_invalid'),
                    );
                }

                if ($result->successful->isEmpty()) {
                    $this->failSource(
                        'upload',
                        'panelData.uploads',
                        __('admin.media_library.upload_failed'),
                    );
                }

                if (! $isAcquisitionOnlyBatch) {
                    $this->selectAcquired($result->media());
                }

                $this->clearSourceInput('upload');

                if ($this->isMultiple || $this->isInlineOwnerWorkspace) {
                    $this->currentPage = 1;
                    $this->reloadFiles();
                }

                if ($result->isPartial()) {
                    Notification::make()
                        ->warning()
                        ->title(__('admin.media_library.upload_partial_title'))
                        ->body($isAcquisitionOnlyBatch
                            ? __('admin.media_library.acquisition_batch_partial', [
                                'added' => $result->successful->count(),
                                'not_added' => $result->unsuccessfulCount(),
                            ])
                            : __('admin.media_library.upload_partial_body', [
                                'added' => $result->successful->count(),
                                'not_added' => $result->unsuccessfulCount(),
                            ]))
                        ->send();
                } else {
                    Notification::make()
                        ->success()
                        ->title(__('admin.media_library.uploaded'))
                        ->body($isAcquisitionOnlyBatch
                            ? __('admin.media_library.acquisition_batch_created', [
                                'count' => $result->successful->count(),
                            ])
                            : $this->acquisitionSuccessBody($result->successful->count()))
                        ->send();
                }

                if (! $isAcquisitionOnlyBatch) {
                    $this->dispatchSelectionIfSingle();
                }
            });
    }

    public function acquireUrlAction(): Action
    {
        return Action::make('acquireUrl')
            ->label(fn (): string => $this->isMultiple
                ? __('admin.media_library.add_and_select')
                : __('admin.media_library.add_and_choose'))
            ->icon(Heroicon::Link)
            ->disabled(fn (): bool => blank($this->form->getRawState()['external_url'] ?? null))
            ->extraAttributes([
                'wire:loading.attr' => 'disabled',
                'wire:offline.attr' => 'disabled',
            ])
            ->action(function (): void {
                $this->activeSource = 'url';
                $this->resetValidation($this->sourceErrorKeys('url'));
                $url = trim((string) ($this->form->getState()['external_url'] ?? ''));
                $this->ensureAcquisitionCapacity(
                    'url',
                    'panelData.external_url',
                );

                try {
                    $media = app(MediaAcquisitionManager::class)->acquireExternalUrl(
                        $url,
                        $this->uploadPurpose(),
                        $this->actor(),
                    );
                } catch (AuthorizationException $exception) {
                    throw $exception;
                } catch (Throwable $exception) {
                    if (! ExternalImageFailureMessage::isExpected($exception)) {
                        report($exception);
                    }

                    $this->failSource(
                        'url',
                        'panelData.external_url',
                        ExternalImageFailureMessage::for($exception),
                    );
                }

                $this->selectAcquired(collect([$media]));
                $this->clearSourceInput('url');

                if ($this->isMultiple) {
                    $this->currentPage = 1;
                    $this->reloadFiles();
                }

                Notification::make()
                    ->success()
                    ->title(__('admin.media_library.url_acquired'))
                    ->body($this->acquisitionSuccessBody())
                    ->send();

                $this->dispatchSelectionIfSingle();
            });
    }

    public function acquireStorageAction(): Action
    {
        return Action::make('acquireStorage')
            ->label(fn (): string => $this->isMultiple
                ? __('admin.media_library.add_and_select')
                : __('admin.media_library.add_and_choose'))
            ->icon(Heroicon::CircleStack)
            ->extraAttributes([
                'data-testid' => 'media-picker-storage-acquire',
                'wire:loading.attr' => 'disabled',
                'wire:offline.attr' => 'disabled',
            ])
            ->action(function (array $arguments): void {
                $this->activeSource = 'storage';
                $this->resetValidation($this->sourceErrorKeys('storage'));
                $token = $arguments['token'] ?? null;

                if (! is_string($token)) {
                    abort(422);
                }

                $this->ensureAcquisitionCapacity(
                    'storage',
                    'storageAcquisition',
                );

                try {
                    $result = app(MediaAcquisitionManager::class)->acquireStorageCandidate(
                        $token,
                        $this->uploadPurpose(),
                        $this->actor(),
                    );
                } catch (LockTimeoutException) {
                    $this->failSource(
                        'storage',
                        'storageAcquisition',
                        __('admin.media_library.storage_busy'),
                    );
                } catch (InvalidArgumentException|RuntimeException) {
                    $this->failSource(
                        'storage',
                        'storageAcquisition',
                        __('admin.media_library.storage_invalid'),
                    );
                }

                $this->selectAcquired(collect([$result->media]));

                if ($this->isMultiple) {
                    $this->currentPage = 1;
                    $this->reloadFiles();
                }

                Notification::make()
                    ->success()
                    ->title(__("admin.media_library.storage_{$result->disposition->value}"))
                    ->body($this->acquisitionSuccessBody(
                        reused: $result->disposition === MediaAcquisitionDisposition::Reused,
                    ))
                    ->send();

                $this->dispatchSelectionIfSingle();
            });
    }

    public function editItemAction(): Action
    {
        return Action::make('editItem')
            ->label(__('admin.actions.edit'))
            ->icon(Heroicon::Pencil)
            ->color('gray')
            ->modalWidth(Width::Medium)
            ->schema([
                TextInput::make('title')->label(__('admin.fields.title'))->maxLength(255),
                TextInput::make('alt')->label(__('admin.fields.alt_text'))->maxLength(255),
                Textarea::make('caption')->label(__('admin.fields.caption'))->rows(3)->maxLength(65000),
                Textarea::make('description')->label(__('admin.fields.description'))->rows(5)->maxLength(65000),
            ])
            ->fillForm(function (array $arguments): array {
                $media = $this->trustedRecord($arguments['id'] ?? '', 'update');

                return $media->only(['title', 'alt', 'caption', 'description']);
            })
            ->action(function (array $data, array $arguments): void {
                $media = $this->trustedRecord($arguments['id'] ?? '', 'update');
                $media->fill(Arr::only($data, ['title', 'alt', 'caption', 'description']))->save();
                $this->reloadFiles();

                Notification::make()
                    ->success()
                    ->title(__('admin.media_library.updated'))
                    ->send();
            });
    }

    public function viewItemAction(): Action
    {
        return Action::make('viewItem')
            ->label(__('admin.actions.view'))
            ->icon(Heroicon::Eye)
            ->color('gray')
            ->url(function (array $arguments): string {
                $media = $this->trustedRecord($arguments['id'] ?? '', 'view');

                return route('admin.media-files.view', ['media' => $media->getKey()]);
            }, true);
    }

    public function downloadItemAction(): Action
    {
        return Action::make('downloadItem')
            ->label(__('admin.actions.download'))
            ->icon(Heroicon::ArrowDownTray)
            ->color('gray')
            ->action(function (array $arguments) {
                $media = $this->trustedRecord($arguments['id'] ?? '', 'download');

                return redirect()->to(route('admin.media-files.download', ['media' => $media->getKey()]));
            });
    }

    public function destroyItemAction(): Action
    {
        return Action::make('destroyItem')
            ->label(__('admin.actions.delete'))
            ->icon(Heroicon::Trash)
            ->color('danger')
            ->requiresConfirmation()
            ->action(function (array $arguments): void {
                $media = $this->trustedRecord($arguments['id'] ?? '', 'delete');
                app(MediaFilesystemMutationCoordinator::class)->delete($media, $this->actor());
                $this->selectedIds = array_values(array_diff($this->selectedIds, [(int) $media->getKey()]));
                $this->reloadFiles();
            });
    }

    public function renameItemAction(): Action
    {
        return Action::make('renameItem')
            ->label(__('admin.media_library.rename'))
            ->icon(Heroicon::PencilSquare)
            ->color('gray')
            ->requiresConfirmation()
            ->action(function (array $arguments): void {
                $media = $this->trustedRecord($arguments['id'] ?? '', 'rename');
                app(MediaFilesystemMutationCoordinator::class)->rename($media, $this->actor());
                $this->reloadFiles();
            });
    }

    public function swapItemAction(): Action
    {
        return Action::make('swapItem')
            ->label(__('admin.media_library.swap'))
            ->icon(Heroicon::ArrowsRightLeft)
            ->color('warning')
            ->schema([
                FileUpload::make('replacement')
                    ->label(__('admin.media_library.replacement'))
                    ->acceptedFileTypes(app(CuratorImageUploadPolicy::class)->mimeTypesFor($this->uploadPurpose()))
                    ->maxSize(CuratorImageUploadPolicy::MAX_KILOBYTES)
                    ->storeFiles(false)
                    ->required(),
            ])
            ->action(function (array $data, array $arguments): void {
                $media = $this->trustedRecord($arguments['id'] ?? '', 'swap');
                $replacement = $data['replacement'] ?? null;
                abort_unless($replacement instanceof TemporaryUploadedFile, 422);
                app(MediaFilesystemMutationCoordinator::class)->swap($media, $replacement, $this->actor());
                $this->reloadFiles();
            });
    }

    public function destroySelectedAction(): Action
    {
        return Action::make('destroySelected')
            ->label(__('admin.media_library.delete_selected'))
            ->icon(Heroicon::Trash)
            ->color('danger')
            ->requiresConfirmation()
            ->visible(fn (): bool => ! $this->isInlineOwnerWorkspace && $this->selectedIds !== [])
            ->action(function (): void {
                $records = $this->trustedRecords($this->selectedIds, 'delete');

                app(MediaFilesystemMutationCoordinator::class)->deleteMany($records, $this->actor());

                $this->selectedIds = [];
                $this->reloadFiles();
            });
    }

    public function insertMediaAction(): Action
    {
        return Action::make('insertMedia')
            ->label(__('admin.media_library.use_selected'))
            ->color('success')
            ->visible(fn (): bool => $this->selectedIds !== [])
            ->action(function (): void {
                $this->dispatchSelection();
            });
    }

    public function render(): View
    {
        return view('livewire.admin.media-picker-panel');
    }

    private function reloadFiles(): void
    {
        Gate::forUser($this->actor())->authorize('viewAny', $this->mediaModel());

        if (filled($this->search)) {
            $query = $this->browseQuery();
            $search = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $this->search).'%';
            $query->where(function (Builder $query) use ($search): void {
                $query
                    ->whereRaw("name LIKE ? ESCAPE '!'", [$search])
                    ->orWhereRaw("title LIKE ? ESCAPE '!'", [$search])
                    ->orWhereRaw("alt LIKE ? ESCAPE '!'", [$search])
                    ->orWhereRaw("caption LIKE ? ESCAPE '!'", [$search])
                    ->orWhereRaw("description LIKE ? ESCAPE '!'", [$search]);
            });

            $this->files = $this->projectQuery($query->limit(app(CuratorImageUploadPolicy::class)->pickerSearchLimit()));
            $this->lastPage = 1;

            return;
        }

        $query = $this->browseQuery();
        $count = (clone $query)->count();
        $browseLimit = app(CuratorImageUploadPolicy::class)->pickerBrowseLimit();
        $this->lastPage = max(1, (int) ceil($count / $browseLimit));
        $this->currentPage = min(max(1, $this->currentPage), $this->lastPage);
        $this->files = $this->projectQuery($query->forPage($this->currentPage, $browseLimit));
    }

    /**
     * @return Builder<Media>
     */
    private function browseQuery(): Builder
    {
        return app(MediaRecordScope::class)
            ->inventoryQuery()
            ->when(! $this->allMedia, fn (Builder $query): Builder => $query->where('directory', $this->uploadPurpose()->root()))
            ->select([
                'id', 'reference_key', 'name', 'path', 'title', 'alt', 'ext', 'size', 'width', 'height', 'created_at',
                'disk', 'directory', 'visibility', 'type',
            ])
            ->orderByDesc('id');
    }

    /**
     * @param  Builder<Media>  $query
     * @return array<int, array<string, int|string|null>>
     */
    private function projectQuery(Builder $query): array
    {
        $projector = app(MediaRecordProjector::class);

        return $query
            ->get()
            ->map(fn (Media $media): array => $projector->project($media))
            ->all();
    }

    private function trustedRecord(mixed $id, string $ability): Media
    {
        abort_unless(is_int($id) || is_string($id), 422);
        $scope = app(MediaRecordScope::class);
        $media = in_array($ability, ['delete', 'rename', 'swap'], true)
            ? $scope->findOrFail($id)
            : $scope->findInventoryOrFail($id);
        Gate::forUser($this->actor())->authorize($ability, $media);

        if ($ability === 'select') {
            abort_if(app(MediaInventoryDiagnostics::class)->selectionBlockedReason($media) !== null, 422);
        }

        return $media;
    }

    /**
     * @param  array<int, int|string>  $ids
     * @return array<int, int>
     */
    private function trustedIds(array $ids, string $ability): array
    {
        return $this->trustedRecords($ids, $ability)
            ->map(fn (Media $media): int => (int) $media->getKey())
            ->all();
    }

    /**
     * @param  array<int, int|string>  $ids
     * @return Collection<int, Media>
     */
    private function trustedRecords(array $ids, string $ability): Collection
    {
        $trustedIds = collect($ids)
            ->map(function (mixed $id): int {
                if (! is_int($id) && (! is_string($id) || ! ctype_digit($id))) {
                    abort(422);
                }

                $id = (int) $id;
                abort_if($id < 1, 422);

                return $id;
            })
            ->unique()
            ->values()
            ->all();

        if (count($trustedIds) > self::SELECTION_LIMIT) {
            abort(422);
        }

        $scope = app(MediaRecordScope::class);
        $records = (in_array($ability, ['delete', 'rename', 'swap'], true)
            ? $scope->query()
            : $scope->inventoryQuery())
            ->whereKey($trustedIds)
            ->get()
            ->keyBy(fn (Media $media): int => (int) $media->getKey());

        if ($records->count() !== count($trustedIds)) {
            throw (new ModelNotFoundException)->setModel(
                $this->mediaModel(),
                $trustedIds,
            );
        }

        $actor = $this->actor();

        return collect($trustedIds)
            ->map(function (int $id) use ($records, $actor, $ability): Media {
                /** @var Media $media */
                $media = $records->get($id);
                Gate::forUser($actor)->authorize($ability, $media);

                if ($ability === 'select') {
                    abort_if(app(MediaInventoryDiagnostics::class)->selectionBlockedReason($media) !== null, 422);
                }

                return $media;
            });
    }

    private function selectionLimit(): int
    {
        if (! $this->isMultiple) {
            return 1;
        }

        return min($this->maxItems ?? self::SELECTION_LIMIT, self::SELECTION_LIMIT);
    }

    private function ensureAcquisitionCapacity(string $source, string $field): void
    {
        if (! $this->isMultiple || count($this->selectedIds) < $this->selectionLimit()) {
            return;
        }

        $this->failSource(
            $source,
            $field,
            __('admin.media_library.acquisition_selection_limit_exceeded'),
        );
    }

    /** @param Collection<int, Media> $media */
    private function selectAcquired(Collection $media): void
    {
        $newIds = $this->trustedIds(
            $media->map(fn (Media $item): int => (int) $item->getKey())->all(),
            'select',
        );
        $this->selectedIds = $this->isMultiple
            ? array_values(array_unique([...$this->selectedIds, ...$newIds]))
            : array_slice($newIds, 0, 1);

        if (count($this->selectedIds) > $this->selectionLimit()) {
            abort(422);
        }
    }

    private function reloadStorageFiles(): void
    {
        if (! $this->storageConfigured) {
            $this->storageFiles = [];

            return;
        }

        $this->storageFiles = app(MediaAcquisitionManager::class)->storageCandidates();
    }

    private function loadStorageFiles(): void
    {
        try {
            $this->reloadStorageFiles();
            $this->storageLoaded = true;
        } catch (Throwable $exception) {
            report($exception);

            $this->storageFiles = [];
            $this->storageLoaded = false;
            $this->failSource(
                'storage',
                'storageAcquisition',
                __('admin.media_library.storage_load_failed'),
            );
        }
    }

    /** @return array<int, string> */
    private function sourceErrorKeys(string $source): array
    {
        return match ($source) {
            'upload' => ['panelData.uploads'],
            'url' => ['panelData.external_url'],
            'storage' => ['storageAcquisition'],
            default => [],
        };
    }

    /** @return array<string, bool|string|null> */
    private function sourceSectionAttributes(string $source): array
    {
        $isActive = $this->activeSource === $source;

        return [
            'id' => "media-picker-panel-{$source}",
            'data-testid' => "media-picker-panel-{$source}",
            'class' => $isActive ? 'pt-3' : 'hidden',
            'aria-hidden' => $isActive ? null : 'true',
            'inert' => $isActive ? null : true,
        ];
    }

    private function acquisitionSuccessBody(int $count = 1, bool $reused = false): string
    {
        $mode = $this->isMultiple ? 'multiple' : 'single';
        $disposition = $reused ? 'reused' : 'created';

        return __("admin.media_library.acquisition_{$disposition}_{$mode}", [
            'count' => $count,
        ]);
    }

    private function dispatchSelectionIfSingle(): void
    {
        if (! $this->isMultiple) {
            $this->dispatchSelection();
        }
    }

    private function dispatchSelection(): void
    {
        $ids = $this->trustedIds($this->selectedIds, 'select');

        if (! $this->isMultiple && count($ids) > 1) {
            abort(422);
        }

        if ($this->maxItems !== null && count($ids) > $this->maxItems) {
            abort(422);
        }

        $this->dispatch('insert-media', [
            'mediaId' => $ids[0] ?? null,
            'mediaIds' => $ids,
        ]);
    }

    private function clearSourceInput(string $source): void
    {
        if ($source === 'upload') {
            $this->panelData['uploads'] = [];
        } elseif ($source === 'url') {
            $this->panelData['external_url'] = null;
        }
    }

    private function failSource(string $source, string $field, string $message): never
    {
        $this->activeSource = $source;

        throw ValidationException::withMessages([
            $field => $message,
        ]);
    }

    private function uploadPurpose(): ImageUploadPurpose
    {
        return ImageUploadPurpose::from($this->purpose);
    }

    private function actor(): User
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);

        return $actor;
    }

    /** @return class-string<Media> */
    private function mediaModel(): string
    {
        return config('curator.model', Media::class);
    }
}
