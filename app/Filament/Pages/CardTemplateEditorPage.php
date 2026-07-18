<?php

namespace App\Filament\Pages;

use App\Enums\UserRole;
use App\Models\User;
use App\Settings\PublicContentSettings;
use App\Support\PublicFront\Cards\PublicFrontCardTemplateRegistry;
use App\Support\PublicFront\PublicFrontConfigRegistry;
use App\Support\Settings\CardTemplates\CardTemplateAccessPolicy;
use App\Support\Settings\CardTemplates\CardTemplateIdentity;
use App\Support\Settings\CardTemplates\CardTemplatePreviewer;
use App\Support\Settings\CardTemplates\CardTemplateWriteException;
use App\Support\Settings\CardTemplates\CardTemplateWriteResult;
use App\Support\Settings\SettingsPageProfiler;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\View as SchemaView;
use Filament\Schemas\Schema;
use Filament\Support\Enums\SlideOverPosition;
use Filament\Support\Enums\Width;
use Filament\Support\Facades\FilamentView;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Throwable;

abstract class CardTemplateEditorPage extends SettingsPage
{
    use BuildsPublicContentSettingsSubjectSchemas;

    public const BUILDER_DISPLAY_INLINE = 'inline';

    public const BUILDER_DISPLAY_SLIDE_OVER = 'slide_over';

    protected static string $settings = PublicContentSettings::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected string $view = 'filament.pages.card-template-editor';

    #[Locked]
    public string $operationMode = 'blank';

    #[Locked]
    public ?string $originalFamily = null;

    #[Locked]
    public ?string $originalKey = null;

    #[Locked]
    public ?string $targetFingerprint = null;

    #[Locked]
    public ?string $sourceFamily = null;

    #[Locked]
    public ?string $sourceKey = null;

    #[Locked]
    public ?string $sourceFingerprint = null;

    #[Locked]
    public bool $sp3aMeasurementMode = false;

    #[Locked]
    public bool $profilingMode = false;

    #[Locked]
    public ?string $measurementFixtureIdentity = null;

    #[Locked]
    public bool $capable = false;

    #[Locked]
    public bool $restricted = false;

    #[Locked]
    public bool $templateProtectedAtMount = false;

    #[Locked]
    public bool $protectedForgeryDetected = false;

    #[Locked]
    public bool $defaultIdentity = false;

    #[Locked]
    public bool $familyImportLocked = false;

    #[Locked]
    public string $previewStatus = 'idle';

    #[Locked]
    public ?string $previewFamily = null;

    #[Locked]
    public ?int $previewSampleId = null;

    #[Locked]
    public ?string $previewSampleLabel = null;

    #[Locked]
    public ?string $previewHtml = null;

    #[Locked]
    public ?string $previewRefreshedAt = null;

    #[Locked]
    public ?string $previewDraftHash = null;

    /** @var array{sample_id: int|null} */
    public array $previewControls = ['sample_id' => null];

    public string $builderDisplayMode = self::BUILDER_DISPLAY_SLIDE_OVER;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->hasRoleAtLeast(UserRole::Admin);
    }

    public function canEdit(): bool
    {
        return static::canAccess();
    }

    protected function hasUnsavedDataChangesAlert(): bool
    {
        return true;
    }

    public function hydrate(): void
    {
        $this->normalizeBuilderDisplayMode();
        $this->restoreProfilingConfiguration();
        $this->enforceCurrentCapability();
    }

    public function updatedInteractsWithSchemas(string $statePath): void
    {
        parent::updatedInteractsWithSchemas($statePath);
        $this->enforceCurrentCapability();

        if ($statePath === 'data.family') {
            $this->previewSampleId = null;
            $this->previewControls['sample_id'] = null;
            $this->refreshPreview();

            return;
        }

        if (in_array($statePath, [
            'data.layout',
            'data.density',
            'data.image_size',
            'data.title_size',
        ], true)) {
            $this->refreshPreview();

            return;
        }

        if ($statePath === 'data.parts' || str_starts_with($statePath, 'data.parts.')) {
            $this->refreshPreview();
        }
    }

    public function form(Schema $schema): Schema
    {
        $identityLocked = $this->defaultIdentity || $this->operationMode === 'override';
        $fields = [
            TextInput::make('key')
                ->label(__('admin.fields.card_template_key'))
                ->helperText(__('admin.helpers.card_template_key'))
                ->extraInputAttributes(['dir' => 'ltr'])
                ->required()
                ->maxLength(CardTemplateIdentity::KEY_MAX_LENGTH)
                ->rules(['regex:'.CardTemplateIdentity::KEY_PATTERN])
                ->disabled($identityLocked)
                ->dehydrated(),
            TextInput::make('label')
                ->label(__('admin.fields.card_template_label'))
                ->helperText(__('admin.helpers.card_template_label'))
                ->required()
                ->maxLength(CardTemplateIdentity::LABEL_MAX_LENGTH),
            Select::make('family')
                ->label(__('admin.fields.card_template_family'))
                ->helperText(__('admin.helpers.card_template_family'))
                ->options(PublicFrontConfigRegistry::cardFamilyOptions())
                ->native(false)
                ->live()
                ->required()
                ->disabled($identityLocked)
                ->dehydrated(),
            Select::make('layout')
                ->label(__('admin.fields.card_template_layout'))
                ->helperText(__('admin.helpers.card_template_layout'))
                ->options([
                    'cards' => __('admin.layouts.cards'),
                    'rows' => __('admin.layouts.rows'),
                ])
                ->native(false)
                ->live()
                ->required(),
            Select::make('density')
                ->label(__('admin.fields.card_template_density'))
                ->helperText(__('admin.helpers.card_template_density'))
                ->options([
                    'compact' => __('admin.card_density.compact'),
                    'comfortable' => __('admin.card_density.comfortable'),
                ])
                ->native(false)
                ->live()
                ->required(),
            Select::make('image_size')
                ->label(__('admin.fields.card_template_image_size'))
                ->helperText(__('admin.helpers.card_template_image_size'))
                ->options([
                    'hidden' => __('admin.card_image_size.hidden'),
                    'small' => __('admin.card_image_size.small'),
                    'medium' => __('admin.card_image_size.medium'),
                    'large' => __('admin.card_image_size.large'),
                ])
                ->native(false)
                ->live()
                ->required(),
            Select::make('title_size')
                ->label(__('admin.fields.card_template_title_size'))
                ->helperText(__('admin.helpers.card_template_title_size'))
                ->options([
                    'sm' => __('admin.card_title_size.sm'),
                    'base' => __('admin.card_title_size.base'),
                    'lg' => __('admin.card_title_size.lg'),
                ])
                ->native(false)
                ->live()
                ->required(),
        ];

        if ($this->restricted) {
            $fields[] = Text::make(__('admin.settings_sp3c.editor.restricted_copy'))
                ->extraAttributes(['data-sp3c-restricted-shell' => 'true']);
        } else {
            $fields[] = SchemaView::make('filament.card-templates.builder-display-mode')
                ->viewData(fn (): array => [
                    'builderDisplayMode' => $this->builderDisplayMode,
                ])
                ->columnSpanFull();
            $fields[] = Builder::make('parts')
                ->label(__('admin.fields.card_template_parts'))
                ->helperText(__('admin.helpers.card_template_parts'))
                ->blocks($this->cardTemplatePartBlocks(previews: true))
                ->blockPickerColumns(2)
                ->blockPreviews(fn (): bool => $this->cardTemplatePartPreviewsEnabled(true))
                ->editAction(fn (Action $action): Action => $this->configureCardTemplatePartEditAction($action))
                ->cloneable()
                ->reorderable()
                ->deletable()
                ->live(debounce: 500)
                ->afterStateUpdated(function (): void {
                    $this->refreshPreview();
                })
                ->default([])
                ->addActionLabel(__('admin.actions.add_card_template_part'))
                ->extraAttributes(['data-sp3c-template-parts' => 'true'])
                ->columnSpanFull();
        }

        return $schema
            ->components([
                Section::make(__('admin.settings_sp3c.editor.draft_heading'))
                    ->description(__('admin.settings_sp3c.editor.draft_description'))
                    ->schema($fields)
                    ->extraAttributes(['data-sp3c-template-editor' => 'true'])
                    ->columns(3),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->restoreProfilingConfiguration();
        $this->enforceCurrentCapability();

        if ($this->refuseMutationBeforeDehydration()) {
            return;
        }

        $profiler = app(SettingsPageProfiler::class);

        $profiler->withSubject('card-template-editor', function () use ($profiler): void {
            $profiler->withRequestKind(SettingsPageProfiler::REQUEST_SAVE, function () use ($profiler): void {
                $profiler->measure('save.total', function (): void {
                    try {
                        $this->beginDatabaseTransaction();
                        $this->callHook('beforeValidate');
                        $draft = app(SettingsPageProfiler::class)->measure(
                            'save.validation.total',
                            fn (): array => $this->form->getState(),
                            SettingsPageProfiler::REQUEST_SAVE,
                        );
                        $this->callHook('afterValidate');
                        $result = app(SettingsPageProfiler::class)->measure(
                            'save.settings_persist',
                            fn (): CardTemplateWriteResult => $this->writeDraft(
                                $draft,
                                fn () => $this->callHook('beforeSave'),
                                fn () => $this->callHook('afterSave'),
                            ),
                            SettingsPageProfiler::REQUEST_SAVE,
                            app(SettingsPageProfiler::class)->payloadBytes($draft),
                        );
                    } catch (CardTemplateWriteException $exception) {
                        $this->rollBackDatabaseTransaction();
                        $this->reportWriteFailure($exception);

                        return;
                    } catch (Throwable $exception) {
                        $this->rollBackDatabaseTransaction();

                        throw $exception;
                    }

                    $this->commitDatabaseTransaction();
                    $this->rememberData();
                    $this->getSavedNotification()?->send();
                    $redirectUrl = EditCardTemplate::getUrl([
                        'family' => $result->family,
                        'key' => $result->key,
                    ]);
                    $this->redirect($redirectUrl, navigate: FilamentView::hasSpaMode($redirectUrl));
                }, SettingsPageProfiler::REQUEST_SAVE);
            });
        });
    }

    public function getTitle(): string
    {
        return __('admin.settings_sp3c.editor.title');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return view('filament.pages.card-template-import-lock-metadata', [
            'familyImportLocked' => $this->familyImportLocked,
        ]);
    }

    /**
     * @return array<Action>
     */
    public function getFormActions(): array
    {
        return [
            $this->getSaveFormAction(),
            Action::make('cancel')
                ->label(__('admin.actions.cancel'))
                ->color('gray')
                ->url(CardTemplateSettings::getUrl()),
        ];
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('previewPanel')
                ->label(__('admin.settings_sp3c.preview.open'))
                ->icon(Heroicon::OutlinedEye)
                ->color('gray')
                ->extraAttributes([
                    'class' => 'xl:hidden',
                    'data-test' => 'card-template-preview-open',
                ])
                ->mountUsing(function (): void {
                    if ($this->previewStatus !== 'ready' || $this->previewHtml === null) {
                        $this->refreshPreview();
                    }
                })
                ->slideOver()
                ->stickyModalHeader()
                ->modalWidth(Width::TwoExtraLarge)
                ->modalHeading(__('admin.settings_sp3c.preview.title'))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel(__('admin.settings_sp3c.preview.close'))
                ->modalContent(fn (): View => view('filament.pages.card-template-preview', [
                    'modal' => true,
                    'previewStatus' => $this->previewStatus,
                    'previewFamily' => $this->previewFamily,
                    'previewSampleLabel' => $this->previewSampleLabel,
                    'previewHtml' => $this->previewHtml,
                    'previewRefreshedAt' => $this->previewRefreshedAt,
                ])),
        ];
    }

    public function previewSampleForm(Schema $schema): Schema
    {
        $components = $this->canChoosePreviewSample()
            ? [
                Select::make('sample_id')
                    ->label(__('admin.settings_sp3c.preview.choose_sample'))
                    ->placeholder(__('admin.settings_sp3c.preview.sample_placeholder'))
                    ->options(fn (): array => $this->canChoosePreviewSample()
                        ? app(CardTemplatePreviewer::class)->initialSampleOptions($this->currentPreviewFamily())
                        : [])
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->optionsLimit(CardTemplatePreviewer::SAMPLE_LIMIT)
                    ->getSearchResultsUsing(function (string $search): array {
                        if (! $this->canChoosePreviewSample()) {
                            return [];
                        }

                        return app(CardTemplatePreviewer::class)
                            ->sampleOptions($this->currentPreviewFamily(), $search);
                    })
                    ->getOptionLabelUsing(function (mixed $value): ?string {
                        if (! $this->canChoosePreviewSample() || ! is_numeric($value)) {
                            return null;
                        }

                        if ((int) $value === $this->previewSampleId && $this->previewSampleLabel !== null) {
                            return $this->previewSampleLabel;
                        }

                        return app(CardTemplatePreviewer::class)->sampleLabel(
                            $this->currentPreviewFamily(),
                            (int) $value,
                        );
                    })
                    ->live()
                    ->afterStateUpdated(function (mixed $state): void {
                        $this->selectPreviewSample($state);
                    }),
            ]
            : [];

        return $schema
            ->components($components)
            ->statePath('previewControls');
    }

    public function selectPreviewSample(mixed $sampleId): void
    {
        abort_unless(static::canAccess(), 403);

        if (! $this->canChoosePreviewSample() || ! is_numeric($sampleId)) {
            $this->previewControls['sample_id'] = null;

            return;
        }

        $this->refreshPreview((int) $sampleId);
    }

    public function setBuilderDisplayMode(string $mode): void
    {
        abort_unless(static::canAccess(), 403);
        $this->builderDisplayMode = $mode;
        $this->normalizeBuilderDisplayMode();
    }

    public function refreshPreview(?int $sampleId = null): void
    {
        abort_unless(static::canAccess(), 403);
        $draft = is_array($this->data) ? $this->data : [];
        $this->previewFamily = $this->familyFromDraft($draft);
        $this->previewDraftHash = $this->draftHash($draft);

        if ($this->sp3aMeasurementMode) {
            $this->clearPreview('idle');

            return;
        }

        if ($this->restricted) {
            $this->clearPreview('restricted');

            return;
        }

        try {
            $preview = app(CardTemplatePreviewer::class)->preview(
                $draft,
                $sampleId ?? $this->previewSampleId,
            );
        } catch (CardTemplateWriteException $exception) {
            $status = $exception->getMessage() === 'preview_sample_missing'
                ? 'no_sample'
                : 'invalid_draft';
            $this->clearPreview($status);

            return;
        } catch (Throwable $exception) {
            report($exception);
            $this->clearPreview('sample_error');

            return;
        }

        $this->previewStatus = 'ready';
        $this->previewFamily = $preview['family'];
        $this->previewSampleId = $preview['sample_id'];
        $this->previewControls['sample_id'] = $preview['sample_id'];
        $this->previewSampleLabel = $preview['sample_label'];
        $this->previewHtml = $preview['html'];
        $this->previewRefreshedAt = now()->timezone('Asia/Jerusalem')->format('d/m/Y H:i:s');
    }

    public function previewIsStale(): bool
    {
        if ($this->previewDraftHash === null || ! is_array($this->data)) {
            return false;
        }

        return ! hash_equals($this->previewDraftHash, $this->draftHash($this->data));
    }

    public function previewEmptyMessage(): string
    {
        $family = in_array($this->previewFamily, PublicFrontCardTemplateRegistry::families(), true)
            ? $this->previewFamily
            : PublicFrontCardTemplateRegistry::CONTENT_ITEM_FAMILY;

        return __("admin.settings_sp3c.preview.empty_{$family}");
    }

    protected function initializePreview(): void
    {
        $this->refreshPreview();
    }

    /**
     * @param  array<string, mixed>  $draft
     */
    abstract protected function writeDraft(
        array $draft,
        Closure $beforePersist,
        Closure $afterPersist,
    ): CardTemplateWriteResult;

    protected function initializeMeasurementMode(): void
    {
        $this->sp3aMeasurementMode = app()->environment('local') && request()->boolean('sp3a_measure');
        $this->profilingMode = $this->sp3aMeasurementMode && request()->boolean('sp3a_profile');
        $this->measurementFixtureIdentity = $this->sp3aMeasurementMode
            ? 'content_item:sp3a_content_item_1'
            : null;
        $this->restoreProfilingConfiguration();
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    protected function setFamilyImportLock(array $snapshot, string $family): void
    {
        $lockedPaths = is_array($snapshot['import_locks']['locked_paths'] ?? null)
            ? $snapshot['import_locks']['locked_paths']
            : [];
        $this->familyImportLocked = in_array("card_templates.{$family}", $lockedPaths, true);
    }

    protected function isDefaultIdentity(string $family, string $key): bool
    {
        return (PublicFrontCardTemplateRegistry::defaultTemplateKeys()[$family] ?? null) === $key;
    }

    protected function reportWriteFailure(CardTemplateWriteException $exception): void
    {
        $reason = $exception->getMessage();
        $translationKey = "admin.settings_sp3c.errors.{$reason}";
        $message = __($translationKey);

        if ($message === $translationKey) {
            $message = __('admin.settings_sp3c.errors.validation');
        }

        if ($exception->details !== []) {
            $message .= ' '.implode(', ', $exception->details);
        }

        $this->addError('data.key', $message);
        Notification::make()
            ->danger()
            ->title($message)
            ->send();
    }

    protected function enforceCurrentCapability(): void
    {
        $policy = app(CardTemplateAccessPolicy::class);
        $this->capable = $policy->currentActorCanManageProtectedTemplates();

        if ($this->capable || ! is_array($this->data)) {
            return;
        }

        if ($this->templateProtectedAtMount) {
            unset($this->data['parts']);
            $this->restricted = true;

            return;
        }

        if (array_key_exists('parts', $this->data) && $policy->isProtected($this->data)) {
            $this->data = $policy->stripProtectedParts($this->data);
            $this->protectedForgeryDetected = true;
        }
    }

    protected function refuseMutationBeforeDehydration(): bool
    {
        if ($this->sp3aMeasurementMode) {
            $this->reportWriteFailure(CardTemplateWriteException::named('measurement'));

            return true;
        }

        if ($this->protectedForgeryDetected) {
            $this->reportWriteFailure(CardTemplateWriteException::named('protected'));

            return true;
        }

        return false;
    }

    protected function restoreProfilingConfiguration(): void
    {
        if (app()->environment('local') && $this->profilingMode) {
            config()->set('settings.profiling.enabled', true);
        }
    }

    /**
     * @param  array<string, mixed>  $draft
     */
    private function familyFromDraft(array $draft): ?string
    {
        $family = $draft['family'] ?? null;

        return is_string($family) ? $family : null;
    }

    private function currentPreviewFamily(): string
    {
        return $this->previewFamily
            ?? $this->familyFromDraft(is_array($this->data) ? $this->data : [])
            ?? PublicFrontCardTemplateRegistry::CONTENT_ITEM_FAMILY;
    }

    public function canChoosePreviewSample(): bool
    {
        if ($this->restricted || $this->previewStatus === 'restricted') {
            return false;
        }

        if ($this->templateProtectedAtMount && ! $this->capable) {
            return false;
        }

        return in_array($this->currentPreviewFamily(), PublicFrontCardTemplateRegistry::families(), true);
    }

    protected function cardTemplatePartPreviewsEnabled(bool $previews): bool
    {
        return $previews && $this->builderDisplayMode === self::BUILDER_DISPLAY_SLIDE_OVER;
    }

    protected function configureCardTemplatePartEditAction(Action $action): Action
    {
        return $action
            ->schema(function (array $arguments, Builder $component, Schema $schema): Schema {
                return $schema
                    ->components(
                        $component->getChildSchema($arguments['item'])
                            ->getClone()
                            ->getComponents(withHidden: true),
                    )
                    ->columns(['default' => 1, 'lg' => 2]);
            })
            ->slideOver()
            ->slideOverPosition(SlideOverPosition::Start)
            ->modalWidth(Width::ThreeExtraLarge)
            ->stickyModalHeader()
            ->stickyModalFooter();
    }

    /**
     * @param  array<string, mixed>  $draft
     */
    private function draftHash(array $draft): string
    {
        return hash('sha256', json_encode(
            $draft,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        ));
    }

    private function clearPreview(string $status): void
    {
        $this->previewStatus = $status;
        $this->previewSampleId = null;
        $this->previewControls['sample_id'] = null;
        $this->previewSampleLabel = null;
        $this->previewHtml = null;
        $this->previewRefreshedAt = null;
    }

    private function normalizeBuilderDisplayMode(): void
    {
        if (! in_array($this->builderDisplayMode, [
            self::BUILDER_DISPLAY_INLINE,
            self::BUILDER_DISPLAY_SLIDE_OVER,
        ], true)) {
            $this->builderDisplayMode = self::BUILDER_DISPLAY_SLIDE_OVER;
        }
    }
}
