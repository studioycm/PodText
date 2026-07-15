<?php

namespace Tests\Support;

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

class SettingsSp3cCanaryLibraryPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static bool $shouldRegisterNavigation = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBeaker;

    protected string $view = 'settings-sp3c-canary::library';

    /** @var array<int, array<string, bool|int|string>> */
    #[Locked]
    public array $rows = [];

    public function mount(int $rowCount = 30): void
    {
        $this->rows = collect(app(SettingsSp3cDeepestFixture::class)->templates($rowCount))
            ->map(fn (array $template, int $index): array => [
                'record_key' => "configured:{$template['family']}:{$template['key']}",
                'identity' => "{$template['family']}:{$template['key']}",
                'label' => (string) $template['label'],
                'family_label' => (string) $template['family'],
                'layout_label' => (string) $template['layout'],
                'parts_status' => (string) count($template['parts']),
                'where_used' => '0 explicit / 0 implicit',
                'default_override' => false,
                'kind' => 'configured',
                'can_edit' => true,
                'can_clone' => true,
            ])
            ->all();
    }

    public function getTitle(): string
    {
        return __('admin.settings_sp3c.canary.library');
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (?string $search, array $filters): array => collect($this->rows)
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
                ->mapWithKeys(fn (array $row): array => [$row['record_key'] => $row])
                ->all())
            ->paginated(false)
            ->columns([
                TextColumn::make('label')->searchable(),
                TextColumn::make('identity')->searchable(),
                TextColumn::make('family_label'),
                TextColumn::make('layout_label'),
                TextColumn::make('parts_status'),
                TextColumn::make('where_used'),
                IconColumn::make('default_override')->boolean(),
            ])
            ->filters([
                TernaryFilter::make('default_override')
                    ->placeholder(__('admin.settings_sp3c.library.filters.all'))
                    ->trueLabel(__('admin.settings_sp3c.library.filters.overrides'))
                    ->falseLabel(__('admin.settings_sp3c.library.filters.non_overrides')),
            ])
            ->headerActions([
                Action::make('createTemplate')
                    ->label(__('admin.settings_sp3c.actions.create'))
                    ->icon(Heroicon::OutlinedPlus)
                    ->url('#'),
            ])
            ->recordActions([
                Action::make('editTemplate')
                    ->label(__('admin.actions.edit'))
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->visible(fn (array $record): bool => (bool) $record['can_edit'])
                    ->url('#'),
                Action::make('cloneTemplate')
                    ->label(__('admin.settings_sp3c.actions.clone'))
                    ->icon(Heroicon::OutlinedSquare2Stack)
                    ->visible(fn (array $record): bool => (bool) $record['can_clone'])
                    ->url('#'),
                Action::make('createOverride')
                    ->label(__('admin.settings_sp3c.actions.create_override'))
                    ->icon(Heroicon::OutlinedDocumentDuplicate)
                    ->visible(fn (array $record): bool => $record['kind'] === 'virtual')
                    ->url('#'),
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function sp3cMeasurementState(): array
    {
        return [
            'rows' => $this->rows,
            'mounted_actions' => $this->mountedActions,
        ];
    }
}
