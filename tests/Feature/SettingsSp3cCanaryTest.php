<?php

use App\Models\Author;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\Transcription;
use App\Support\PublicFront\Cards\PublicFrontCardTemplateRegistry;
use App\Support\Settings\CardTemplates\CardTemplatePreviewer;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\View;
use Livewire\Livewire;
use Tests\Support\SettingsSp3cCanaryLibraryPage;
use Tests\Support\SettingsSp3cCanaryMeasurement;
use Tests\Support\SettingsSp3cCanaryPage;
use Tests\Support\SettingsSp3cDeepestFixture;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Http::preventStrayRequests();
    Mail::fake();
    View::addNamespace('settings-sp3c-canary', base_path('tests/Fixtures/settings-sp3c-canary'));
});

/**
 * @param  array<int|string, array{type: string, data: array<string, mixed>}>  $parts
 */
function settingsSp3cCanaryPartKey(array $parts, string $sentinel): int|string
{
    foreach ($parts as $key => $part) {
        if (str_contains((string) ($part['data']['label'] ?? ''), $sentinel)) {
            return $key;
        }
    }

    throw new RuntimeException("Canary part [{$sentinel}] was not found.");
}

it('renders every applicable part through escaped preview chrome', function (): void {
    $restoreBuilderFake = Builder::fake();

    try {
        $fixture = app(SettingsSp3cDeepestFixture::class);
        $component = Livewire::test(SettingsSp3cCanaryPage::class, [
            'previews' => true,
            'capable' => true,
        ])->assertOk();

        $metrics = app(SettingsSp3cCanaryMeasurement::class)->measure($component);

        expect($metrics['summary_chrome'])
            ->toBeGreaterThanOrEqual(count($fixture->template()['parts']))
            ->and($component->html())
            ->not->toContain('<script data-sp3c-hostile=');
    } finally {
        $restoreBuilderFake();
    }
});

it('keeps protected values out of non capable html and serialized state', function (): void {
    $component = Livewire::test(SettingsSp3cCanaryPage::class, [
        'previews' => true,
        'capable' => false,
    ])->assertOk();

    $html = $component->html();
    $state = json_encode(
        $component->instance()->form->getRawState(),
        JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
    );

    expect($html)
        ->toContain(__('admin.settings_sp3c.canary.restricted'))
        ->not->toContain(SettingsSp3cDeepestFixture::PROTECTED_TOP_SENTINEL)
        ->not->toContain(SettingsSp3cDeepestFixture::PROTECTED_NESTED_SENTINEL)
        ->and($state)
        ->not->toContain(SettingsSp3cDeepestFixture::PROTECTED_TOP_SENTINEL)
        ->not->toContain(SettingsSp3cDeepestFixture::PROTECTED_NESTED_SENTINEL)
        ->not->toContain('parts');
});

it('mounts and confirms only one selected top level part editor', function (): void {
    $restoreBuilderFake = Builder::fake();

    try {
        $component = Livewire::test(SettingsSp3cCanaryPage::class, [
            'previews' => true,
            'capable' => true,
        ]);
        $parts = $component->instance()->form->getRawState()['parts'];
        $selectedKey = settingsSp3cCanaryPartKey(
            $parts,
            SettingsSp3cDeepestFixture::ORDINARY_SELECTED_SENTINEL,
        );
        $edit = TestAction::make('edit')->schemaComponent('parts', 'form');

        $component
            ->mountAction($edit, ['item' => $selectedKey])
            ->assertSchemaStateSet([
                'label' => SettingsSp3cDeepestFixture::ORDINARY_SELECTED_SENTINEL,
            ]);

        $component
            ->fillForm(['label' => 'SP3C_EDIT_CONFIRMED'])
            ->callMountedAction();

        $updatedParts = $component->instance()->form->getRawState()['parts'];

        expect($updatedParts[$selectedKey]['data']['label'])->toBe('SP3C_EDIT_CONFIRMED')
            ->and($updatedParts[settingsSp3cCanaryPartKey(
                $updatedParts,
                SettingsSp3cDeepestFixture::ORDINARY_UNSELECTED_SENTINEL,
            )]['data']['label'])->toBe(SettingsSp3cDeepestFixture::ORDINARY_UNSELECTED_SENTINEL);
    } finally {
        $restoreBuilderFake();
    }
});

it('keeps cancel validation and simulated page failures local to the parent draft', function (): void {
    $restoreBuilderFake = Builder::fake();

    try {
        $component = Livewire::test(SettingsSp3cCanaryPage::class, [
            'previews' => true,
            'capable' => true,
        ])->set('data.template.label', 'SP3C_PARENT_DRAFT_SURVIVES');
        $parts = $component->instance()->form->getRawState()['parts'];
        $selectedKey = settingsSp3cCanaryPartKey(
            $parts,
            SettingsSp3cDeepestFixture::ORDINARY_SELECTED_SENTINEL,
        );
        $edit = TestAction::make('edit')->schemaComponent('parts', 'form');
        $originalLabel = $parts[$selectedKey]['data']['label'];

        $component
            ->mountAction($edit, ['item' => $selectedKey])
            ->fillForm(['label' => 'SP3C_CANCELLED'])
            ->unmountAction();

        expect($component->instance()->form->getRawState()['parts'][$selectedKey]['data']['label'])
            ->toBe($originalLabel);

        $component
            ->mountAction($edit, ['item' => $selectedKey])
            ->fillForm(['label' => str_repeat('x', 241)])
            ->callMountedAction()
            ->assertHasFormErrors(['label' => 'max']);

        expect($component->instance()->form->getRawState()['parts'][$selectedKey]['data']['label'])
            ->toBe($originalLabel);

        $component->unmountAction();

        foreach (['validation', 'stale', 'collision'] as $kind) {
            $component->call('simulatePageFailure', $kind);
        }

        expect($component->get('data.template.label'))->toBe('SP3C_PARENT_DRAFT_SURVIVES')
            ->and($component->instance()->form->getRawState()['parts'][$selectedKey]['data']['label'])
            ->toBe($originalLabel);

        Livewire::test(SettingsSp3cCanaryPage::class, [
            'previews' => true,
            'capable' => true,
        ])->assertSet('data.template.label', fn (string $label): bool => $label !== 'SP3C_PARENT_DRAFT_SURVIVES');
    } finally {
        $restoreBuilderFake();
    }
});

it('clones deletes reorders closes and reopens top level parts without repeated type bleed', function (): void {
    $component = Livewire::test(SettingsSp3cCanaryPage::class, [
        'previews' => true,
        'capable' => true,
    ]);
    $initialParts = $component->instance()->form->getRawState()['parts'];
    $selectedKey = settingsSp3cCanaryPartKey(
        $initialParts,
        SettingsSp3cDeepestFixture::ORDINARY_SELECTED_SENTINEL,
    );
    $unselectedKey = settingsSp3cCanaryPartKey(
        $initialParts,
        SettingsSp3cDeepestFixture::ORDINARY_UNSELECTED_SENTINEL,
    );
    $path = fn (string $name): TestAction => TestAction::make($name)->schemaComponent('parts', 'form');

    $component->callAction($path('clone'), arguments: ['item' => $selectedKey]);
    $clonedParts = $component->instance()->form->getRawState()['parts'];
    $cloneKey = array_key_last($clonedParts);

    expect($clonedParts)->toHaveCount(count($initialParts) + 1)
        ->and($clonedParts[$cloneKey]['type'])->toBe($initialParts[$selectedKey]['type'])
        ->and($clonedParts[$cloneKey]['data']['label'])->toBe($initialParts[$selectedKey]['data']['label'])
        ->and($clonedParts[$unselectedKey]['data']['label'])
        ->toBe(SettingsSp3cDeepestFixture::ORDINARY_UNSELECTED_SENTINEL);

    $component->callAction($path('delete'), arguments: ['item' => $cloneKey]);
    $afterDelete = $component->instance()->form->getRawState()['parts'];

    expect($afterDelete)->toHaveCount(count($initialParts));

    $reversedKeys = array_reverse(array_keys($afterDelete));
    $component->callAction($path('reorder'), arguments: ['items' => $reversedKeys]);

    expect(array_keys($component->instance()->form->getRawState()['parts']))
        ->toBe($reversedKeys);

    $component
        ->mountAction($path('edit'), ['item' => $selectedKey])
        ->unmountAction()
        ->mountAction($path('edit'), ['item' => $selectedKey])
        ->assertSchemaStateSet([
            'label' => SettingsSp3cDeepestFixture::ORDINARY_SELECTED_SENTINEL,
        ]);
});

it('edits one selected nested child and keeps sibling child state isolated', function (): void {
    $restoreBuilderFake = Builder::fake();

    try {
        $component = Livewire::test(SettingsSp3cCanaryPage::class, [
            'previews' => true,
            'capable' => true,
        ]);
        $parts = $component->instance()->form->getRawState()['parts'];
        $groupKey = collect($parts)->search(fn (array $part): bool => $part['type'] === 'part_group');
        expect($groupKey)->not->toBeFalse();
        $children = $parts[$groupKey]['data']['children'];
        $childKey = array_key_first($children);
        $siblingKey = array_keys($children)[1];
        $siblingBefore = $children[$siblingKey];

        $component
            ->mountAction(
                TestAction::make('edit')->schemaComponent('parts', 'form'),
                ['item' => $groupKey],
            )
            ->mountAction(
                TestAction::make('edit')->schemaComponent('children', 'mountedActionSchema0'),
                ['item' => $childKey],
            )
            ->fillForm(['label' => 'SP3C_NESTED_EDIT_CONFIRMED'])
            ->callMountedAction()
            ->callMountedAction();

        $updatedChildren = $component->instance()->form->getRawState()['parts'][$groupKey]['data']['children'];

        expect($updatedChildren[$childKey]['data']['label'])->toBe('SP3C_NESTED_EDIT_CONFIRMED')
            ->and($updatedChildren[$siblingKey])->toBe($siblingBefore);
    } finally {
        $restoreBuilderFake();
    }
});

it('renders controls only for the selected top level and nested paths', function (): void {
    $restoreBuilderFake = Builder::fake();

    try {
        $fixture = app(SettingsSp3cDeepestFixture::class)->template();
        $groupIndex = collect($fixture['parts'])->search(fn (array $part): bool => $part['type'] === 'part_group');
        expect($groupIndex)->toBeInt();
        $measure = app(SettingsSp3cCanaryMeasurement::class);
        $top = Livewire::test(SettingsSp3cCanaryPage::class, [
            'previews' => true,
            'capable' => true,
            'selectedPartIndex' => 0,
        ]);
        $nested = Livewire::test(SettingsSp3cCanaryPage::class, [
            'previews' => true,
            'capable' => true,
            'selectedPartIndex' => $groupIndex,
            'selectedChildIndex' => 0,
        ]);
        $topPaths = $measure->wireModelPaths($top->html());
        $nestedPaths = $measure->wireModelPaths($nested->html());

        expect($top->html())->toContain('data-sp3c-canary-selected-editor="top"')
            ->and($topPaths)->toContain('data.template.parts.0.data.label')
            ->and(collect($topPaths)->contains(fn (string $path): bool => str_contains($path, 'data.template.parts.1.data.')))->toBeFalse()
            ->and($nested->html())->toContain('data-sp3c-canary-selected-editor="nested"')
            ->and($nestedPaths)->toContain("data.template.parts.{$groupIndex}.data.children.0.data.label")
            ->and(collect($nestedPaths)->contains(fn (string $path): bool => str_contains($path, ".parts.{$groupIndex}.data.children.1.data.")))->toBeFalse();
    } finally {
        $restoreBuilderFake();
    }
});

it('keeps the isolated library projection query free and constant at scale', function (): void {
    $fixture = app(SettingsSp3cDeepestFixture::class);
    expect($fixture->sections(100))->toHaveCount(100);

    DB::enableQueryLog();
    DB::flushQueryLog();
    $small = Livewire::test(SettingsSp3cCanaryLibraryPage::class, ['rowCount' => 10]);
    $smallQueries = count(DB::getQueryLog());

    DB::flushQueryLog();
    $large = Livewire::test(SettingsSp3cCanaryLibraryPage::class, ['rowCount' => 100]);
    $largeQueries = count(DB::getQueryLog());

    $largeState = json_encode(
        $large->get('rows'),
        JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
    );

    expect($smallQueries)->toBe($largeQueries)
        ->and($largeQueries)->toBe(0)
        ->and($small->html())->not->toContain('<script data-sp3c-hostile=')
        ->and($largeState)->not->toContain('"parts":')
        ->and($large->get('rows'))->toHaveCount(100);
});

it('measures a deterministic wrapper and control reduction against the same surface', function (): void {
    $restoreBuilderFake = Builder::fake();

    try {
        $measure = app(SettingsSp3cCanaryMeasurement::class);
        $groupIndex = collect(app(SettingsSp3cDeepestFixture::class)->template()['parts'])
            ->search(fn (array $part): bool => $part['type'] === 'part_group');
        expect($groupIndex)->toBeInt();

        $samples = collect(range(1, 3))->map(function () use ($measure, $groupIndex): array {
            $control = Livewire::test(SettingsSp3cCanaryPage::class, [
                'previews' => false,
                'capable' => true,
            ]);
            $candidate = Livewire::test(SettingsSp3cCanaryPage::class, [
                'previews' => true,
                'capable' => true,
            ]);
            $selected = Livewire::test(SettingsSp3cCanaryPage::class, [
                'previews' => true,
                'capable' => true,
                'selectedPartIndex' => 0,
            ]);
            $nested = Livewire::test(SettingsSp3cCanaryPage::class, [
                'previews' => true,
                'capable' => true,
                'selectedPartIndex' => $groupIndex,
                'selectedChildIndex' => 0,
            ]);
            $library = Livewire::test(SettingsSp3cCanaryLibraryPage::class, [
                'rowCount' => 30,
            ]);

            return [
                'control' => $measure->measure($control),
                'candidate' => $measure->measure($candidate),
                'selected' => $measure->measure($selected),
                'nested' => $measure->measure($nested),
                'library' => $measure->measureHtml($library->html(), ['rows' => $library->get('rows')]),
            ];
        });

        $controlWrappers = $samples->max('control.field_wrappers');
        $candidateWrappers = $samples->max('candidate.field_wrappers');
        $controlControls = $samples->max('control.editor_controls');
        $candidateControls = $samples->max('candidate.editor_controls');
        $selectedWrappers = $samples->max('selected.field_wrappers');
        $selectedControls = $samples->max('selected.editor_controls');
        $nestedWrappers = $samples->max('nested.field_wrappers');
        $nestedControls = $samples->max('nested.editor_controls');

        if (getenv('SP3C_CANARY_REPORT') === '1') {
            fwrite(STDERR, json_encode([
                'samples' => $samples->all(),
                'frozen_plus_20_percent' => collect(['candidate', 'selected', 'nested', 'library'])
                    ->mapWithKeys(fn (string $surface): array => [
                        $surface => collect($samples->first()[$surface])
                            ->map(fn (int $value): int => (int) ceil($value * 1.2))
                            ->all(),
                    ])
                    ->all(),
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES).PHP_EOL);
        }

        expect($samples->pluck('control.field_wrappers')->unique())->toHaveCount(1)
            ->and($samples->pluck('candidate.field_wrappers')->unique())->toHaveCount(1)
            ->and($samples->pluck('control.editor_controls')->unique())->toHaveCount(1)
            ->and($samples->pluck('candidate.editor_controls')->unique())->toHaveCount(1)
            ->and($controlWrappers)->toBeGreaterThan(0)
            ->and($controlControls)->toBeGreaterThan(0)
            ->and(1 - ($candidateWrappers / $controlWrappers))->toBeGreaterThanOrEqual(0.70)
            ->and(1 - ($candidateControls / $controlControls))->toBeGreaterThanOrEqual(0.70)
            ->and(1 - ($selectedWrappers / $controlWrappers))->toBeGreaterThanOrEqual(0.70)
            ->and(1 - ($selectedControls / $controlControls))->toBeGreaterThanOrEqual(0.70)
            ->and(1 - ($nestedWrappers / $controlWrappers))->toBeGreaterThanOrEqual(0.70)
            ->and(1 - ($nestedControls / $controlControls))->toBeGreaterThanOrEqual(0.70)
            ->and($samples->pluck('library.elements')->unique())->toHaveCount(1)
            ->and($samples->pluck('library.serialized_state_bytes')->unique())->toHaveCount(1);
    } finally {
        $restoreBuilderFake();
    }
});

it('measures a deterministic bounded preview delta across editor states and families', function (): void {
    $restoreBuilderFake = Builder::fake();

    try {
        $fixture = app(SettingsSp3cDeepestFixture::class);
        $measure = app(SettingsSp3cCanaryMeasurement::class);
        $author = Author::factory()->create(['name' => 'SP3C Preview Contributor']);
        $group = ContentGroup::factory()->published()->create(['title' => 'SP3C Preview Group']);
        $item = ContentItem::factory()->for($group)->published()->create(['title' => 'SP3C Preview Item']);
        $transcription = Transcription::factory()
            ->for($item)
            ->forAuthor($author)
            ->published(now()->subMinute())
            ->create(['title' => 'SP3C Preview Transcription']);
        $transcription->syncTranscribers([$author]);
        $item->update(['featured_transcription_id' => $transcription->getKey()]);

        $previewer = app(CardTemplatePreviewer::class);
        $previews = collect(PublicFrontCardTemplateRegistry::families())
            ->mapWithKeys(function (string $family) use ($previewer): array {
                $draft = PublicFrontCardTemplateRegistry::defaultTemplateForFamily($family);
                $draft['label'] = "SP3C {$family} preview";

                return [$family => $previewer->preview($draft)];
            });
        $groupIndex = collect($fixture->template()['parts'])
            ->search(fn (array $part): bool => $part['type'] === 'part_group');
        expect($groupIndex)->toBeInt();

        $samples = collect(range(1, 3))->map(function () use ($fixture, $groupIndex, $measure, $previews): array {
            $states = [
                'unselected' => [],
                'selected' => ['selectedPartIndex' => 0],
                'nested' => [
                    'selectedPartIndex' => $groupIndex,
                    'selectedChildIndex' => 0,
                ],
            ];

            return collect($states)->mapWithKeys(function (array $selection, string $state) use ($fixture, $measure, $previews): array {
                $editor = Livewire::test(SettingsSp3cCanaryPage::class, [
                    'previews' => true,
                    'capable' => true,
                    ...$selection,
                ]);
                $baseHtml = $editor->html();
                $baseState = $editor->instance()->sp3cMeasurementState();
                $baseMetrics = $measure->measure($editor);
                $basePaths = $measure->wireModelPaths($baseHtml);
                $familyMetrics = $previews->map(function (array $preview, string $family) use ($baseHtml, $baseMetrics, $basePaths, $baseState, $fixture, $measure): array {
                    $previewState = [
                        'status' => 'ready',
                        'family' => $family,
                        'sample_id' => $preview['sample_id'],
                        'sample_label' => $preview['sample_label'],
                        'html' => $preview['html'],
                    ];
                    $readyHtml = $baseHtml.'<section data-card-template-preview-root>'.$preview['html'].'</section>';
                    $readyMetrics = $measure->measureHtml($readyHtml, [
                        ...$baseState,
                        'preview' => $previewState,
                    ]);
                    $staleMetrics = $measure->measureHtml(
                        $baseHtml.'<section data-card-template-preview-root data-preview-status="stale"></section>',
                        [
                            ...$baseState,
                            'preview' => [...$previewState, 'status' => 'stale', 'html' => null],
                        ],
                    );
                    $delta = collect($readyMetrics)
                        ->map(fn (int $value, string $metric): int => $value - ($baseMetrics[$metric] ?? 0))
                        ->all();
                    $serializedPreview = json_encode(
                        $previewState,
                        JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
                    );

                    expect($readyMetrics['field_wrappers'])->toBe($baseMetrics['field_wrappers'])
                        ->and($readyMetrics['editor_controls'])->toBe($baseMetrics['editor_controls'])
                        ->and($measure->wireModelPaths($readyHtml))->toBe($basePaths)
                        ->and($staleMetrics['field_wrappers'])->toBe($baseMetrics['field_wrappers'])
                        ->and($staleMetrics['editor_controls'])->toBe($baseMetrics['editor_controls'])
                        ->and($serializedPreview)->not->toContain('data.parts')
                        ->not->toContain('relations')
                        ->not->toContain('original')
                        ->and($readyHtml)->not->toContain('<script data-sp3c-hostile=')
                        ->and($readyHtml)->not->toContain('href=')
                        ->and($fixture->template())->toHaveKey('parts');

                    return [
                        'base' => $baseMetrics,
                        'stale' => $staleMetrics,
                        'ready' => $readyMetrics,
                        'delta' => $delta,
                    ];
                });

                return [$state => $familyMetrics->all()];
            })->all();
        });

        $flattened = $samples->flatMap(fn (array $states): array => collect($states)
            ->flatMap(fn (array $families): array => array_values($families))
            ->all());
        $maxDelta = collect(array_keys($flattened->first()['delta']))
            ->mapWithKeys(fn (string $metric): array => [
                $metric => $flattened->max("delta.{$metric}"),
            ]);

        if (getenv('STEP5B_CANARY_REPORT') === '1') {
            fwrite(STDERR, json_encode([
                'samples' => $samples->all(),
                'max_preview_delta' => $maxDelta->all(),
                'candidate_plus_20_percent' => $maxDelta
                    ->map(fn (int $value): int => (int) ceil($value * 1.2))
                    ->all(),
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES).PHP_EOL);
        }

        expect($samples)->toHaveCount(3)
            ->and($samples->unique()->count())->toBe(1)
            ->and($maxDelta['field_wrappers'])->toBe(0)
            ->and($maxDelta['editor_controls'])->toBe(0)
            ->and($maxDelta['wire_models'])->toBe(0)
            ->and($maxDelta['elements'])->toBeLessThanOrEqual(41)
            ->and($maxDelta['html_bytes'])->toBeLessThanOrEqual(8739)
            ->and($maxDelta['serialized_state_bytes'])->toBeLessThanOrEqual(9299);
    } finally {
        $restoreBuilderFake();
    }
});
