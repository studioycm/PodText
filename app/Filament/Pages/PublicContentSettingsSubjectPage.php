<?php

namespace App\Filament\Pages;

use App\Enums\UserRole;
use App\Filament\Actions\ExportPublicSettingsAction;
use App\Filament\Support\Concerns\UsesAdminNavigationOrder;
use App\Models\User;
use App\Settings\PublicContentSettings as PublicContentSettingsData;
use App\Support\Media\OwnerImageChoicePresentation;
use App\Support\Media\SettingsOwnerImagePresenter;
use App\Support\Media\SettingsOwnerImageSnapshot;
use App\Support\PublicFront\PublicFrontConfigValidator;
use App\Support\SettingsLifecycle\PublicContentSettingsWriteCoordinator;
use App\Support\SettingsLifecycle\SettingsImportLocks;
use App\Support\SettingsLifecycle\SettingsImportLockSurfaceRegistry;
use App\Support\SettingsLifecycle\SettingsLifecycleSchema;
use App\Support\SettingsLifecycle\SettingsLifecycleSelectionState;
use App\Support\SettingsLifecycle\SettingsLifecycleUnit;
use App\Support\SettingsLifecycle\SettingsSubjectBaseline;
use App\Support\SettingsLifecycle\SettingsSubjectThreeWayMerger;
use App\Support\Transcriptions\MultiTranscriptionSurfaces;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Field;
use Filament\Notifications\Notification;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Component as SchemaComponent;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Concerns\RestrictsFileUploadsToSchemaComponents;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Locked;

abstract class PublicContentSettingsSubjectPage extends SettingsPage
{
    use BuildsPublicContentSettingsSubjectSchemas;
    use RestrictsFileUploadsToSchemaComponents;
    use UsesAdminNavigationOrder;

    protected static string $settings = PublicContentSettingsData::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static bool $shouldRegisterNavigation = false;

    protected ?bool $hasDatabaseTransactions = true;

    /**
     * @var array<int, array{group: string, path: string, selectable: bool}>|null
     */
    private ?array $inlineImportLockRows = null;

    /**
     * @var array<int, string>|null
     */
    private ?array $inlineImportLockedPaths = null;

    /**
     * @var array<string, array<int, string>>
     */
    private array $inlineImportLockUnitPathsBySemanticPath = [];

    #[Locked]
    public string $settingsOwnerImageFingerprint = '';

    /**
     * @var array<string, array{
     *     direct_state: string,
     *     reference_key: string|null,
     *     legacy_path: string|null,
     *     evidence_hash: string|null,
     *     resolved_media_id: int|null,
     *     can_choose_automatic: bool
     * }>
     */
    #[Locked]
    public array $settingsOpenedOwnerImageEvidence = [];

    /**
     * @var array<string, array{exists: bool, hash: string}>
     */
    #[Locked]
    public array $settingsOpenedRawUnitBaseline = [];

    /**
     * @var array<string, array{exists: bool, hash: string}>
     */
    #[Locked]
    public array $settingsOpenedEditableUnitBaseline = [];

    /**
     * Exists only between native beforeFill and afterFill in one request.
     *
     * @var array<string, mixed>|null
     */
    private ?array $openedRawPayloadDuringFill = null;

    /**
     * Exists only between native mutateFormDataBeforeFill and afterFill.
     *
     * @var array<string, mixed>|null
     */
    private ?array $openedFormFacingPayloadDuringFill = null;

    private ?SettingsOwnerImagePresenter $settingsOwnerImagePresenter = null;

    private ?SettingsOwnerImageSnapshot $settingsOwnerImageSnapshot = null;

    private ?PublicContentSettingsWriteCoordinator $settingsWriteCoordinator = null;

    public static function getNavigationLabel(): string
    {
        return __('admin.pages.public_content_settings.navigation');
    }

    public function getTitle(): string
    {
        return __('admin.pages.public_content_settings.title');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('manageImportLocks')
                ->label(__('admin.actions.manage_import_locks'))
                ->icon(Heroicon::OutlinedLockClosed)
                ->color('gray')
                ->url(fn (): string => ManageSettingsImportLocks::getUrl()),
            ExportPublicSettingsAction::make(),
        ];
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->hasRoleAtLeast(UserRole::Admin);
    }

    public function canEdit(): bool
    {
        return static::canAccess();
    }

    protected function settingsSubject(): string
    {
        throw new \LogicException('A public content settings subject page must declare its owner.');
    }

    protected function beforeFill(): void
    {
        $raw = $this->freshRawSettingsPayload();

        $this->openedRawPayloadDuringFill = $raw;
        $this->settingsOpenedRawUnitBaseline = $this->settingsSubjectBaseline()->capture($raw);
        $this->captureSettingsOwnerImageSnapshot($raw);
    }

    protected function afterFill(): void
    {
        $openedRaw = $this->openedRawPayloadDuringFill ?? $this->freshRawSettingsPayload();
        $openedEditable = $this->prepareUnvalidatedLocalCandidate(
            $this->openedFormFacingPayloadDuringFill ?? [],
            $openedRaw,
        );
        $openedEditable = $this->normalizedValidatorCandidate($openedEditable);

        $this->settingsOpenedEditableUnitBaseline = $this->settingsSubjectBaseline()
            ->capture($openedEditable);
        $this->openedRawPayloadDuringFill = null;
        $this->openedFormFacingPayloadDuringFill = null;
    }

    public function save(): void
    {
        abort_unless($this->canEdit(), 403);

        $this->settingsWriteCoordinator()
            ->withinLock(fn (): mixed => parent::save());
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $freshRaw = $this->freshRawSettingsPayload();
        $localCandidate = $this->prepareUnvalidatedLocalCandidate($data, $freshRaw);
        $merger = app(SettingsSubjectThreeWayMerger::class);
        $validator = app(PublicFrontConfigValidator::class);
        $validatorGroups = SettingsSubjectOwnershipRegistry::validatorGroups($this->settingsSubject());
        $localValidation = $validator->validateGroups($localCandidate, $validatorGroups);
        $freshValidation = $validator->validateGroups($freshRaw, $validatorGroups);
        $normalizedLocalCandidate = $this->overlayNormalizedValidatorGroups(
            $localCandidate,
            $localValidation->config(),
            $validatorGroups,
        );
        $validationPayload = $merger->validationPayload(
            $this->settingsOpenedEditableUnitBaseline,
            $localCandidate,
            $freshRaw,
            $normalizedLocalCandidate,
            $localValidation->invalidConfig(),
            $freshValidation->invalidConfig(),
        );
        $validation = $validator->validateGroups($validationPayload, $validatorGroups);
        $normalizedCandidate = $this->overlayNormalizedValidatorGroups(
            $validationPayload,
            $validation->config(),
            $validatorGroups,
        );

        $merge = $merger->merge(
            $this->settingsOpenedRawUnitBaseline,
            $this->settingsOpenedEditableUnitBaseline,
            $localCandidate,
            $freshRaw,
            $normalizedCandidate,
            $validation->invalidConfig(),
            $freshValidation->invalidConfig(),
            $validationPayload,
            $normalizedLocalCandidate,
        );

        if ($merge['invalid_path'] !== null) {
            $unitPath = $merge['invalid_unit_path'] ?? $merge['invalid_path'];
            $message = __('admin.validation.settings_unit_invalid', [
                'unit' => app(SettingsLifecycleSchema::class)->labelFor($unitPath),
            ]);

            $this->addError("data.{$unitPath}", $message);

            Notification::make()
                ->danger()
                ->title($message)
                ->send();

            throw new Halt;
        }

        if ($merge['conflict_path'] !== null) {
            Notification::make()
                ->warning()
                ->title(__('admin.validation.settings_unit_changed', [
                    'unit' => app(SettingsLifecycleSchema::class)->labelFor($merge['conflict_path']),
                ]))
                ->send();

            throw new Halt;
        }

        return $merge['payload'];
    }

    protected function afterSave(): void
    {
        $this->fillForm();

        $this->settingsWriteCoordinator()->refreshLeaseOrFail();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function rememberOpenedFormFacingPayload(array $data): array
    {
        $this->openedFormFacingPayloadDuringFill = $data;

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $stored
     * @return array<string, mixed>
     */
    private function prepareUnvalidatedLocalCandidate(array $data, array $stored): array
    {
        $owned = SettingsSubjectOwnershipRegistry::extractOwned($data, $this->settingsSubject());
        $owned = $this->normalizeOwnedFormData($owned, $stored);
        $candidate = SettingsSubjectOwnershipRegistry::overlayOwned(
            $stored,
            $owned,
            $this->settingsSubject(),
        );
        $candidate = MultiTranscriptionSurfaces::overlayUnauthorizedSettings(
            $candidate,
            PublicContentSettingsData::class,
            storedSnapshot: $stored,
        );

        return $candidate;
    }

    /**
     * @param  array<string, mixed>  $candidate
     * @return array<string, mixed>
     */
    private function normalizedValidatorCandidate(array $candidate): array
    {
        $groups = SettingsSubjectOwnershipRegistry::validatorGroups($this->settingsSubject());
        $config = app(PublicFrontConfigValidator::class)
            ->validateGroups($candidate, $groups)
            ->config();

        return $this->overlayNormalizedValidatorGroups($candidate, $config, $groups);
    }

    /**
     * @param  array<string, mixed>  $candidate
     * @param  array<string, mixed>  $normalized
     * @param  array<int, string>  $groups
     * @return array<string, mixed>
     */
    private function overlayNormalizedValidatorGroups(
        array $candidate,
        array $normalized,
        array $groups,
    ): array {
        foreach ($groups as $group) {
            if (array_key_exists($group, $normalized)) {
                $candidate[$group] = $normalized[$group];
            }
        }

        return $candidate;
    }

    /**
     * @return array<string, mixed>
     */
    private function freshRawSettingsPayload(): array
    {
        $settings = app(static::getSettings());

        return $settings->getRepository()->getPropertiesInGroup($settings::group());
    }

    private function settingsSubjectBaseline(): SettingsSubjectBaseline
    {
        return app(SettingsSubjectBaseline::class);
    }

    private function settingsWriteCoordinator(): PublicContentSettingsWriteCoordinator
    {
        return $this->settingsWriteCoordinator ??= app(PublicContentSettingsWriteCoordinator::class);
    }

    public function form(Schema $schema): Schema
    {
        $components = $this->subjectSchema()
            ->container($schema)
            ->getChildComponents();

        $schema = $schema->components($components);

        $this->applyInlineImportLockHints($schema->getComponents());

        return $schema;
    }

    protected function subjectSchema(): Tab
    {
        throw new \LogicException('A focused public content settings page must declare its schema.');
    }

    protected function settingsOwnerImageChoice(
        string $slotKey,
        int|string|null $pendingIdentity,
    ): OwnerImageChoicePresentation {
        $presenter = $this->settingsOwnerImagePresenter();
        $presenter->primeOpenedEvidence($this->settingsOpenedOwnerImageEvidence);

        return $presenter->choice(
            $this->currentSettingsOwnerImageSnapshot(),
            $slotKey,
            $pendingIdentity,
            [
                'commit' => __('admin.actions.save'),
                'cancel' => __('admin.actions.cancel'),
                'admission' => __('admin.media_library.acquisition_permanence_inline'),
            ],
            $this->openedSettingsOwnerImageEvidence($slotKey),
        );
    }

    /**
     * @return array{
     *     direct_state?: string,
     *     reference_key?: string|null,
     *     legacy_path?: string|null,
     *     evidence_hash?: string|null,
     *     resolved_media_id?: int|null,
     *     can_choose_automatic?: bool
     * }|null
     */
    protected function openedSettingsOwnerImageEvidence(string $slotKey): ?array
    {
        $evidence = $this->settingsOpenedOwnerImageEvidence[$slotKey] ?? null;

        return is_array($evidence) ? $evidence : null;
    }

    private function currentSettingsOwnerImageSnapshot(): SettingsOwnerImageSnapshot
    {
        if ($this->settingsOwnerImageSnapshot instanceof SettingsOwnerImageSnapshot) {
            return $this->settingsOwnerImageSnapshot;
        }

        $settings = app(static::getSettings());
        $settings->refresh();

        return $this->settingsOwnerImageSnapshot = $this->settingsOwnerImagePresenter()->snapshot(
            $settings->toArray(),
            $this->settingsSubject(),
        );
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function captureSettingsOwnerImageSnapshot(array $settings): SettingsOwnerImageSnapshot
    {
        $snapshot = $this->settingsOwnerImagePresenter()->snapshot(
            $settings,
            $this->settingsSubject(),
        );
        $this->settingsOwnerImageSnapshot = $snapshot;
        $this->settingsOwnerImageFingerprint = $snapshot->fingerprint;
        $this->settingsOpenedOwnerImageEvidence = $this->settingsOwnerImagePresenter()
            ->openedEvidence($snapshot);

        return $snapshot;
    }

    private function settingsOwnerImagePresenter(): SettingsOwnerImagePresenter
    {
        return $this->settingsOwnerImagePresenter ??= app(SettingsOwnerImagePresenter::class);
    }

    protected function withImportLockSection(Section $section, string $group, string $key): Section
    {
        return $section
            ->key("public-settings-lock-section-{$key}")
            ->headerActions([
                $this->inlineImportLockGroupAction($group, $key),
            ]);
    }

    /**
     * @param  array<int, mixed>  $components
     */
    protected function applyInlineImportLockHints(array $components): void
    {
        foreach ($components as $component) {
            if ($component instanceof Field) {
                $this->applyInlineImportLockHint($component);
            }

            if (! $component instanceof SchemaComponent || ! method_exists($component, 'getChildComponents')) {
                continue;
            }

            $this->applyInlineImportLockHints($component->getChildComponents());
        }
    }

    private function applyInlineImportLockHint(Field $field): void
    {
        $statePath = $field->getStatePath(isAbsolute: false);

        if (blank($statePath)) {
            return;
        }

        $unitPaths = $this->inlineImportLockUnitPathsForSemanticPath($statePath);

        if (count($unitPaths) !== 1) {
            return;
        }

        $field->hintAction($this->inlineImportLockUnitAction($unitPaths[0]));
    }

    private function inlineImportLockGroupAction(string $group, string $key): Action
    {
        return Action::make('toggleImportLockGroup_'.$this->inlineImportLockActionKey($key))
            ->label(fn (): string => __('admin.settings_import_locks.inline_group_state.'.$this->inlineImportLockGroupState($group)))
            ->icon(fn (): Heroicon => $this->inlineImportLockGroupIcon($group))
            ->color(fn (): string => $this->inlineImportLockGroupColor($group))
            ->tooltip(fn (): string => __('admin.settings_import_locks.inline_group_tooltip.'.$this->inlineImportLockGroupState($group), $this->inlineImportLockGroupCounts($group)))
            ->action(function () use ($group): void {
                $this->toggleInlineImportLockGroup($group);
            });
    }

    private function inlineImportLockUnitAction(string $unitPath): Action
    {
        return Action::make('toggleImportLockUnit_'.$this->inlineImportLockActionKey($unitPath))
            ->label(fn (): string => $this->inlineImportLockUnitLocked($unitPath)
                ? __('admin.settings_import_locks.inline_field_locked')
                : __('admin.settings_import_locks.inline_field_unlocked'))
            ->icon(fn (): Heroicon => $this->inlineImportLockUnitLocked($unitPath) ? Heroicon::OutlinedLockClosed : Heroicon::OutlinedLockOpen)
            ->color(fn (): string => $this->inlineImportLockUnitLocked($unitPath) ? 'warning' : 'gray')
            ->tooltip(fn (): string => __('admin.settings_import_locks.inline_field_tooltip.'.($this->inlineImportLockUnitLocked($unitPath) ? 'locked' : 'unlocked'), [
                'unit' => $this->inlineImportLockUnit($unitPath)?->label ?? $unitPath,
                'path' => $unitPath,
            ]))
            ->iconButton()
            ->action(function () use ($unitPath): void {
                $this->toggleInlineImportLockUnit($unitPath);
            });
    }

    private function toggleInlineImportLockGroup(string $group): void
    {
        $this->inlineImportLockedPaths = app(SettingsImportLocks::class)->save(
            app(SettingsLifecycleSelectionState::class)->toggleGroup(
                $this->inlineImportLockRows(),
                $this->inlineImportLockedPaths(),
                $group,
            ),
        );
    }

    private function toggleInlineImportLockUnit(string $unitPath): void
    {
        $this->inlineImportLockedPaths = app(SettingsImportLocks::class)->save(
            app(SettingsLifecycleSelectionState::class)->togglePath(
                $this->inlineImportLockRows(),
                $this->inlineImportLockedPaths(),
                $unitPath,
            ),
        );
    }

    private function inlineImportLockGroupState(string $group): string
    {
        return app(SettingsLifecycleSelectionState::class)
            ->groupState($this->inlineImportLockRows(), $this->inlineImportLockedPaths(), $group);
    }

    private function inlineImportLockGroupIcon(string $group): Heroicon
    {
        return match ($this->inlineImportLockGroupState($group)) {
            'all' => Heroicon::OutlinedLockClosed,
            'some' => Heroicon::OutlinedShieldExclamation,
            default => Heroicon::OutlinedLockOpen,
        };
    }

    private function inlineImportLockGroupColor(string $group): string
    {
        return match ($this->inlineImportLockGroupState($group)) {
            'all' => 'warning',
            'some' => 'info',
            default => 'gray',
        };
    }

    /**
     * @return array{locked: int, total: int}
     */
    private function inlineImportLockGroupCounts(string $group): array
    {
        $paths = collect($this->inlineImportLockRows())
            ->filter(fn (array $row): bool => ($row['group'] ?? null) === $group && (bool) ($row['selectable'] ?? false))
            ->pluck('path')
            ->values()
            ->all();

        return [
            'locked' => count(array_intersect($paths, $this->inlineImportLockedPaths())),
            'total' => count($paths),
        ];
    }

    private function inlineImportLockUnitLocked(string $unitPath): bool
    {
        return in_array($unitPath, $this->inlineImportLockedPaths(), true);
    }

    private function inlineImportLockUnit(string $unitPath): ?SettingsLifecycleUnit
    {
        return app(SettingsLifecycleSchema::class)->unitFor($unitPath);
    }

    /**
     * @return array<int, array{group: string, path: string, selectable: bool}>
     */
    private function inlineImportLockRows(): array
    {
        if ($this->inlineImportLockRows !== null) {
            return $this->inlineImportLockRows;
        }

        $registry = app(SettingsImportLockSurfaceRegistry::class);

        return $this->inlineImportLockRows = collect($registry->surfaces())
            ->where('type', 'section')
            ->flatMap(fn (array $surface): array => collect($surface['unit_paths'])
                ->map(fn (string $path): array => [
                    'group' => $surface['group'],
                    'path' => $path,
                    'selectable' => true,
                ])
                ->all())
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function inlineImportLockedPaths(): array
    {
        return $this->inlineImportLockedPaths ??= app(SettingsImportLocks::class)->lockedPaths();
    }

    /**
     * @return array<int, string>
     */
    private function inlineImportLockUnitPathsForSemanticPath(string $semanticPath): array
    {
        if (array_key_exists($semanticPath, $this->inlineImportLockUnitPathsBySemanticPath)) {
            return $this->inlineImportLockUnitPathsBySemanticPath[$semanticPath];
        }

        $unitPath = app(SettingsImportLockSurfaceRegistry::class)->importantFieldUnitPath($semanticPath);

        return $this->inlineImportLockUnitPathsBySemanticPath[$semanticPath] = $unitPath ? [$unitPath] : [];
    }

    private function inlineImportLockActionKey(string $path): string
    {
        return trim((string) preg_replace('/[^A-Za-z0-9]+/', '_', $path), '_');
    }
}
