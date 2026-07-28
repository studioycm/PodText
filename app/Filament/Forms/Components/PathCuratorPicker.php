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
use App\Support\Media\OwnerImageChoicePresentation;
use Closure;
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

    private bool $inlineOwnerWorkspace = false;

    private bool $ownerChoice = false;

    private ?Closure $ownerChoicePresentation = null;

    private ?OwnerImageChoicePresentation $cachedOwnerChoicePresentation = null;

    private bool $hasCachedOwnerChoicePresentation = false;

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
            fn (): Action => $this->getRemoveDirectOwnerImageAction(),
            fn (): Action => $this->getPickerAction(),
        ]);

        $this->afterStateHydrated(function (PathCuratorPicker $component, mixed $state): void {
            $component->invalidateOwnerChoicePresentation();

            if ($component->hydrateSavedOwnerIdentity($state)) {
                return;
            }

            $trusted = $component->trustedIdentity($state, preserveExisting: true);
            $component->state($trusted);
            $component->hydrateSelectedItems($trusted);
        });

        $this->clearAfterStateUpdatedHooks();
        $this->afterStateUpdated(function (PathCuratorPicker $component, mixed $state, mixed $old): void {
            $component->invalidateOwnerChoicePresentation();
            $trusted = $component->trustedIdentity(
                $state,
                preserveExisting: $component->isSavedBrokenOwnerIdentity($state),
            );
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
        return ! $this->isOwnerChoice() && $this->multiple;
    }

    public function inlineOwnerWorkspace(bool $condition = true): static
    {
        $this->inlineOwnerWorkspace = $condition;

        return $this;
    }

    public function isInlineOwnerWorkspace(): bool
    {
        return $this->inlineOwnerWorkspace;
    }

    public function ownerChoice(Closure $presentation): static
    {
        $this->ownerChoice = true;
        $this->ownerChoicePresentation = $presentation;
        $this->invalidateOwnerChoicePresentation();

        return $this;
    }

    public function isOwnerChoice(): bool
    {
        return $this->ownerChoice;
    }

    public function getOwnerChoicePresentation(): ?OwnerImageChoicePresentation
    {
        if (! $this->isOwnerChoice()) {
            return null;
        }

        if ($this->hasCachedOwnerChoicePresentation) {
            return $this->cachedOwnerChoicePresentation;
        }

        $presentation = $this->evaluate($this->ownerChoicePresentation);

        if (! $presentation instanceof OwnerImageChoicePresentation) {
            throw new LogicException('Owner choice mode requires an owner image choice presentation.');
        }

        $this->cachedOwnerChoicePresentation = $presentation;
        $this->hasCachedOwnerChoicePresentation = true;

        return $this->cachedOwnerChoicePresentation;
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

        if ($this->isOwnerChoice()) {
            $this->callAfterStateUpdated();
        }

        $this->invalidateOwnerChoicePresentation();
        $this->dispatchOwnerSelectionChanged($ids->first());
        $this->partiallyRender();
    }

    #[ExposedLivewireMethod]
    public function restoreSavedOwnerSelection(array $arguments = []): void
    {
        abort_unless($this->isOwnerChoice(), 404);
        $presentation = $this->getOwnerChoicePresentation();
        $savedMediaId = $presentation?->savedMediaId;

        if ($savedMediaId === null) {
            $savedBrokenIdentity = $this->savedBrokenOwnerIdentity();

            if ($savedBrokenIdentity === null) {
                $this->state(null);
                $this->selectedItems = [];
            } else {
                $trusted = $this->trustedIdentity($savedBrokenIdentity, preserveExisting: true);
                $this->state($trusted);
                $this->hydrateSelectedItems($trusted);
            }
        } else {
            $trusted = $this->trustedIdentity($savedMediaId, preserveExisting: true);
            $this->state($trusted);
            $this->hydrateSelectedItems($trusted);
        }

        $this->callAfterStateUpdated();
        $this->invalidateOwnerChoicePresentation();
        $this->getLivewire()->dispatch(
            'owner-media-selection-restored',
            selectedId: $savedMediaId,
        );
        $this->partiallyRender();
    }

    #[ExposedLivewireMethod]
    public function chooseAutomaticOwnerImage(array $arguments = []): void
    {
        abort_unless($this->isOwnerChoice(), 404);
        abort_unless($this->getOwnerChoicePresentation()?->canChooseAutomatic === true, 422);

        $this->state(null);
        $this->selectedItems = [];
        $this->callAfterStateUpdated();
        $this->invalidateOwnerChoicePresentation();
        $this->dispatchOwnerSelectionChanged(null);
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
            ->hidden(fn (PathCuratorPicker $component): bool => $component->isOwnerChoice())
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
            ->hidden(fn (PathCuratorPicker $component): bool => $component->isDisabled() || $component->isOwnerChoice())
            ->url(function (array $arguments): string {
                $media = $this->trustedActionRecord($arguments, 'update');

                return MediaResource::getUrl('edit', ['record' => $media]);
            }, true);
    }

    public function getRemoveDirectOwnerImageAction(): Action
    {
        return Action::make('removeDirectOwnerImage')
            ->label(__('admin.owner_image.actions.use_automatic_image'))
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->iconButton()
            ->extraAttributes(['data-testid' => 'owner-image-choose-automatic'])
            ->hidden(fn (PathCuratorPicker $component): bool => ! $component->isOwnerChoice())
            ->requiresConfirmation()
            ->modalHeading(__('admin.owner_image.actions.use_automatic_image'))
            ->modalDescription(__('admin.owner_image.automatic_fallback_hint'))
            ->modalSubmitActionLabel(__('admin.media_library.remove'))
            ->action(fn (PathCuratorPicker $component) => $component->chooseAutomaticOwnerImage());
    }

    public function getViewAction(): Action
    {
        return Action::make('view')
            ->label(__('admin.actions.view'))
            ->icon(Heroicon::Eye)
            ->color('gray')
            ->hidden(fn (PathCuratorPicker $component): bool => $component->isOwnerChoice())
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
                        await \$wire.callSchemaComponentMethod('{$componentKey}', 'updateState', { arguments: \$event.detail });
                        await \$wire.unmountAction(false);
                        {$restoreFocus};
                    } finally {
                        returningSelection = false;
                    }
                    JS;

                return [
                    LivewireSchemaComponent::make(
                        MediaPickerPanel::class,
                        fn (): array => $component->pickerComponentData(),
                    )
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

    public function getInlineWorkspaceComponent(): LivewireSchemaComponent
    {
        if (! $this->isInlineOwnerWorkspace()) {
            throw new LogicException('The inline Media workspace requires inline owner mode.');
        }

        return LivewireSchemaComponent::make(
            MediaPickerPanel::class,
            fn (): array => [
                ...$this->pickerComponentData(),
                'isInlineOwnerWorkspace' => true,
            ],
        )
            ->key(fn (): string => "inline-media-picker-workspace-{$this->getKey()}")
            ->columnSpanFull()
            ->extraAttributes(function (): array {
                $componentKey = $this->getKey();
                $insertHandler = <<<JS
                    if (returningSelection) {
                        return;
                    }

                    returningSelection = true;

                    try {
                        await \$wire.callSchemaComponentMethod('{$componentKey}', 'updateState', { arguments: \$event.detail });
                    } finally {
                        returningSelection = false;
                    }
                    JS;

                return [
                    'x-data' => '{ returningSelection: false }',
                    'x-on:insert-media.stop' => $insertHandler,
                ];
            });
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
            ->hidden(fn (PathCuratorPicker $component): bool => $component->isDisabled()
                || $component->isInlineOwnerWorkspace()
                || $component->isOwnerChoice())
            ->action(function (array $arguments, PathCuratorPicker $component): void {
                abort_if($component->isOwnerChoice(), 404);
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
            ->hidden(fn (PathCuratorPicker $component): bool => $component->isDisabled() || $component->isOwnerChoice())
            ->action(function (PathCuratorPicker $component): void {
                abort_if($component->isOwnerChoice(), 404);
                $component->authorizeDetachForState();
                $component->state([]);
            });
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function trustedActionRecord(array $arguments, string $ability): Media
    {
        abort_if($this->isOwnerChoice(), 404);
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

    private function hydrateSavedOwnerIdentity(mixed $state): bool
    {
        if (! $this->isOwnerChoice()) {
            return false;
        }

        $presentation = $this->getOwnerChoicePresentation();

        if (! $presentation instanceof OwnerImageChoicePresentation) {
            return false;
        }

        if ($this->authorizedSavedOwnerReferenceKey($presentation, $state) !== null) {
            $this->state($presentation->savedMediaId);
            $this->selectedItems = [];

            return true;
        }

        if ($presentation->directState === 'broken' && is_string($state)) {
            $savedBrokenIdentity = filled($presentation->savedReferenceKey)
                ? $presentation->savedReferenceKey
                : $presentation->expectedLegacyPath;

            if (is_string($savedBrokenIdentity) && hash_equals($savedBrokenIdentity, $state)) {
                $this->state($savedBrokenIdentity);
                $this->selectedItems = [];

                return true;
            }
        }

        if (
            $presentation->directState === 'absent'
            && $presentation->savedMediaId === null
            && blank($presentation->savedReferenceKey)
            && blank($presentation->expectedLegacyPath)
            && $state === null
        ) {
            $this->state(null);
            $this->selectedItems = [];

            return true;
        }

        return false;
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

        if ($preserveExisting && $this->isSavedBrokenOwnerIdentity($identity)) {
            return $identity;
        }

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
        if ($this->isSavedBrokenOwnerIdentity($identity)) {
            return $identity;
        }

        $savedOwnerIdentity = $this->dehydrateAuthorizedSavedOwnerIdentity($identity);

        if (is_string($savedOwnerIdentity)) {
            return $savedOwnerIdentity;
        }

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

    private function dehydrateAuthorizedSavedOwnerIdentity(mixed $identity): ?string
    {
        if (
            ! $this->isOwnerChoice()
            || (! is_int($identity) && (! is_string($identity) || ! ctype_digit($identity)))
        ) {
            return null;
        }

        $presentation = $this->getOwnerChoicePresentation();

        return $presentation instanceof OwnerImageChoicePresentation
            ? $this->authorizedSavedOwnerReferenceKey($presentation, $identity)
            : null;
    }

    private function authorizedSavedOwnerReferenceKey(
        OwnerImageChoicePresentation $presentation,
        mixed $identity,
    ): ?string {
        if (
            $presentation->directState !== 'present'
            || ! $presentation->savedMediaCanSelect
            || $presentation->savedMediaId === null
            || ! is_string($presentation->savedReferenceKey)
            || preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/D', $presentation->savedReferenceKey) !== 1
            || ! is_array($presentation->directMedia)
            || ($presentation->directMedia['id'] ?? null) !== $presentation->savedMediaId
            || ($presentation->directMedia['reference_key'] ?? null) !== $presentation->savedReferenceKey
        ) {
            return null;
        }

        if (
            (is_int($identity) || (is_string($identity) && ctype_digit($identity)))
            && (int) $identity === $presentation->savedMediaId
        ) {
            return $presentation->savedReferenceKey;
        }

        return is_string($identity) && hash_equals($presentation->savedReferenceKey, $identity)
            ? $presentation->savedReferenceKey
            : null;
    }

    private function isSavedBrokenOwnerIdentity(mixed $identity): bool
    {
        if (! $this->isOwnerChoice() || ! is_string($identity) || blank($identity)) {
            return false;
        }

        $savedIdentity = $this->savedBrokenOwnerIdentity();

        return is_string($savedIdentity) && hash_equals($savedIdentity, $identity);
    }

    private function savedBrokenOwnerIdentity(): ?string
    {
        $presentation = $this->getOwnerChoicePresentation();

        if ($presentation?->directState !== 'broken') {
            return null;
        }

        $savedIdentity = filled($presentation->savedReferenceKey)
            ? $presentation->savedReferenceKey
            : $presentation->expectedLegacyPath;

        return is_string($savedIdentity) && filled($savedIdentity)
            ? $savedIdentity
            : null;
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

    /** @return array<string, mixed> */
    private function pickerComponentData(): array
    {
        $presentation = $this->getOwnerChoicePresentation();

        return [
            'purpose' => $this->getUploadPurpose()->value,
            'selectedIds' => collect($this->identityValues($this->getState()))
                ->filter(fn (mixed $identity): bool => is_int($identity) || (is_string($identity) && ctype_digit($identity)))
                ->map(fn (int|string $identity): int => (int) $identity)
                ->values()
                ->all(),
            'isMultiple' => $this->isMultiple(),
            'maxItems' => $this->getMaxItems(),
            ...($this->isOwnerChoice() ? [
                'isOwnerChoice' => true,
                'savedMediaId' => $presentation?->savedMediaId,
            ] : []),
        ];
    }

    private function invalidateOwnerChoicePresentation(): void
    {
        $this->cachedOwnerChoicePresentation = null;
        $this->hasCachedOwnerChoicePresentation = false;
    }

    private function dispatchOwnerSelectionChanged(?int $selectedId): void
    {
        if (! $this->isOwnerChoice()) {
            return;
        }

        $this->getLivewire()->dispatch(
            'owner-media-selection-changed',
            selectedId: $selectedId,
        );
    }
}
