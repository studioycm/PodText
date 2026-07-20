<?php

namespace App\Filament\Forms\Components;

use App\Enums\ImageUploadPurpose;
use App\Filament\Resources\Media\MediaResource;
use App\Models\Media;
use App\Models\User;
use App\Support\Media\MediaIdentityResolver;
use App\Support\Media\MediaRecordProjector;
use App\Support\Media\MediaRecordScope;
use Filament\Actions\Action;
use Filament\Forms\Components\Field;
use Filament\Support\Components\Attributes\ExposedLivewireMethod;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
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
            $trusted = $component->trustedIdentity($state);
            $component->state($trusted);
            $component->hydrateSelectedItems($trusted);
        });

        $this->clearAfterStateUpdatedHooks();
        $this->afterStateUpdated(function (PathCuratorPicker $component, mixed $state): void {
            $trusted = $component->trustedIdentity($state);
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
            ->query($this->getUploadPurpose())
            ->whereKey($ids->all())
            ->get()
            ->keyBy(fn (Media $record): int => (int) $record->getKey());
        abort_if($media->count() !== $ids->count(), 404);

        $ids->each(function (int $id) use ($actor, $media): void {
            /** @var Media $record */
            $record = $media->get($id);
            Gate::forUser($actor)->authorize('select', $record);
            Gate::forUser($actor)->authorize('attach', $record);
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
            ->modalSubmitAction(false)
            ->modalCancelAction(false)
            ->modalWidth(Width::Screen)
            ->modalCloseButton(false)
            ->modalContent(fn (PathCuratorPicker $component): View => view('filament.forms.components.media-picker-modal', [
                'componentKey' => $component->getKey(),
                'purpose' => $component->getUploadPurpose()->value,
                'selectedIds' => collect($component->identityValues($component->getState()))
                    ->filter(fn (mixed $identity): bool => is_int($identity) || (is_string($identity) && ctype_digit($identity)))
                    ->map(fn (int|string $identity): int => (int) $identity)
                    ->values()
                    ->all(),
                'isMultiple' => $component->isMultiple(),
                'maxItems' => $component->getMaxItems(),
            ]))
            ->action(fn (): null => null);
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
        $media = app(MediaRecordScope::class)->findOrFail(
            is_int($id) || is_string($id) ? $id : '',
            $this->getUploadPurpose(),
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
        return $this->selectedItems;
    }

    private function trustedIdentity(mixed $state): array|int|string|null
    {
        $values = $this->identityValues($state);
        $numericIds = collect($values)
            ->filter(fn (mixed $identity): bool => is_int($identity) || (is_string($identity) && ctype_digit($identity)))
            ->map(fn (int|string $identity): int => (int) $identity)
            ->unique()
            ->values();

        if ($numericIds->count() === count($values) && $numericIds->isNotEmpty()) {
            $records = app(MediaRecordScope::class)
                ->query($this->getUploadPurpose())
                ->whereKey($numericIds->all())
                ->get()
                ->keyBy(fn (Media $media): int => (int) $media->getKey());
            abort_if($records->count() !== $numericIds->count(), 404);
            $actor = auth()->user();
            abort_unless($actor instanceof User, 403);
            $numericIds->each(function (int $id) use ($records, $actor): void {
                Gate::forUser($actor)->authorize('select', $records->get($id));
            });

            return $this->isMultiple() ? $numericIds->all() : $numericIds->first();
        }

        $identities = collect($values)
            ->map(fn (mixed $identity): int|string|null => $this->trustedSingleIdentity($identity))
            ->filter(fn (mixed $identity): bool => $identity !== null)
            ->unique()
            ->values()
            ->all();

        return $this->isMultiple() ? $identities : ($identities[0] ?? null);
    }

    private function trustedSingleIdentity(mixed $identity): int|string|null
    {
        $identity = is_array($identity)
            ? ($identity['id'] ?? $identity['reference_key'] ?? null)
            : $identity;
        $media = null;

        if (is_int($identity) || (is_string($identity) && ctype_digit($identity))) {
            $media = app(MediaRecordScope::class)->findOrFail($identity, $this->getUploadPurpose());
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
            Gate::forUser($actor)->authorize('select', $media);

            return (int) $media->getKey();
        }

        return null;
    }

    private function dehydrateIdentity(mixed $identity): ?string
    {
        if (is_int($identity) || (is_string($identity) && ctype_digit($identity))) {
            $media = app(MediaRecordScope::class)->findOrFail($identity, $this->getUploadPurpose());
            $actor = auth()->user();
            abort_unless($actor instanceof User, 403);
            Gate::forUser($actor)->authorize('select', $media);

            return $this->dehydratesReferenceKey ? $media->reference_key : $media->path;
        }

        if ($this->dehydratesReferenceKey && filled($identity)) {
            abort(422);
        }

        return is_string($identity) && filled($identity) ? $identity : null;
    }

    private function authorizeDetachForState(): void
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);
        Gate::forUser($actor)->authorize('viewAny', config('curator.model', Media::class));

        collect($this->identityValues($this->getState()))
            ->filter(fn (mixed $identity): bool => is_int($identity) || (is_string($identity) && ctype_digit($identity)))
            ->each(function (int|string $identity) use ($actor): void {
                $media = app(MediaRecordScope::class)->findOrFail($identity, $this->getUploadPurpose());
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
                ->query($this->getUploadPurpose())
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
