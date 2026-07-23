<?php

namespace App\Livewire\Admin;

use App\Enums\ImageUploadPurpose;
use App\Models\Media;
use App\Models\User;
use App\Support\Media\CuratorImageUploadPolicy;
use App\Support\Media\MediaAcquisitionManager;
use App\Support\Media\MediaFilesystemMutationCoordinator;
use App\Support\Media\MediaInventoryDiagnostics;
use App\Support\Media\MediaRecordProjector;
use App\Support\Media\MediaRecordScope;
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
use UnexpectedValueException;

class MediaPickerPanel extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use RestrictsFileUploadsToSchemaComponents;

    private const SELECTION_LIMIT = 50;

    #[Locked]
    public string $purpose;

    #[Locked]
    public bool $isMultiple = false;

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
    ): void {
        $resolvedPurpose = ImageUploadPurpose::tryFrom($purpose)
            ?? throw new UnexpectedValueException('The media picker purpose is invalid.');

        Gate::forUser($this->actor())->authorize('viewAny', $this->mediaModel());

        $this->purpose = $resolvedPurpose->value;
        $this->isMultiple = $isMultiple;
        $this->maxItems = $maxItems;
        $this->selectedIds = $this->trustedIds($selectedIds, 'view');

        if (count($this->selectedIds) > $this->selectionLimit()) {
            abort(422);
        }

        $this->form->fill();
        $this->reloadFiles();
        $this->reloadStorageFiles();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('panelData')
            ->components([
                Section::make(__('admin.media_library.upload_source'))
                    ->schema([
                        FileUpload::make('uploads')
                            ->label(__('admin.fields.media_files'))
                            ->acceptedFileTypes(app(CuratorImageUploadPolicy::class)->mimeTypesFor($this->uploadPurpose()))
                            ->maxSize(app(CuratorImageUploadPolicy::class)->maxKilobytes())
                            ->multiple()
                            ->maxFiles(app(CuratorImageUploadPolicy::class)->uploadBatchLimit())
                            ->maxParallelUploads(2)
                            ->storeFiles(false),
                    ]),
                Section::make(__('admin.media_library.url_source'))
                    ->schema([
                        TextInput::make('external_url')
                            ->label(__('admin.media_library.url_field'))
                            ->helperText(__('admin.media_library.url_help'))
                            ->url()
                            ->rule('url:https')
                            ->maxLength(2048),
                    ]),
            ]);
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
        $this->reloadStorageFiles();
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
        $this->selectedIds = [];
    }

    public function uploadFilesAction(): Action
    {
        return Action::make('uploadFiles')
            ->label(__('admin.media_library.upload'))
            ->icon(Heroicon::ArrowUpTray)
            ->visible(fn (): bool => count(Arr::wrap($this->form->getRawState()['uploads'] ?? [])) > 0)
            ->action(function (): void {
                $data = $this->form->getState();
                $uploads = array_values(Arr::wrap($data['uploads'] ?? []));

                if (count($uploads) > app(CuratorImageUploadPolicy::class)->uploadBatchLimit()) {
                    abort(422);
                }

                if (
                    $this->isMultiple
                    && count($this->selectedIds) + count($uploads) > $this->selectionLimit()
                ) {
                    throw ValidationException::withMessages([
                        'panelData.uploads' => __('admin.media_library.selection_limit_exceeded'),
                    ]);
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
                    $created = app(MediaAcquisitionManager::class)->acquireUploads(
                        $uploads,
                        $this->uploadPurpose(),
                        $actor,
                    );
                } catch (InvalidArgumentException) {
                    throw ValidationException::withMessages([
                        'panelData.uploads' => __('admin.media_library.upload_invalid'),
                    ]);
                }

                $this->selectAcquired($created);

                $this->form->fill();
                $this->currentPage = 1;
                $this->reloadFiles();

                Notification::make()
                    ->success()
                    ->title(__('admin.media_library.uploaded'))
                    ->send();
            });
    }

    public function acquireUrlAction(): Action
    {
        return Action::make('acquireUrl')
            ->label(__('admin.media_library.acquire_url'))
            ->icon(Heroicon::Link)
            ->visible(fn (): bool => filled($this->form->getRawState()['external_url'] ?? null))
            ->action(function (): void {
                $url = trim((string) ($this->form->getState()['external_url'] ?? ''));

                try {
                    $media = app(MediaAcquisitionManager::class)->acquireExternalUrl(
                        $url,
                        $this->uploadPurpose(),
                        $this->actor(),
                    );
                } catch (InvalidArgumentException|RuntimeException) {
                    throw ValidationException::withMessages([
                        'panelData.external_url' => __('admin.media_library.url_invalid'),
                    ]);
                }

                $this->selectAcquired(collect([$media]));
                $this->form->fill();
                $this->currentPage = 1;
                $this->reloadFiles();

                Notification::make()
                    ->success()
                    ->title(__('admin.media_library.url_acquired'))
                    ->send();
            });
    }

    public function acquireStorageAction(): Action
    {
        return Action::make('acquireStorage')
            ->label(__('admin.media_library.acquire_storage'))
            ->icon(Heroicon::CircleStack)
            ->action(function (array $arguments): void {
                $token = $arguments['token'] ?? null;

                if (! is_string($token)) {
                    abort(422);
                }

                try {
                    $media = app(MediaAcquisitionManager::class)->acquireStorageCandidate(
                        $token,
                        $this->uploadPurpose(),
                        $this->actor(),
                    );
                } catch (InvalidArgumentException|RuntimeException) {
                    throw ValidationException::withMessages([
                        'storageSearch' => __('admin.media_library.storage_invalid'),
                    ]);
                }

                $this->selectAcquired(collect([$media]));
                $this->currentPage = 1;
                $this->reloadFiles();
                $this->reloadStorageFiles();

                Notification::make()
                    ->success()
                    ->title(__('admin.media_library.storage_acquired'))
                    ->send();
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
            ->visible(fn (): bool => $this->selectedIds !== [])
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
        $this->storageFiles = app(MediaAcquisitionManager::class)->storageCandidates($this->storageSearch);
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
