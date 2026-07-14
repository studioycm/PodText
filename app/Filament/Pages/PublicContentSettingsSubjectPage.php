<?php

namespace App\Filament\Pages;

use App\Enums\UserRole;
use App\Filament\Actions\ExportPublicSettingsAction;
use App\Filament\Support\Concerns\UsesAdminNavigationOrder;
use App\Models\User;
use App\Settings\PublicContentSettings as PublicContentSettingsData;
use App\Support\PublicFront\PublicFrontConfigValidator;
use App\Support\Settings\SettingsPageProfiler;
use App\Support\Settings\SettingsSp3aMeasurementFixture;
use App\Support\Settings\SettingsSp3bSubjectFixture;
use App\Support\SettingsLifecycle\SettingsImportLocks;
use App\Support\SettingsLifecycle\SettingsImportLockSurfaceRegistry;
use App\Support\SettingsLifecycle\SettingsLifecycleSchema;
use App\Support\SettingsLifecycle\SettingsLifecycleSelectionState;
use App\Support\SettingsLifecycle\SettingsLifecycleUnit;
use App\Support\Transcriptions\MultiTranscriptionSurfaces;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Field;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Component as SchemaComponent;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Facades\FilamentView;
use Filament\Support\Icons\Heroicon;
use Throwable;

abstract class PublicContentSettingsSubjectPage extends SettingsPage
{
    use BuildsPublicContentSettingsSubjectSchemas;
    use UsesAdminNavigationOrder;

    protected static string $settings = PublicContentSettingsData::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static bool $shouldRegisterNavigation = false;

    public bool $sp3aMeasurementMode = false;

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

    public function mount(): void
    {
        parent::mount();
    }

    protected function settingsSubject(): string
    {
        throw new \LogicException('A public content settings subject page must declare its owner.');
    }

    protected function fillForm(): void
    {
        $this->sp3aMeasurementMode = app()->environment('local') && request()->boolean('sp3a_measure');

        $this->settingsProfiler()->withRequestKind(SettingsPageProfiler::REQUEST_INITIAL_LOAD, function (): void {
            $this->callHook('beforeFill');

            $data = $this->settingsProfiler()->measure(
                'settings.read_hydrate',
                function (): array {
                    $settings = app(static::getSettings());

                    return $this->mutateFormDataBeforeFill($settings->toArray());
                },
                SettingsPageProfiler::REQUEST_INITIAL_LOAD,
            );

            if ($this->sp3aMeasurementMode) {
                $data = SettingsSubjectOwnershipRegistry::overlayOwned(
                    $data,
                    app(SettingsSp3aMeasurementFixture::class)->payload(),
                    $this->settingsSubject(),
                );
                $data = SettingsSubjectOwnershipRegistry::overlayOwned(
                    $data,
                    app(SettingsSp3bSubjectFixture::class)->payload(
                        $this->settingsSubject(),
                        request()->query('sp3b_subject_fixture'),
                    ),
                    $this->settingsSubject(),
                );
            }

            $this->form->fill($data);

            $this->callHook('afterFill');

            $this->recordPayloadSnapshot('payload.load', SettingsPageProfiler::REQUEST_INITIAL_LOAD);
        });
    }

    public function save(): void
    {
        abort_unless($this->canEdit(), 403);

        if ($this->sp3aMeasurementMode) {
            return;
        }

        $this->settingsProfiler()->withRequestKind(SettingsPageProfiler::REQUEST_SAVE, function (): void {
            $this->settingsProfiler()->measure('save.total', function (): void {
                try {
                    $this->beginDatabaseTransaction();

                    $this->callHook('beforeValidate');

                    $data = $this->settingsProfiler()->measure(
                        'save.validation.total',
                        fn (): array => $this->form->getState(),
                        SettingsPageProfiler::REQUEST_SAVE,
                        $this->currentPayloadBytes(),
                    );

                    $this->callHook('afterValidate');

                    $data = $this->settingsProfiler()->measure(
                        'save.mutate_normalize',
                        function () use ($data): array {
                            $settings = app(static::getSettings());
                            $settings->refresh();
                            $stored = $settings->toArray();
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
                            $validated = app(PublicFrontConfigValidator::class)
                                ->validateGroups(
                                    $candidate,
                                    SettingsSubjectOwnershipRegistry::validatorGroups($this->settingsSubject()),
                                )
                                ->config();
                            $owned = SettingsSubjectOwnershipRegistry::overlayOwned(
                                $owned,
                                $validated,
                                $this->settingsSubject(),
                            );

                            return [
                                'settings' => $settings,
                                'data' => MultiTranscriptionSurfaces::overlayUnauthorizedSettings(
                                    SettingsSubjectOwnershipRegistry::overlayOwned(
                                        $stored,
                                        $owned,
                                        $this->settingsSubject(),
                                    ),
                                    PublicContentSettingsData::class,
                                    storedSnapshot: $stored,
                                ),
                            ];
                        },
                        SettingsPageProfiler::REQUEST_SAVE,
                        $this->settingsProfiler()->payloadBytes($data),
                    );

                    $this->callHook('beforeSave');

                    $this->settingsProfiler()->measure(
                        'save.settings_persist',
                        function () use ($data): void {
                            $data['settings']->fill($data['data']);
                            $data['settings']->save();
                        },
                        SettingsPageProfiler::REQUEST_SAVE,
                        $this->settingsProfiler()->payloadBytes($data),
                    );

                    $this->callHook('afterSave');
                } catch (Halt $exception) {
                    $exception->shouldRollbackDatabaseTransaction() ?
                        $this->rollBackDatabaseTransaction() :
                        $this->commitDatabaseTransaction();

                    return;
                } catch (Throwable $exception) {
                    $this->rollBackDatabaseTransaction();

                    throw $exception;
                }

                $this->commitDatabaseTransaction();

                $this->rememberData();

                $this->getSavedNotification()?->send();

                if ($redirectUrl = $this->getRedirectUrl()) {
                    $this->redirect($redirectUrl, navigate: FilamentView::hasSpaMode($redirectUrl));
                }
            }, SettingsPageProfiler::REQUEST_SAVE, $this->currentPayloadBytes());
        });
    }

    public function updatedInteractsWithSchemas(string $statePath): void
    {
        $this->settingsProfiler()->withRequestKind(SettingsPageProfiler::REQUEST_LIVEWIRE_UPDATE, function () use ($statePath): void {
            $this->settingsProfiler()->measure(
                'livewire_update.total',
                fn (): mixed => parent::updatedInteractsWithSchemas($statePath),
                SettingsPageProfiler::REQUEST_LIVEWIRE_UPDATE,
                $this->currentPayloadBytes(),
            );

            $this->recordPayloadSnapshot('payload.livewire_update', SettingsPageProfiler::REQUEST_LIVEWIRE_UPDATE);
        });
    }

    public function form(Schema $schema): Schema
    {
        $formBuildTimer = $this->settingsProfiler()->start('form.total_build', SettingsPageProfiler::REQUEST_INITIAL_LOAD);

        try {
            $components = $this->subjectSchema()
                ->container($schema)
                ->getChildComponents();

            $schema = $schema->components($components);

            $this->settingsProfiler()->measure(
                'form.inline_import_lock_hints',
                fn (): mixed => $this->applyInlineImportLockHints($schema->getComponents()),
                SettingsPageProfiler::REQUEST_INITIAL_LOAD,
            );

            return $schema;
        } finally {
            $this->settingsProfiler()->stop($formBuildTimer);
        }
    }

    protected function subjectSchema(): Tab
    {
        throw new \LogicException('A focused public content settings page must declare its schema.');
    }

    protected function withImportLockSection(Section $section, string $group, string $key): Section
    {
        $sectionBuildTimer = $this->settingsProfiler()->start(
            "schema.section.{$key}",
            SettingsPageProfiler::REQUEST_INITIAL_LOAD,
        );

        try {
            return $section
                ->key("public-settings-lock-section-{$key}")
                ->headerActions([
                    $this->inlineImportLockGroupAction($group, $key),
                ]);
        } finally {
            $this->settingsProfiler()->stop($sectionBuildTimer);
        }
    }

    /**
     * @template TValue
     *
     * @param  Closure(): TValue  $callback
     * @return TValue
     */
    private function profileSchemaBuild(string $phase, Closure $callback): mixed
    {
        return $this->settingsProfiler()->measure(
            "schema.{$phase}",
            $callback,
            SettingsPageProfiler::REQUEST_INITIAL_LOAD,
        );
    }

    private function settingsProfiler(): SettingsPageProfiler
    {
        return app(SettingsPageProfiler::class);
    }

    private function recordPayloadSnapshot(string $phase, string $requestKind): void
    {
        $profiler = $this->settingsProfiler();

        if (! $profiler->isEnabled()) {
            return;
        }

        $profiler->record(
            phase: $phase,
            milliseconds: 0.0,
            requestKind: $requestKind,
            payloadBytes: $this->currentPayloadBytes(),
        );
    }

    private function currentPayloadBytes(): int
    {
        $profiler = $this->settingsProfiler();

        if (! $profiler->isEnabled()) {
            return 0;
        }

        try {
            return $profiler->payloadBytes($this->form->getStateSnapshot());
        } catch (Throwable) {
            return 0;
        }
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
