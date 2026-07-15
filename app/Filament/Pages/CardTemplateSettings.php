<?php

namespace App\Filament\Pages;

use App\Enums\UserRole;
use App\Filament\Support\Concerns\UsesAdminNavigationOrder;
use App\Models\User;
use App\Settings\PublicContentSettings;
use App\Support\Settings\CardTemplates\CardTemplateLibraryProjection;
use App\Support\Settings\CardTemplates\CardTemplateLibraryProjector;
use App\Support\Settings\SettingsPageProfiler;
use App\Support\Settings\SettingsSp3aMeasurementFixture;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;

class CardTemplateSettings extends Page implements HasTable
{
    use InteractsWithTable;
    use UsesAdminNavigationOrder;

    protected static ?string $slug = 'settings/card-templates';

    protected static bool $shouldRegisterNavigation = true;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected string $view = 'filament.pages.card-template-settings';

    #[Locked]
    public bool $sp3aMeasurementMode = false;

    #[Locked]
    public bool $profilingMode = false;

    #[Locked]
    public ?string $measurementFixtureIdentity = null;

    private ?CardTemplateLibraryProjection $requestProjection = null;

    /** @var array<string, mixed>|null */
    private ?array $requestSnapshot = null;

    public static function getNavigationLabel(): string
    {
        return __('admin.pages.card_template_settings.navigation');
    }

    public function getTitle(): string
    {
        return __('admin.pages.card_template_settings.title');
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->hasRoleAtLeast(UserRole::Admin);
    }

    public function mount(): void
    {
        $this->sp3aMeasurementMode = app()->environment('local') && request()->boolean('sp3a_measure');
        $this->profilingMode = $this->sp3aMeasurementMode && request()->boolean('sp3a_profile');
        $this->measurementFixtureIdentity = $this->sp3aMeasurementMode ? 'sp3a-library' : null;
        $this->restoreProfilingConfiguration();
    }

    public function hydrate(): void
    {
        $this->requestProjection = null;
        $this->requestSnapshot = null;
        $this->restoreProfilingConfiguration();
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (?string $search, array $filters): array => collect($this->projection()->records)
                ->when(filled($search), fn ($records) => $records->filter(function (array $record) use ($search): bool {
                    $haystack = collect(['label', 'identity', 'family_label', 'layout_label'])
                        ->map(fn (string $field): string => (string) ($record[$field] ?? ''))
                        ->implode(' ');

                    return Str::contains(Str::lower($haystack), Str::lower((string) $search));
                }))
                ->when(
                    filled($filters['default_override']['value'] ?? null),
                    fn ($records) => $records->whereStrict(
                        'default_override',
                        (bool) (int) $filters['default_override']['value'],
                    ),
                )
                ->mapWithKeys(fn (array $record): array => [$record['record_key'] => $record])
                ->all())
            ->paginated(false)
            ->columns([
                TextColumn::make('label')
                    ->label(__('admin.settings_sp3c.library.columns.label'))
                    ->searchable(),
                TextColumn::make('identity')
                    ->label(__('admin.settings_sp3c.library.columns.identity'))
                    ->searchable(),
                TextColumn::make('family_label')
                    ->label(__('admin.settings_sp3c.library.columns.family')),
                TextColumn::make('layout_label')
                    ->label(__('admin.settings_sp3c.library.columns.layout')),
                TextColumn::make('parts_status')
                    ->label(__('admin.settings_sp3c.library.columns.parts')),
                TextColumn::make('where_used')
                    ->label(__('admin.settings_sp3c.library.columns.where_used')),
                IconColumn::make('default_override')
                    ->label(__('admin.settings_sp3c.library.columns.override'))
                    ->boolean(),
            ])
            ->filters([
                TernaryFilter::make('default_override')
                    ->label(__('admin.settings_sp3c.library.columns.override'))
                    ->placeholder(__('admin.settings_sp3c.library.filters.all'))
                    ->trueLabel(__('admin.settings_sp3c.library.filters.overrides'))
                    ->falseLabel(__('admin.settings_sp3c.library.filters.non_overrides')),
            ])
            ->headerActions([
                Action::make('createTemplate')
                    ->label(__('admin.settings_sp3c.actions.create'))
                    ->icon(Heroicon::OutlinedPlus)
                    ->url(function (): string {
                        abort_unless(static::canAccess(), 403);

                        return CreateCardTemplate::getUrl(['mode' => 'blank']);
                    }),
            ])
            ->recordActions([
                Action::make('editTemplate')
                    ->label(__('admin.actions.edit'))
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->visible(fn (array $record): bool => ($record['kind'] ?? null) === 'configured'
                        && (bool) ($record['can_edit'] ?? false))
                    ->url(function (array $record): string {
                        abort_unless(static::canAccess(), 403);

                        return EditCardTemplate::getUrl([
                            'family' => $record['family'],
                            'key' => $record['key'],
                        ]);
                    }),
                Action::make('cloneTemplate')
                    ->label(__('admin.settings_sp3c.actions.clone'))
                    ->icon(Heroicon::OutlinedSquare2Stack)
                    ->visible(fn (array $record): bool => ($record['kind'] ?? null) === 'configured'
                        && (bool) ($record['can_clone'] ?? false))
                    ->url(function (array $record): string {
                        abort_unless(static::canAccess(), 403);

                        return CreateCardTemplate::getUrl([
                            'mode' => 'clone',
                            'family' => $record['family'],
                            'key' => $record['key'],
                        ]);
                    }),
                Action::make('createOverride')
                    ->label(__('admin.settings_sp3c.actions.create_override'))
                    ->icon(Heroicon::OutlinedDocumentDuplicate)
                    ->visible(fn (array $record): bool => ($record['kind'] ?? null) === 'virtual')
                    ->url(function (array $record): string {
                        abort_unless(static::canAccess(), 403);

                        return CreateCardTemplate::getUrl([
                            'mode' => 'override',
                            'family' => $record['family'],
                            'key' => $record['key'],
                        ]);
                    }),
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $snapshot = $this->snapshot();
        $lockedPaths = is_array($snapshot['import_locks']['locked_paths'] ?? null)
            ? $snapshot['import_locks']['locked_paths']
            : [];

        return [
            'familyLocks' => collect(['content_item', 'content_group', 'contributor'])
                ->mapWithKeys(fn (string $family): array => [
                    $family => in_array("card_templates.{$family}", $lockedPaths, true),
                ])
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function sp3cMeasurementState(): array
    {
        return [
            'rows' => $this->projection()->records,
            'mounted_actions' => $this->mountedActions,
        ];
    }

    private function projection(): CardTemplateLibraryProjection
    {
        return $this->requestProjection ??= app(SettingsPageProfiler::class)->withSubject(
            'card-template-library',
            fn (): CardTemplateLibraryProjection => app(CardTemplateLibraryProjector::class)
                ->project($this->snapshot()),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(): array
    {
        if ($this->requestSnapshot !== null) {
            return $this->requestSnapshot;
        }

        if ($this->sp3aMeasurementMode) {
            return $this->requestSnapshot = app(SettingsSp3aMeasurementFixture::class)->payload();
        }

        $settings = app(PublicContentSettings::class);
        $settings->refresh();

        return $this->requestSnapshot = $settings->toArray();
    }

    private function restoreProfilingConfiguration(): void
    {
        if (app()->environment('local') && $this->profilingMode) {
            config()->set('settings.profiling.enabled', true);
        }
    }
}
