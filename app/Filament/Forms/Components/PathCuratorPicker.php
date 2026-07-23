<?php

namespace App\Filament\Forms\Components;

use App\Enums\ImageUploadPurpose;
use App\Filament\Resources\Media\MediaResource;
use App\Livewire\Admin\MediaPickerPanel;
use App\Models\Media;
use App\Models\User;
use App\Support\Media\MediaAttachmentFormState;
use App\Support\Media\MediaIdentityResolver;
use App\Support\Media\MediaInventoryDiagnostics;
use App\Support\Media\MediaRecordProjector;
use App\Support\Media\MediaRecordScope;
use Filament\Actions\Action;
use Filament\Forms\Components\Field;
use Filament\Schemas\Components\Livewire as LivewireSchemaComponent;
use Filament\Support\Components\Attributes\ExposedLivewireMethod;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Livewire\Attributes\Renderless;
use LogicException;

class PathCuratorPicker extends Field
{
    protected string $view = 'filament.forms.components.path-curator-picker';

    private ?ImageUploadPurpose $uploadPurpose = null;

    private string $buttonLabel;

    private bool $multiple = false;

    private ?int $maximumItems = null;

    private bool $dehydratesReferenceKey = false;

    /** @var array<string, array<string, mixed>> */
    private array $selectedItems = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->buttonLabel = __('admin.actions.pick_media');

        $this->registerActions([
            fn (): Action => $this->getDownloadAction(),
            fn (): Action => $this->getEditAction(),
            fn (): Action => $this->getRemoveAction(),
            fn (): Action => $this->getRemoveAllAction(),
            fn (): Action => $this->getViewAction(),
            fn (): Action => $this->getPickerAction(),
        ]);

        $this->afterStateHydrated(function (PathCuratorPicker $component, mixed $state): void {
            $trusted = $component->trustedIdentity($state, preserveExisting: true);
            $component->state($trusted);
            $component->hydrateSelectedItems($trusted);
        });

        $this->clearAfterStateUpdatedHooks();
        $this->afterStateUpdated(function (PathCuratorPicker $component, mixed $state, mixed $old): void {
            $trusted = $component->trustedIdentity($state);
            $oldTrusted = $component->trustedIdentity($old, preserveExisting: true);

            if (! $component->acceptsNewSelections($trusted, $oldTrusted)) {
                return;
            }

            $component->state($trusted);
            $component->hydrateSelectedItems($trusted);
        });

        $this->dehydrateStateUsing(function (PathCuratorPicker $component, mixed $state): array|string|null {
            $identities = collect($component->identityValues($state))
                ->map(fn (mixed $identity): ?string => $component->dehydrateIdentity($identity))
                ->filter(fn (?string $identity): bool => filled($identity))
                ->values()
                ->all();

            return $component->isMultiple()
                ? $identities
                : ($identities[0] ?? null);
        });
    }

    public function purpose(ImageUploadPurpose $purpose): static
    {
        $this->uploadPurpose = $purpose;

        return $this;
    }

    public function buttonLabel(string $label): static
    {
        $this->buttonLabel = $label;

        return $this;
    }

    public function getButtonLabel(): string
    {
        return $this->buttonLabel;
    }

    public function multiple(bool $condition = true): static
    {
        $this->multiple = $condition;

        return $this;
    }

    public function isMultiple(): bool
    {
        return $this->multiple;
    }

    public function maxItems(int $items): static
    {
        $this->maximumItems = $items;

        return $this;
    }

    public function referenceKeyIdentity(bool $condition = true): static
    {
        $this->dehydratesReferenceKey = $condition;

        return $this;
    }

    public function getMaxItems(): ?int
    {
        return $this->maximumItems;
    }

    public function getUploadPurpose(): ImageUploadPurpose
    {
        return $this->uploadPurpose
            ?? throw new LogicException('An app-owned image upload purpose is required.');
    }

    #[ExposedLivewireMethod]
    public function updateState(array $arguments): void
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);
        $rawIds = is_array($arguments['mediaIds'] ?? null)
            ? $arguments['mediaIds']
            : [$arguments['mediaId'] ?? null];
        $ids = collect($rawIds)
            ->map(function (mixed $id): int {
                abort_unless(is_int($id) || (is_string($id) && ctype_digit($id)), 422);

                return (int) $id;
            })
            ->unique()
            ->values();
        $limit = $this->isMultiple() ? min($this->getMaxItems() ?? 50, 50) : 1;
        abort_if($ids->isEmpty() || $ids->count() > $limit, 422);
        $media = app(MediaRecordScope::class)
            ->inventoryQuery()
            ->whereKey($ids->all())
            ->get()
            ->keyBy(fn (Media $record): int => (int) $record->getKey());
        abort_if($media->count() !== $ids->count(), 404);

        $ids->each(function (int $id) use ($actor, $media): void {
            /** @var Media $record */
            $record = $media->get($id);
            Gate::forUser($actor)->authorize('select', $record);
            Gate::forUser($actor)->authorize('attach', $record);
            abort_if(app(MediaInventoryDiagnostics::class)->selectionBlockedReason($record) !== null, 422);
        });

        $this->state($this->isMultiple() ? $ids->all() : $ids->first());
        $this->selectedItems = $ids
            ->mapWithKeys(function (int $id) use ($media): array {
                /** @var Media $record */
                $record = $media->get($id);

                return [(string) $id => app(MediaRecordProjector::class)->project($record)];
            })
            ->all();
        $this->partiallyRender();
    }

    #[ExposedLivewireMethod]
    #[Renderless]
    public function closePicker(): void
    {
        $livewire = $this->getLivewire();

        if (
            (! method_exists($livewire, 'getMountedAction')) ||
            (! method_exists($livewire, 'unmountAction'))
        ) {
            return;
        }

        $action = $livewire->getMountedAction();

        if (
            ($action?->getName() !== 'launchPanel') ||
            ($action->getSchemaComponent()?->getKey() !== $this->getKey())
        ) {
            return;
        }

        $livewire->unmountAction(false);
    }

    public function getDownloadAction(): Action
    {
        return Action::make('download')
            ->label(__('admin.actions.download'))
            ->icon(Heroicon::ArrowDownTray)
            ->color('gray')
            ->action(function (array $arguments) {
                $media = $this->trustedActionRecord($arguments, 'download');

                return redirect()->to(route('admin.media-files.download', ['media' => $media->getKey()]));
            });
    }

    public function getEditAction(): Action
    {
        return Action::make('edit')
            ->label(__('admin.actions.edit'))
            ->icon(Heroicon::Pencil)
            ->color('gray')
            ->hidden(fn (PathCuratorPicker $component): bool => $component->isDisabled())
            ->url(function (array $arguments): string {
                $media = $this->trustedActionRecord($arguments, 'update');

                return MediaResource::getUrl('edit', ['record' => $media]);
            }, true);
    }

    public function getViewAction(): Action
    {
        return Action::make('view')
            ->label(__('admin.actions.view'))
            ->icon(Heroicon::Eye)
            ->color('gray')
            ->url(function (array $arguments): string {
                $media = $this->trustedActionRecord($arguments, 'view');

                return route('admin.media-files.view', ['media' => $media->getKey()]);
            }, true);
    }

    public function getPickerAction(): Action
    {
        return Action::make('launchPanel')
            ->label($this->getButtonLabel())
            ->button()
            ->color('gray')
            ->outlined()
            ->size('md')
            ->extraAttributes(fn (PathCuratorPicker $component): array => [
                'data-testid' => 'media-picker-open',
                'id' => $component->getPickerFocusTargetId(),
            ])
            ->modalSubmitAction(false)
            ->modalCancelAction(false)
            ->modalWidth(Width::Screen)
            ->modalCloseButton(false)
            ->closeModalByClickingAway(false)
            ->closeModalByEscaping(false)
            ->formWrapper(false)
            ->schema(function (PathCuratorPicker $component): array {
                $componentKey = $component->getKey();
                $focusTargetId = $component->getPickerFocusTargetId();
                $restoreFocus = "requestAnimationFrame(() => requestAnimationFrame(() => document.getElementById('{$focusTargetId}')?.focus({ preventScroll: true })))";
                $closePicker = "\$wire.callSchemaComponentMethod('{$componentKey}', 'closePicker')";
                $closeHandler = <<<JS
                    if (returningSelection) {
                        return;
                    }

                    if (navigator.onLine) {
                        await \$wire.unmountAction(false);
                        {$restoreFocus};

                        return;
                    }

                    const modal = \$el.closest('[data-fi-modal-id]');

                    if (! modal) {
                        {$restoreFocus};

                        return;
                    }

                    const modalId = modal.id;
                    const actionMatch = modalId.match(/^(.*-action-)(\d+)$/);
                    offlineClosePending = true;
                    // Keep Filament's full focus-trap and scroll-lock cleanup,
                    // but suppress this modal's unreachable server unmount.
                    modal.addEventListener('modal-closed', (event) => {
                        if (event.detail?.id !== modalId) {
                            return;
                        }

                        event.stopImmediatePropagation();
                    }, { capture: true, once: true });
                    window.dispatchEvent(new CustomEvent('close-modal', {
                        bubbles: true,
                        composed: true,
                        detail: { id: modalId },
                    }));
                    // Update Filament's window-level action-modal bookkeeping
                    // without bubbling through the suppressed server listener.
                    window.dispatchEvent(new CustomEvent('modal-closed', {
                        detail: { id: modalId },
                    }));

                    if (actionMatch && (Number(actionMatch[2]) > 0)) {
                        window.dispatchEvent(new CustomEvent('open-modal', {
                            bubbles: true,
                            composed: true,
                            detail: {
                                id: actionMatch[1] + (Number(actionMatch[2]) - 1),
                            },
                        }));
                    }

                    {$restoreFocus};
                    JS;
                $onlineHandler = <<<JS
                    if ((! offlineClosePending) || offlineCloseReconciling) {
                        return;
                    }

                    offlineCloseReconciling = true;

                    try {
                        await {$closePicker};
                        offlineClosePending = false;
                    } catch {
                        // Keep the close pending for a later online event.
                    } finally {
                        offlineCloseReconciling = false;
                    }
                    JS;
                $insertHandler = <<<JS
                    if (returningSelection) {
                        return;
                    }

                    returningSelection = true;

                    try {
                        await \$wire.callSchemaComponentMethod('{$componentKey}', 'updateState', \$event.detail);
                        await \$wire.unmountAction(false);
                        {$restoreFocus};
                    } finally {
                        returningSelection = false;
                    }
                    JS;

                return [
                    LivewireSchemaComponent::make(MediaPickerPanel::class, [
                        'purpose' => $component->getUploadPurpose()->value,
                        'selectedIds' => collect($component->identityValues($component->getState()))
                            ->filter(fn (mixed $identity): bool => is_int($identity) || (is_string($identity) && ctype_digit($identity)))
                            ->map(fn (int|string $identity): int => (int) $identity)
                            ->values()
                            ->all(),
                        'isMultiple' => $component->isMultiple(),
                        'maxItems' => $component->getMaxItems(),
                    ])
                        ->key("media-picker-workspace-{$componentKey}")
                        ->columnSpanFull()
                        ->extraAttributes([
                            'x-data' => '{ offlineClosePending: false, offlineCloseReconciling: false, returningSelection: false }',
                            'x-on:close-media-picker' => $closeHandler,
                            'x-on:online.window' => $onlineHandler,
                            'x-on:insert-media' => $insertHandler,
                        ]),
                ];
            })
            ->action(fn (): null => null);
    }

    public function getPickerFocusTargetId(): string
    {
        return 'media-picker-open-'.substr(hash('sha256', (string) $this->getKey()), 0, 12);
    }

    public function getRemoveAction(): Action
    {
        return Action::make('remove')
            ->label(__('admin.media_library.remove'))
            ->icon(Heroicon::MinusCircle)
            ->color('gray')
            ->hidden(fn (PathCuratorPicker $component): bool => $component->isDisabled())
            ->action(function (array $arguments, PathCuratorPicker $component): void {
                $component->authorizeDetachForState();
                $component->state(null);
            });
    }

    public function getRemoveAllAction(): Action
    {
        return Action::make('removeAll')
            ->label(__('admin.media_library.clear_selection'))
            ->color('danger')
            ->outlined()
            ->hidden(fn (PathCuratorPicker $component): bool => $component->isDisabled())
            ->action(function (PathCuratorPicker $component): void {
                $component->authorizeDetachForState();
                $component->state([]);
            });
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function trustedActionRecord(array $arguments, string $ability): Media
    {
        $id = $arguments['id'] ?? null;
        $media = app(MediaRecordScope::class)->findInventoryOrFail(
            is_int($id) || is_string($id) ? $id : '',
        );
        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);
        Gate::forUser($actor)->authorize($ability, $media);

        return $media;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getSelectedItems(): array
    {
        $this->hydrateSelectedItems($this->trustedIdentity(
            $this->getState(),
            preserveExisting: true,
        ));

        return $this->selectedItems;
    }

    private function trustedIdentity(mixed $state, bool $preserveExisting = false): array|int|string|null
    {
        $values = $this->identityValues($state);
        $numericIds = collect($values)
            ->filter(fn (mixed $identity): bool => is_int($identity) || (is_string($identity) && ctype_digit($identity)))
            ->map(fn (int|string $identity): int => (int) $identity)
            ->unique()
            ->values();

        if ($numericIds->count() === count($values) && $numericIds->isNotEmpty()) {
            $records = app(MediaRecordScope::class)
                ->inventoryQuery()
                ->whereKey($numericIds->all())
                ->get()
                ->keyBy(fn (Media $media): int => (int) $media->getKey());
            abort_if($records->count() !== $numericIds->count(), 404);
            $actor = auth()->user();
            abort_unless($actor instanceof User, 403);
            $numericIds->each(function (int $id) use ($records, $actor, $preserveExisting): void {
                Gate::forUser($actor)->authorize($preserveExisting ? 'view' : 'select', $records->get($id));
            });

            return $this->isMultiple() ? $numericIds->all() : $numericIds->first();
        }

        $identities = collect($values)
            ->map(fn (mixed $identity): int|string|null => $this->trustedSingleIdentity($identity, $preserveExisting))
            ->filter(fn (mixed $identity): bool => $identity !== null)
            ->unique()
            ->values()
            ->all();

        return $this->isMultiple() ? $identities : ($identities[0] ?? null);
    }

    private function trustedSingleIdentity(mixed $identity, bool $preserveExisting = false): int|string|null
    {
        $identity = is_array($identity)
            ? ($identity['id'] ?? $identity['reference_key'] ?? null)
            : $identity;
        $media = null;

        if (($preservedMediaId = MediaAttachmentFormState::preservedMediaId($identity)) !== null) {
            $media = app(MediaRecordScope::class)->findInventoryOrFail($preservedMediaId);
        } elseif (is_int($identity) || (is_string($identity) && ctype_digit($identity))) {
            $media = app(MediaRecordScope::class)->findInventoryOrFail($identity);
        } elseif (is_string($identity) && preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/i', $identity)) {
            $media = app(MediaRecordScope::class)->findByReferenceKey($identity, $this->getUploadPurpose());

            abort_unless($media instanceof Media, 404);
        } elseif (is_string($identity)) {
            try {
                $media = app(MediaIdentityResolver::class)->resolve(null, $identity, $this->getUploadPurpose());

                if (! $media instanceof Media) {
                    $path = app(MediaIdentityResolver::class)->path(null, $identity, $this->getUploadPurpose());
                    abort_if($this->dehydratesReferenceKey, 422);

                    return $path;
                }
            } catch (InvalidArgumentException) {
                abort(422);
            }
        } elseif ($identity !== null) {
            abort(422);
        }

        if ($media instanceof Media) {
            $actor = auth()->user();
            abort_unless($actor instanceof User, 403);
            Gate::forUser($actor)->authorize($preserveExisting ? 'view' : 'select', $media);

            return (int) $media->getKey();
        }

        return null;
    }

    private function dehydrateIdentity(mixed $identity): ?string
    {
        if (is_int($identity) || (is_string($identity) && ctype_digit($identity))) {
            $media = app(MediaRecordScope::class)->findInventoryOrFail($identity);
            $actor = auth()->user();
            abort_unless($actor instanceof User, 403);
            Gate::forUser($actor)->authorize(
                app(MediaRecordScope::class)->hasPortableReferenceKey($media) ? 'select' : 'view',
                $media,
            );

            if (! $this->dehydratesReferenceKey) {
                return $media->path;
            }

            return app(MediaRecordScope::class)->hasPortableReferenceKey($media)
                ? $media->reference_key
                : MediaAttachmentFormState::preservedMediaIdentity((int) $media->getKey());
        }

        if ($this->dehydratesReferenceKey && filled($identity)) {
            abort(422);
        }

        return is_string($identity) && filled($identity) ? $identity : null;
    }

    private function acceptsNewSelections(mixed $state, mixed $oldState): bool
    {
        $oldIds = collect($this->identityValues($oldState))
            ->filter(fn (mixed $identity): bool => is_int($identity) || (is_string($identity) && ctype_digit($identity)))
            ->map(fn (int|string $identity): int => (int) $identity);
        $newIds = collect($this->identityValues($state))
            ->filter(fn (mixed $identity): bool => is_int($identity) || (is_string($identity) && ctype_digit($identity)))
            ->map(fn (int|string $identity): int => (int) $identity)
            ->diff($oldIds)
            ->unique()
            ->values();
        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);

        foreach ($newIds as $id) {
            $media = app(MediaRecordScope::class)->findInventoryOrFail($id);
            Gate::forUser($actor)->authorize('select', $media);
            Gate::forUser($actor)->authorize('attach', $media);
            $blockedReason = app(MediaInventoryDiagnostics::class)->selectionBlockedReason($media);

            if ($blockedReason === null) {
                continue;
            }

            $this->state($oldState);
            $this->hydrateSelectedItems($oldState);
            $this->getLivewire()->addError((string) $this->getStatePath(), $blockedReason);

            return false;
        }

        $this->getLivewire()->resetErrorBag((string) $this->getStatePath());

        return true;
    }

    private function authorizeDetachForState(): void
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);
        Gate::forUser($actor)->authorize('viewAny', config('curator.model', Media::class));

        collect($this->identityValues($this->getState()))
            ->filter(fn (mixed $identity): bool => is_int($identity) || (is_string($identity) && ctype_digit($identity)))
            ->each(function (int|string $identity) use ($actor): void {
                $media = app(MediaRecordScope::class)->findInventoryOrFail($identity);
                Gate::forUser($actor)->authorize('detach', $media);
            });
    }

    private function hydrateSelectedItems(mixed $state): void
    {
        $values = $this->identityValues($state);
        $ids = collect($values)
            ->filter(fn (mixed $identity): bool => is_int($identity) || (is_string($identity) && ctype_digit($identity)))
            ->map(fn (int|string $identity): int => (int) $identity)
            ->unique()
            ->values();

        if ($ids->isNotEmpty()) {
            $records = app(MediaRecordScope::class)
                ->inventoryQuery()
                ->whereKey($ids->all())
                ->get()
                ->keyBy(fn (Media $media): int => (int) $media->getKey());
            $this->selectedItems = $ids
                ->filter(fn (int $id): bool => $records->has($id))
                ->mapWithKeys(function (int $id) use ($records): array {
                    /** @var Media $media */
                    $media = $records->get($id);

                    return [(string) $id => app(MediaRecordProjector::class)->project($media)];
                })
                ->all();

            return;
        }

        $legacyPath = collect($values)->first(fn (mixed $value): bool => is_string($value) && filled($value));

        if (! is_string($legacyPath)) {
            $this->selectedItems = [];

            return;
        }

        $this->selectedItems = ['legacy' => [
            'id' => null,
            'pretty_name' => basename($legacyPath),
            'alt' => '',
            'ext' => mb_strtolower(pathinfo($legacyPath, PATHINFO_EXTENSION)),
            'size' => 0,
            'width' => null,
            'height' => null,
            'preview_url' => null,
        ]];
    }

    /** @return array<int, mixed> */
    private function identityValues(mixed $state): array
    {
        if (! is_array($state)) {
            return $state === null ? [] : [$state];
        }

        if (array_is_list($state)) {
            return $state;
        }

        if (array_key_exists('id', $state) || array_key_exists('reference_key', $state)) {
            return [$state];
        }

        return [];
    }
}
