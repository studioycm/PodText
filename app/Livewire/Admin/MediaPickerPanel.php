<?php

namespace App\Livewire\Admin;

use App\Enums\ImageUploadPurpose;
use App\Enums\MediaAcquisitionDisposition;
use App\Enums\MediaDiagnosticReason;
use App\Filament\Resources\Media\MediaResource;
use App\Models\Media;
use App\Models\User;
use App\Support\Media\CuratorImageUploadPolicy;
use App\Support\Media\ExternalImageFailureMessage;
use App\Support\Media\MediaAcquisitionManager;
use App\Support\Media\MediaBulkDeleteCensus;
use App\Support\Media\MediaDetailsViewModel;
use App\Support\Media\MediaFilesystemMutationCoordinator;
use App\Support\Media\MediaInventoryDiagnostics;
use App\Support\Media\MediaLibraryTaskQuery;
use App\Support\Media\MediaOperationReceipts;
use App\Support\Media\MediaRecordProjector;
use App\Support\Media\MediaRecordScope;
use App\Support\Media\MediaReferenceFinder;
use App\Support\Media\MediaUploadBatchResult;
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
use Livewire\Attributes\On;
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

    private const SOURCES = ['gallery', 'upload', 'url', 'storage'];

    private const ROOT_DIRECTORY_FILTER = '__root__';

    #[Locked]
    public string $purpose;

    #[Locked]
    public bool $isMultiple = false;

    #[Locked]
    public bool $isInlineOwnerWorkspace = false;

    #[Locked]
    public bool $isOwnerChoice = false;

    #[Locked]
    public ?int $savedMediaId = null;

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

    public string $searchScope = 'all';

    public string $directoryFilter = '';

    public string $storageSearch = '';

    #[Locked]
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

    /** @var array<int, array{name: string, fate: string, reason: string|null}> */
    #[Locked]
    public array $uploadResults = [];

    #[Locked]
    public ?string $storageErrorToken = null;

    /**
     * @param  array<int, int|string>  $selectedIds
     */
    public function mount(
        string $purpose,
        array $selectedIds = [],
        bool $isMultiple = false,
        ?int $maxItems = null,
        bool $isInlineOwnerWorkspace = false,
        bool $isOwnerChoice = false,
        ?int $savedMediaId = null,
    ): void {
        $resolvedPurpose = ImageUploadPurpose::tryFrom($purpose)
            ?? throw new UnexpectedValueException('The media picker purpose is invalid.');

        Gate::forUser($this->actor())->authorize('viewAny', $this->mediaModel());

        $this->purpose = $resolvedPurpose->value;
        $this->isOwnerChoice = $isOwnerChoice;
        $this->isMultiple = $isOwnerChoice ? false : $isMultiple;
        $this->maxItems = $maxItems;
        $this->isInlineOwnerWorkspace = $isInlineOwnerWorkspace;
        $this->selectedIds = $this->trustedIds($selectedIds, 'view');
        $this->savedMediaId = $savedMediaId === null
            ? null
            : (int) $this->trustedRecord($savedMediaId, 'view')->getKey();
        $this->allMedia = $isOwnerChoice;
        $this->activeSource = $isOwnerChoice ? 'gallery' : 'upload';
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
                Section::make()
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
                                    ? app(CuratorImageUploadPolicy::class)->uploadQueueLimit()
                                    : 1,
                            )
                            ->maxParallelUploads(2)
                            ->panelLayout('grid')
                            ->extraInputAttributes([
                                'data-testid' => 'media-picker-upload-input',
                                'id' => 'media-picker-upload-input',
                            ])
                            ->uploadingMessage(__('admin.media_library.uploading_file'))
                            ->storeFiles(false),
                    ]),
                Section::make()
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
        abort_if($source === 'gallery' && ! $this->isOwnerChoice, 422);

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

    public function updatedSearchScope(): void
    {
        if (! in_array($this->searchScope, ['all', 'title', 'owner', 'filename'], true)) {
            $this->searchScope = 'all';
        }

        $this->currentPage = 1;
        $this->reloadFiles();
    }

    public function updatedDirectoryFilter(): void
    {
        if ($this->directoryFilter !== '' && ! array_key_exists($this->directoryFilter, $this->directoryOptions())) {
            $this->directoryFilter = '';
        }

        $this->currentPage = 1;
        $this->reloadFiles();
    }

    /**
     * @return array<string, string>
     */
    public function directoryOptions(): array
    {
        return once(fn (): array => app(MediaRecordScope::class)
            ->inventoryQuery()
            ->select('directory')
            ->distinct()
            ->orderBy('directory')
            ->pluck('directory')
            ->map(fn (?string $directory): string => (string) $directory)
            ->unique()
            ->values()
            ->mapWithKeys(fn (string $directory): array => $directory === ''
                ? [self::ROOT_DIRECTORY_FILTER => __('admin.media_library.root_directory')]
                : [$directory => $directory])
            ->all());
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

        if ($this->activeSource === 'storage' && $this->storageConfigured) {
            $this->loadStorageFiles();
        }
    }

    public function showContextMedia(): void
    {
        abort_if($this->isOwnerChoice, 422);

        $this->allMedia = false;
        $this->directoryFilter = '';
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
            if ($this->isInlineOwnerWorkspace || $this->isOwnerChoice) {
                return;
            }

            $this->selectedIds = array_values(array_diff($this->selectedIds, [$id]));

            return;
        }

        if (! $this->isMultiple) {
            $this->selectedIds = [$id];

            if ($this->isOwnerChoice) {
                $this->dispatchSelection();
            }

            return;
        }

        if (count($this->selectedIds) >= $this->selectionLimit()) {
            abort(422);
        }

        $this->selectedIds[] = $id;
    }

    public function clearSelection(): void
    {
        if ($this->isOwnerChoice || $this->isInlineOwnerWorkspace) {
            return;
        }

        $this->selectedIds = [];
    }

    #[On('owner-media-selection-restored')]
    public function restoreSavedOwnerSelection(mixed $selectedId = null): void
    {
        abort_unless($this->isOwnerChoice, 404);

        $this->selectedIds = $this->savedMediaId === null
            ? []
            : [(int) $this->trustedRecord($this->savedMediaId, 'view')->getKey()];
    }

    #[On('owner-media-selection-changed')]
    public function synchronizeOwnerSelection(mixed $selectedId): void
    {
        abort_unless($this->isOwnerChoice, 404);

        if ($selectedId === null) {
            $this->selectedIds = [];

            return;
        }

        $this->selectedIds = [(int) $this->trustedRecord($selectedId, 'select')->getKey()];
    }

    public function uploadFilesAction(): Action
    {
        return Action::make('uploadFiles')
            ->label(function (): string {
                $queued = count(Arr::wrap($this->form->getRawState()['uploads'] ?? []));

                if ($this->uploadResults !== [] && $queued > 0) {
                    return __('admin.media_library.upload_retry_remaining', ['count' => $queued]);
                }

                return $this->isInlineOwnerWorkspace
                    ? __('admin.media_library.upload_one_or_multiple')
                    : ($this->isMultiple
                        ? __('admin.media_library.add_and_select')
                        : __('admin.media_library.add_and_choose'));
            })
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

                if (count($uploads) > app(CuratorImageUploadPolicy::class)->uploadQueueLimit()) {
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

                $chunk = array_slice($uploads, 0, app(CuratorImageUploadPolicy::class)->uploadBatchLimit());
                $result = app(MediaAcquisitionManager::class)->acquireUploads(
                    $chunk,
                    $this->uploadPurpose(),
                    $actor,
                );

                $queuedRows = collect($uploads)
                    ->slice(count($chunk))
                    ->map(fn (TemporaryUploadedFile $upload): array => [
                        'name' => $upload->getClientOriginalName(),
                        'fate' => 'not_attempted',
                        'reason' => null,
                    ])
                    ->values()
                    ->all();
                $acquiredHistory = collect($this->uploadResults)
                    ->where('fate', 'acquired')
                    ->values()
                    ->all();
                $this->uploadResults = [
                    ...$acquiredHistory,
                    ...$this->uploadResultRows($chunk, $result),
                    ...$queuedRows,
                ];
                $this->retainUnadmittedUploads($result->admittedIndexes);

                if ($result->successful->isEmpty()) {
                    $this->failSource(
                        'upload',
                        'panelData.uploads',
                        __($result->nothingAdmittedForInvalidFiles()
                            ? 'admin.media_library.upload_invalid'
                            : 'admin.media_library.upload_failed'),
                    );
                }

                if (! $isAcquisitionOnlyBatch) {
                    $this->selectAcquired($result->media());
                }

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
                                'failed' => $result->failedCount,
                                'not_attempted' => $result->notAttemptedCount,
                            ])
                            : __('admin.media_library.upload_partial_body', [
                                'added' => $result->successful->count(),
                                'failed' => $result->failedCount,
                                'not_attempted' => $result->notAttemptedCount,
                            ]))
                        ->send();
                } else {
                    $body = $isAcquisitionOnlyBatch
                        ? __('admin.media_library.acquisition_batch_created', [
                            'count' => $result->successful->count(),
                        ])
                        : $this->acquisitionSuccessBody($result->successful->count());
                    $remaining = count(Arr::wrap($this->panelData['uploads'] ?? []));

                    if ($remaining > 0) {
                        $body .= ' '.__('admin.media_library.upload_more_queued', ['count' => $remaining]);
                    }

                    Notification::make()
                        ->success()
                        ->title(__('admin.media_library.uploaded'))
                        ->body($body)
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

                $this->storageErrorToken = null;
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
                    $this->storageErrorToken = $token;
                    $this->failSource(
                        'storage',
                        'storageAcquisition',
                        __('admin.media_library.storage_busy'),
                    );
                } catch (InvalidArgumentException|RuntimeException) {
                    $this->storageErrorToken = $token;
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
                    ->title($result->disposition->getLabel())
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
            ->hidden(fn (): bool => $this->isOwnerChoice)
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

    public function mediaLibraryUrl(): string
    {
        return MediaResource::getUrl(panel: 'admin');
    }

    public function mediaDetailsAction(): Action
    {
        return Action::make('mediaDetails')
            ->label(__('admin.owner_image.actions.open_details'))
            ->modalHeading(__('admin.owner_image.actions.open_details'))
            ->slideOver()
            ->modalWidth(Width::Medium)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('admin.actions.close'))
            ->modalContent(function (array $arguments): View {
                $media = $this->trustedRecord($arguments['id'] ?? '', 'view');

                return view('livewire.admin.media-details-slide-over', MediaDetailsViewModel::make($media));
            });
    }

    public function viewItemAction(): Action
    {
        return Action::make('viewItem')
            ->label(__('admin.actions.view'))
            ->icon(Heroicon::Eye)
            ->color('gray')
            ->hidden(fn (): bool => $this->isOwnerChoice)
            ->disabled(fn (array $arguments): bool => $this->fileIsMissing($arguments['id'] ?? null))
            ->tooltip(fn (array $arguments): ?string => $this->fileIsMissing($arguments['id'] ?? null)
                ? __('admin.media_library.op_file_missing')
                : null)
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
            ->hidden(fn (): bool => $this->isOwnerChoice)
            ->disabled(fn (array $arguments): bool => $this->fileIsMissing($arguments['id'] ?? null))
            ->tooltip(fn (array $arguments): ?string => $this->fileIsMissing($arguments['id'] ?? null)
                ? __('admin.media_library.op_file_missing')
                : null)
            ->action(function (array $arguments) {
                $media = $this->trustedRecord($arguments['id'] ?? '', 'download');

                return redirect()->to(route('admin.media-files.download', ['media' => $media->getKey()]));
            });
    }

    public function destroyItemAction(): Action
    {
        return Action::make('destroyItem')
            ->label(__('admin.media_library.delete_permanently'))
            ->icon(Heroicon::Trash)
            ->color('danger')
            ->requiresConfirmation()
            ->hidden(fn (): bool => $this->isOwnerChoice)
            ->disabled(fn (array $arguments): bool => ! $this->operationAllowed($arguments['id'] ?? null, 'delete'))
            ->tooltip(fn (array $arguments): ?string => $this->operationBlockedReason($arguments['id'] ?? null, 'delete'))
            ->modalContent(fn (array $arguments): View => $this->panelOperationConsequence($arguments['id'] ?? 0, 'delete'))
            ->modalSubmitActionLabel(__('admin.media_library.delete_permanently'))
            ->action(function (array $arguments): void {
                $media = $this->trustedRecord($arguments['id'] ?? '', 'delete');
                $name = (string) ($media->title ?: $media->name);

                try {
                    app(MediaFilesystemMutationCoordinator::class)->delete($media, $this->actor());
                } catch (ValidationException $exception) {
                    app(MediaOperationReceipts::class)->operationFailed($exception->getMessage());

                    return;
                } catch (RuntimeException) {
                    app(MediaOperationReceipts::class)->operationFailed();

                    return;
                }

                app(MediaOperationReceipts::class)->deleteSucceeded($this->actor(), $name);
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
            ->hidden(fn (): bool => $this->isOwnerChoice)
            ->disabled(fn (array $arguments): bool => ! $this->operationAllowed($arguments['id'] ?? null, 'rename'))
            ->tooltip(fn (array $arguments): ?string => $this->operationBlockedReason($arguments['id'] ?? null, 'rename'))
            ->modalContent(fn (array $arguments): View => $this->panelOperationConsequence($arguments['id'] ?? 0, 'rename'))
            ->modalSubmitActionLabel(__('admin.media_library.rename_submit'))
            ->action(function (array $arguments): void {
                $media = $this->trustedRecord($arguments['id'] ?? '', 'rename');
                $oldName = basename((string) $media->path);

                try {
                    $updated = app(MediaFilesystemMutationCoordinator::class)->rename($media, $this->actor());
                } catch (ValidationException $exception) {
                    app(MediaOperationReceipts::class)->operationFailed($exception->getMessage());

                    return;
                } catch (RuntimeException) {
                    app(MediaOperationReceipts::class)->operationFailed();

                    return;
                }

                app(MediaOperationReceipts::class)->renameSucceeded($this->actor(), $oldName, basename((string) $updated->path));
                $this->reloadFiles();
            });
    }

    public function swapItemAction(): Action
    {
        return Action::make('swapItem')
            ->label(__('admin.media_library.swap'))
            ->icon(Heroicon::ArrowsRightLeft)
            ->color('warning')
            ->hidden(fn (): bool => $this->isOwnerChoice)
            ->disabled(fn (array $arguments): bool => ! $this->operationAllowed($arguments['id'] ?? null, 'swap'))
            ->tooltip(fn (array $arguments): ?string => $this->operationBlockedReason($arguments['id'] ?? null, 'swap'))
            ->modalContent(fn (array $arguments): View => $this->panelOperationConsequence($arguments['id'] ?? 0, 'swap'))
            ->modalSubmitActionLabel(__('admin.media_library.swap_submit'))
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
                $oldSize = (int) $media->size;

                try {
                    $updated = app(MediaFilesystemMutationCoordinator::class)->swap($media, $replacement, $this->actor());
                } catch (ValidationException $exception) {
                    app(MediaOperationReceipts::class)->operationFailed($exception->getMessage());

                    return;
                } catch (RuntimeException) {
                    app(MediaOperationReceipts::class)->operationFailed();

                    return;
                }

                app(MediaOperationReceipts::class)->swapSucceeded($this->actor(), $oldSize, (int) $updated->size);
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
            ->visible(fn (): bool => ! $this->isOwnerChoice
                && ! $this->isInlineOwnerWorkspace
                && $this->selectedIds !== [])
            ->modalContent(fn (): View => app(MediaBulkDeleteCensus::class)->censusView(
                $this->selectedInventoryRecords(),
                $this->actor(),
            ))
            ->modalSubmitActionLabel(__('admin.media_library.bulk_delete_submit'))
            ->action(function (): void {
                Gate::forUser($this->actor())->authorize('deleteAny', $this->mediaModel());

                $result = app(MediaBulkDeleteCensus::class)->execute(
                    $this->selectedInventoryRecords(),
                    $this->actor(),
                );

                app(MediaOperationReceipts::class)->bulkDeleteFinished(
                    $this->actor(),
                    $result['deleted'],
                    $result['blocked'],
                    $result['failed'],
                );

                $this->selectedIds = array_values(array_diff(
                    array_map(intval(...), $this->selectedIds),
                    $result['deleted_ids'],
                ));
                $this->reloadFiles();
            });
    }

    /**
     * @return Collection<int, Media>
     */
    private function selectedInventoryRecords(): Collection
    {
        $ids = array_values(array_filter(array_map(
            fn (mixed $id): int => is_int($id) || ctype_digit((string) $id) ? (int) $id : 0,
            $this->selectedIds,
        )));

        return app(MediaRecordScope::class)
            ->inventoryQuery()
            ->whereKey($ids)
            ->get();
    }

    public function insertMediaAction(): Action
    {
        return Action::make('insertMedia')
            ->label(__('admin.media_library.use_selected'))
            ->color('success')
            ->visible(fn (): bool => ! $this->isOwnerChoice && $this->selectedIds !== [])
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
            $scope = $this->searchScope;
            $rawTerm = $this->search;
            $query->where(function (Builder $query) use ($search, $scope, $rawTerm): void {
                if (in_array($scope, ['all', 'filename'], true)) {
                    $query->orWhereRaw("name LIKE ? ESCAPE '!'", [$search]);
                }

                if (in_array($scope, ['all', 'title'], true)) {
                    $query
                        ->orWhereRaw("title LIKE ? ESCAPE '!'", [$search])
                        ->orWhereRaw("alt LIKE ? ESCAPE '!'", [$search]);
                }

                if ($scope === 'all') {
                    $query
                        ->orWhereRaw("caption LIKE ? ESCAPE '!'", [$search])
                        ->orWhereRaw("description LIKE ? ESCAPE '!'", [$search]);
                }

                if (in_array($scope, ['all', 'owner'], true)) {
                    app(MediaLibraryTaskQuery::class)->applyOwnerTitleSearch($query, $rawTerm);
                }
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
            ->when(
                ! $this->isOwnerChoice && ! $this->allMedia,
                fn (Builder $query): Builder => $query->where('directory', $this->uploadPurpose()->root()),
            )
            ->when(
                $this->directoryFilter !== '' && ($this->isOwnerChoice || $this->allMedia),
                fn (Builder $query): Builder => $this->directoryFilter === self::ROOT_DIRECTORY_FILTER
                    ? $query->where(fn (Builder $query): Builder => $query->whereNull('directory')->orWhere('directory', ''))
                    : $query->where('directory', $this->directoryFilter),
            )
            ->select([
                'id', 'reference_key', 'name', 'path', 'title', 'alt', 'ext', 'size', 'width', 'height', 'created_at',
                'disk', 'directory', 'visibility', 'type',
                // PublicMediaDelivery reads both when deciding inline-SVG
                // safety: trusted_at for the trust short-circuit, updated_at
                // in the cache key — omitting it silently keys on 0.
                'trusted_at', 'updated_at',
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

        if (! $this->isOwnerChoice) {
            $model = $this->mediaModel();
            $table = (new $model)->getTable();
            $query
                ->select("{$table}.*")
                ->addSelect([
                    'storage_identity_count' => $model::query()
                        ->from("{$table} as storage_identity")
                        ->selectRaw('count(*)')
                        ->whereColumn('storage_identity.disk', "{$table}.disk")
                        ->whereColumn('storage_identity.path', "{$table}.path"),
                ]);
        }

        $records = $query->get();

        if (! $this->isOwnerChoice) {
            app(MediaReferenceFinder::class)->prime($records);
        }

        return $records
            ->map(function (Media $media) use ($projector): array {
                $projection = $projector->project(
                    $media,
                    withOwnerDetails: $this->isOwnerChoice,
                );

                if (! $this->isOwnerChoice) {
                    $projection['ops'] = $this->operationAvailability($media);
                }

                return $projection;
            })
            ->all();
    }

    /**
     * @return array<string, array{allowed: bool, reason: ?string}>
     */
    private function operationAvailability(Media $media): array
    {
        $gate = Gate::forUser($this->actor());
        $describe = function (string $ability) use ($gate, $media): array {
            $response = $gate->inspect($ability, $media);

            return [
                'allowed' => $response->allowed(),
                'reason' => $response->allowed() ? null : ($response->message() ?: null),
            ];
        };
        $mutate = $describe('rename');

        return [
            'rename' => $mutate,
            'swap' => $mutate,
            'delete' => $describe('delete'),
        ];
    }

    private function fileProjection(mixed $id): ?array
    {
        foreach ($this->files as $file) {
            if ((int) ($file['id'] ?? 0) === (int) $id) {
                return $file;
            }
        }

        return null;
    }

    private function operationAllowed(mixed $id, string $operation): bool
    {
        $ops = $this->fileProjection($id)['ops'] ?? null;

        return $ops === null || (bool) ($ops[$operation]['allowed'] ?? false);
    }

    private function operationBlockedReason(mixed $id, string $operation): ?string
    {
        $ops = $this->fileProjection($id)['ops'] ?? null;

        return $ops === null ? null : ($ops[$operation]['reason'] ?? null);
    }

    private function fileIsMissing(mixed $id): bool
    {
        $reasons = $this->fileProjection($id)['repair_reasons'] ?? [];

        return in_array(MediaDiagnosticReason::MissingFile->value, $reasons, true);
    }

    private function panelOperationConsequence(mixed $id, string $operation): View
    {
        $media = app(MediaRecordScope::class)->findInventoryOrFail((int) $id);

        return view('filament.resources.media.operation-consequence', [
            'projection' => app(MediaRecordProjector::class)->project($media),
            'storedFilename' => basename((string) $media->path),
            'operation' => $operation,
        ]);
    }

    private function trustedRecord(mixed $id, string $ability): Media
    {
        abort_unless(is_int($id) || is_string($id), 422);
        $media = app(MediaRecordScope::class)->findInventoryOrFail($id);
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

        $this->storageFiles = app(MediaAcquisitionManager::class)->storageCandidates($this->storageSearch);
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
        $disposition = ($reused ? MediaAcquisitionDisposition::Reused : MediaAcquisitionDisposition::Created)->value;

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

        $this->dispatch(
            'insert-media',
            mediaId: $ids[0] ?? null,
            mediaIds: $ids,
        );
    }

    /**
     * @param  array<int, TemporaryUploadedFile>  $uploads
     * @return array<int, array{name: string, fate: string, reason: string|null}>
     */
    private function uploadResultRows(array $uploads, MediaUploadBatchResult $result): array
    {
        $failedReasons = $result->failed->keyBy('index');
        $heavyBytes = app(CuratorImageUploadPolicy::class)->heavyUploadWarningKilobytes() * 1024;

        return collect($uploads)
            ->map(function (TemporaryUploadedFile $upload, int $index) use ($failedReasons, $result, $heavyBytes): array {
                $fate = match (true) {
                    in_array($index, $result->admittedIndexes, true) => 'acquired',
                    $failedReasons->has($index) => 'failed',
                    default => 'not_attempted',
                };
                $size = (int) $upload->getSize();

                return [
                    'name' => $upload->getClientOriginalName(),
                    'fate' => $fate,
                    'reason' => $fate === 'failed' ? (string) $failedReasons->get($index)['reason'] : null,
                    'heavy' => $fate === 'acquired' && $size > $heavyBytes,
                    'size' => $size,
                ];
            })
            ->values()
            ->all();
    }

    /** @param array<int, int> $admittedIndexes */
    private function retainUnadmittedUploads(array $admittedIndexes): void
    {
        $position = 0;
        $remaining = [];

        foreach (Arr::wrap($this->panelData['uploads'] ?? []) as $key => $file) {
            if (! in_array($position, $admittedIndexes, true)) {
                $remaining[$key] = $file;
            }

            $position++;
        }

        $this->panelData['uploads'] = $remaining;
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
