<?php

namespace Tests\Support;

use App\Support\PublicFront\Cards\PublicFrontCardTemplateRegistry;
use BackedEnum;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Locked;

class SettingsSp3cCanaryPage extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBeaker;

    protected string $view = 'settings-sp3c-canary::page';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    /** @var array<string, mixed>|null */
    public ?array $confirmedDraft = null;

    #[Locked]
    public bool $previews = true;

    #[Locked]
    public bool $capable = true;

    #[Locked]
    public bool $restricted = false;

    #[Locked]
    public ?int $selectedPartIndex = null;

    #[Locked]
    public ?int $selectedChildIndex = null;

    public function mount(
        bool $previews = true,
        bool $capable = true,
        ?int $selectedPartIndex = null,
        ?int $selectedChildIndex = null,
    ): void {
        $this->previews = $previews;
        $this->capable = $capable;
        $this->selectedPartIndex = $selectedPartIndex;
        $this->selectedChildIndex = $selectedChildIndex;

        $template = app(SettingsSp3cDeepestFixture::class)->template();

        if (! $capable) {
            unset($template['parts']);
            $this->restricted = true;
        }

        $this->form->fill($template);
    }

    public function getTitle(): string
    {
        return __('admin.settings_sp3c.canary.title');
    }

    public function form(Schema $schema): Schema
    {
        $fields = [
            TextInput::make('key')->required()->maxLength(80),
            Select::make('family')
                ->options(PublicFrontCardTemplateRegistry::familyOptions())
                ->required()
                ->native(false),
            TextInput::make('label')->required()->maxLength(120),
            Select::make('layout')
                ->options(['cards' => 'cards', 'rows' => 'rows'])
                ->required()
                ->native(false),
            Select::make('density')
                ->options(['compact' => 'compact', 'comfortable' => 'comfortable'])
                ->required()
                ->native(false),
            Select::make('image_size')
                ->options(['small' => 'small', 'medium' => 'medium'])
                ->required()
                ->native(false),
            Select::make('title_size')
                ->options(['base' => 'base', 'lg' => 'lg'])
                ->required()
                ->native(false),
        ];

        if (! $this->restricted) {
            $parts = Builder::make('parts')
                ->label(__('admin.fields.card_template_parts'))
                ->blocks($this->partBlocks(allowGroups: true))
                ->reorderable()
                ->cloneable()
                ->deletable();

            if ($this->previews) {
                $parts->blockPreviews();
            }

            $fields[] = $parts->columnSpanFull();

            if ($this->selectedPartIndex !== null) {
                $fields[] = Section::make(__('admin.settings_sp3c.canary.selected_editor'))
                    ->schema($this->selectedPartSchema("parts.{$this->selectedPartIndex}.data"))
                    ->extraAttributes(['data-sp3c-canary-selected-editor' => 'top'])
                    ->columnSpanFull();
            }

            if ($this->selectedPartIndex !== null && $this->selectedChildIndex !== null) {
                $fields[] = Section::make(__('admin.settings_sp3c.canary.selected_child_editor'))
                    ->schema($this->selectedPartSchema("parts.{$this->selectedPartIndex}.data.children.{$this->selectedChildIndex}.data"))
                    ->extraAttributes(['data-sp3c-canary-selected-editor' => 'nested'])
                    ->columnSpanFull();
            }
        }

        return $schema
            ->components([
                Section::make(__('admin.settings_sp3c.canary.draft'))
                    ->schema($fields)
                    ->columns(2),
            ])
            ->statePath('data.template');
    }

    public function confirmDraft(): void
    {
        $this->confirmedDraft = $this->form->getState();
    }

    public function simulatePageFailure(string $kind): void
    {
        abort_unless(in_array($kind, ['validation', 'stale', 'collision'], true), 404);

        $this->addError('data.template.key', __('admin.settings_sp3c.canary.simulated_failure', [
            'kind' => $kind,
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    public function sp3cMeasurementState(): array
    {
        return [
            'data' => $this->data,
            'confirmed_draft' => $this->confirmedDraft,
            'mounted_actions' => $this->mountedActions,
        ];
    }

    /**
     * @return array<int, Block>
     */
    private function partBlocks(bool $allowGroups): array
    {
        return collect(PublicFrontCardTemplateRegistry::partTypes())
            ->filter(fn (string $type): bool => $allowGroups || $type !== 'part_group')
            ->map(function (string $type): Block {
                $block = Block::make($type)
                    ->label(PublicFrontCardTemplateRegistry::partTypeOptions()[$type] ?? $type)
                    ->schema($this->partSchema($type));

                if ($this->previews) {
                    $block->preview('settings-sp3c-canary::part-summary');
                }

                return $block;
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, mixed>
     */
    private function partSchema(string $type): array
    {
        $isGroup = $type === 'part_group';
        $requiresSource = ! in_array($type, ['divider', 'spacer', 'part_group'], true);
        $schema = [
            Toggle::make('visible')->default(true),
            Select::make('source')
                ->options(PublicFrontCardTemplateRegistry::sourceOptions())
                ->required($requiresSource)
                ->visible($requiresSource)
                ->native(false),
            Select::make('attribute')
                ->options(fn (): array => collect(PublicFrontCardTemplateRegistry::attributes())
                    ->flatten()
                    ->unique()
                    ->mapWithKeys(fn (string $attribute): array => [$attribute => $attribute])
                    ->all())
                ->required($requiresSource)
                ->visible($requiresSource)
                ->native(false),
            TextInput::make('label')->maxLength(240),
            Select::make('label_position')
                ->options(array_combine(
                    PublicFrontCardTemplateRegistry::labelPositions(),
                    PublicFrontCardTemplateRegistry::labelPositions(),
                ))
                ->native(false),
            Select::make('label_alignment')
                ->options(array_combine(
                    PublicFrontCardTemplateRegistry::labelAlignments(),
                    PublicFrontCardTemplateRegistry::labelAlignments(),
                ))
                ->native(false),
            TextInput::make('icon'),
            Select::make('icon_position')
                ->options(array_combine(
                    PublicFrontCardTemplateRegistry::iconPositions(),
                    PublicFrontCardTemplateRegistry::iconPositions(),
                ))
                ->native(false),
            Select::make('layout')
                ->options($isGroup
                    ? PublicFrontCardTemplateRegistry::groupLayoutOptions()
                    : PublicFrontCardTemplateRegistry::partLayoutOptions())
                ->native(false),
            TextInput::make('order')->integer()->minValue(0)->maxValue(1000),
            TextInput::make('text')->maxLength(1000),
        ];

        if (! $isGroup) {
            return $schema;
        }

        $children = Builder::make('children')
            ->blocks($this->partBlocks(allowGroups: false))
            ->reorderable()
            ->cloneable()
            ->deletable();

        if ($this->previews) {
            $children->blockPreviews();
        }

        return [
            ...$schema,
            Select::make('columns')
                ->options(PublicFrontCardTemplateRegistry::groupColumnOptions())
                ->native(false),
            Select::make('gap')
                ->options(PublicFrontCardTemplateRegistry::groupGapOptions())
                ->native(false),
            Select::make('alignment')
                ->options(PublicFrontCardTemplateRegistry::groupAlignmentOptions())
                ->native(false),
            $children->columnSpanFull(),
        ];
    }

    /**
     * @return array<int, mixed>
     */
    private function selectedPartSchema(string $prefix): array
    {
        return [
            Toggle::make("{$prefix}.visible"),
            Select::make("{$prefix}.source")
                ->options(PublicFrontCardTemplateRegistry::sourceOptions())
                ->native(false),
            Select::make("{$prefix}.attribute")
                ->options(fn (): array => collect(PublicFrontCardTemplateRegistry::attributes())
                    ->flatten()
                    ->unique()
                    ->mapWithKeys(fn (string $attribute): array => [$attribute => $attribute])
                    ->all())
                ->native(false),
            TextInput::make("{$prefix}.label")->maxLength(240),
            TextInput::make("{$prefix}.icon"),
            Select::make("{$prefix}.layout")
                ->options([
                    ...PublicFrontCardTemplateRegistry::partLayoutOptions(),
                    ...PublicFrontCardTemplateRegistry::groupLayoutOptions(),
                ])
                ->native(false),
            TextInput::make("{$prefix}.order")->integer()->minValue(0)->maxValue(1000),
            TextInput::make("{$prefix}.text")->maxLength(1000),
        ];
    }
}
