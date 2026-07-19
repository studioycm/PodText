<?php

use App\Enums\TranscriptionMode;
use App\Filament\Pages\CardTemplateSettings;
use App\Filament\Pages\CreateCardTemplate;
use App\Filament\Pages\EditCardTemplate;
use App\Models\Author;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\Transcription;
use App\Models\User;
use App\Settings\AdminUxSettings;
use App\Settings\PublicContentSettings;
use App\Support\PublicFront\Cards\PublicFrontCardTemplateRegistry;
use App\Support\PublicFront\PublicFrontConfigRegistry;
use App\Support\Settings\CardTemplates\CardTemplateDraftNormalizer;
use App\Support\Settings\CardTemplates\CardTemplateFocusedWriter;
use App\Support\Settings\CardTemplates\CardTemplatePartSummaryFormatter;
use App\Support\Settings\CardTemplates\CardTemplatePreviewer;
use App\Support\Settings\CardTemplates\CardTemplateReferenceScanner;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Support\Enums\SlideOverPosition;
use Filament\Support\Enums\Width;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Spatie\LaravelSettings\Events\SettingsSaved;
use Spatie\LaravelSettings\SettingsContainer;
use Tests\Support\SettingsSp3cCanaryMeasurement;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Http::preventStrayRequests();
    Mail::fake();
    fakeSettingsBackupSnapshotQueue();
    $this->actingAs(User::factory()->admin()->create());
});

/**
 * @return array<string, mixed>
 */
function step5bEditorTemplate(string $family = 'content_item', string $key = 'preview_target'): array
{
    $template = PublicFrontCardTemplateRegistry::defaultTemplateForFamily($family);
    $template['key'] = $key;
    $template['label'] = 'Step 5B preview target';

    return $template;
}

function step5bEditorSaveSetting(string $settingsClass, string $name, mixed $value): void
{
    DB::table('settings')->updateOrInsert(
        [
            'group' => $settingsClass::group(),
            'name' => $name,
        ],
        [
            'locked' => false,
            'payload' => json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ],
    );

    app()->forgetInstance($settingsClass);
    app(SettingsContainer::class)->clearCache();
}

function step5bEditorPublicItem(
    string $title,
    ?ContentGroup $group = null,
    ?Author $author = null,
): ContentItem {
    $group ??= ContentGroup::factory()->published()->create();
    $item = ContentItem::factory()->for($group)->published()->create(['title' => $title]);
    $transcriptionFactory = Transcription::factory()
        ->for($item)
        ->published(now()->subMinute());

    if ($author) {
        $transcriptionFactory->forAuthor($author);
    }

    $transcription = $transcriptionFactory->create(['title' => $title]);

    if ($author) {
        $transcription->syncTranscribers([$author]);
    }

    $item->update(['featured_transcription_id' => $transcription->getKey()]);

    return $item->refresh();
}

it('previews the current single draft explicitly without settings or mutation services', function (): void {
    $template = step5bEditorTemplate();
    step5bEditorSaveSetting(PublicContentSettings::class, 'card_templates', [$template]);
    $item = step5bEditorPublicItem('Editor Preview Episode');
    $component = Livewire::test(EditCardTemplate::class, [
        'family' => 'content_item',
        'key' => 'preview_target',
    ]);

    expect($component->get('previewStatus'))->toBe('ready')
        ->and($component->get('previewSampleId'))->toBe($item->getKey())
        ->and($component->get('previewHtml'))->toContain('data-card-template-family="content_item"')
        ->and($component->get('previewRefreshedAt'))->toMatch('/^\d{2}\/\d{2} \d{2}:\d{2}$/')
        ->and($component->instance()->previewIsStale())->toBeFalse()
        ->and(substr_count($component->html(), '(min-width: 1024px)'))->toBe(2)
        ->and($component->html())->toContain('lg:grid-cols-[minmax(0,1fr)_minmax(16rem,20rem)]')
        ->and($component->html())->toContain('xl:grid-cols-[minmax(0,1fr)_minmax(20rem,26rem)]')
        ->and($component->html())->not->toContain('(min-width: 1280px)')
        ->and($component->html())->not->toContain('lg:grid-cols-[minmax(0,1fr)_minmax(20rem,26rem)]');

    $component
        ->assertSeeHtml('data-card-template-preview-width')
        ->assertSeeHtml('data-card-template-preview-width-plane')
        ->assertDontSeeHtml('data-card-template-preview-zoom-plane');

    $component->set('data.label', 'Identity-only label change');
    expect($component->instance()->previewIsStale())->toBeTrue();

    Event::fake([SettingsSaved::class]);
    $writer = Mockery::mock(CardTemplateFocusedWriter::class);
    $writer->shouldNotReceive('create', 'edit', 'delete');
    app()->instance(CardTemplateFocusedWriter::class, $writer);
    $scanner = Mockery::mock(CardTemplateReferenceScanner::class);
    $scanner->shouldNotReceive('scan');
    app()->instance(CardTemplateReferenceScanner::class, $scanner);
    app()->forgetInstance(PublicContentSettings::class);
    app()->bind(PublicContentSettings::class, fn (): never => throw new RuntimeException('Preview refresh read configured settings.'));

    $component
        ->set('data.title_size', 'lg')
        ->assertSet('previewStatus', 'ready')
        ->assertSet('previewSampleId', $item->getKey());

    expect($component->instance()->previewIsStale())->toBeFalse()
        ->and($component->get('previewHtml'))->toContain('data-card-title-size="lg"');
    Event::assertNotDispatched(SettingsSaved::class);

    foreach (['previewStatus', 'previewSampleId', 'previewHtml', 'previewDraftHash'] as $property) {
        expect(fn () => $component->set($property, null))
            ->toThrow(Exception::class, 'Cannot update locked property');
    }

    $previewer = Mockery::mock(CardTemplatePreviewer::class);
    $previewer->shouldNotReceive('preview');
    $previewer->shouldReceive('sampleLabel')->andReturn('Editor Preview Episode');
    $previewer->shouldReceive('initialSampleOptions')->andReturn([
        $item->getKey() => 'Editor Preview Episode',
    ]);
    app()->instance(CardTemplatePreviewer::class, $previewer);

    $component
        ->mountAction('previewPanel')
        ->assertSeeHtml('data-card-template-preview-modal')
        ->assertSee('Editor Preview Episode');

    $slideOverMetrics = app(SettingsSp3cCanaryMeasurement::class)->measureHtml(
        $component->html(),
        [
            'preview' => [
                'status' => $component->get('previewStatus'),
                'family' => $component->get('previewFamily'),
                'sample_id' => $component->get('previewSampleId'),
                'sample_label' => $component->get('previewSampleLabel'),
                'html' => $component->get('previewHtml'),
            ],
        ],
    );

    expect(substr_count($component->html(), 'data-card-template-preview-root'))->toBe(1)
        ->and($slideOverMetrics['wire_models'])->toBeGreaterThan(0);

    if (getenv('STEP5B_CANARY_REPORT') === '1') {
        fwrite(STDERR, json_encode([
            'narrow_slide_over_component_response' => $slideOverMetrics,
            'server_preview_roots' => 1,
            'browser_active_preview_roots' => 1,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES).PHP_EOL);
    }
});

it('refreshes once at the family change boundary and keeps sample selection transient', function (): void {
    $group = ContentGroup::factory()->published()->create(['title' => 'Family Preview Podcast']);
    step5bEditorPublicItem('Family Preview Episode', $group);

    $component = Livewire::withQueryParams(['mode' => 'blank'])
        ->test(CreateCardTemplate::class)
        ->set('data.family', 'content_group');

    expect($component->get('previewStatus'))->toBe('ready')
        ->and($component->get('previewFamily'))->toBe('content_group')
        ->and($component->get('previewSampleId'))->toBe($group->getKey())
        ->and($component->get('previewHtml'))->toContain('data-card-template-family="content_group"');
});

it('auto refreshes part field and structural edits without persisting settings', function (): void {
    $template = step5bEditorTemplate();
    $template['parts'][] = [
        'type' => 'custom_text',
        'source' => 'custom',
        'attribute' => 'text',
        'text' => 'STEP5B PART BEFORE',
        'visible' => true,
        'order' => 100,
        'layout' => 'inline',
    ];
    step5bEditorSaveSetting(PublicContentSettings::class, 'card_templates', [$template]);
    $item = step5bEditorPublicItem('Parts Auto Refresh Episode');
    $component = Livewire::test(EditCardTemplate::class, [
        'family' => 'content_item',
        'key' => 'preview_target',
    ]);
    $parts = $component->instance()->form->getRawState()['parts'];
    $customTextKey = collect($parts)->search(fn (array $part): bool => $part['type'] === 'custom_text');
    $builder = $component->instance()->getSchemaComponent('form.parts');
    $presentationFields = collect(['layout', 'density', 'image_size', 'title_size'])
        ->mapWithKeys(fn (string $field): array => [
            $field => $component->instance()->getSchemaComponent("form.{$field}"),
        ]);

    expect($customTextKey)->not->toBeFalse()
        ->and($builder)->toBeInstanceOf(Builder::class)
        ->and($builder->getStateBindingModifiers())->toBe(['live', 'debounce', 500])
        ->and($presentationFields->every(
            fn ($field): bool => $field instanceof Select && $field->getStateBindingModifiers() === ['live'],
        ))->toBeTrue()
        ->and($component->get('previewHtml'))->toContain('STEP5B PART BEFORE');

    Event::fake([SettingsSaved::class]);
    $writer = Mockery::mock(CardTemplateFocusedWriter::class);
    $writer->shouldNotReceive('create', 'edit', 'delete');
    app()->instance(CardTemplateFocusedWriter::class, $writer);
    $scanner = Mockery::mock(CardTemplateReferenceScanner::class);
    $scanner->shouldNotReceive('scan');
    app()->instance(CardTemplateReferenceScanner::class, $scanner);

    $component
        ->set("data.parts.{$customTextKey}.data.text", 'STEP5B PART AFTER')
        ->assertSet('previewStatus', 'ready')
        ->assertSet('previewSampleId', $item->getKey());

    expect($component->get('previewHtml'))
        ->toContain('STEP5B PART AFTER')
        ->not->toContain('STEP5B PART BEFORE')
        ->and($component->instance()->previewIsStale())->toBeFalse();

    $component->callAction(
        TestAction::make('delete')->schemaComponent('parts', 'form'),
        arguments: ['item' => $customTextKey],
    );

    expect($component->get('previewHtml'))->not->toContain('STEP5B PART AFTER')
        ->and($component->get('previewSampleId'))->toBe($item->getKey())
        ->and($component->instance()->previewIsStale())->toBeFalse();
    Event::assertNotDispatched(SettingsSaved::class);
});

it('hydrates legacy order into position canonical builders with compact native controls', function (): void {
    $template = step5bEditorTemplate();
    $template['parts'] = [
        [
            'type' => 'title',
            'source' => 'content_item',
            'attribute' => 'title',
            'label' => 'Retained title label',
            'label_position' => 'inline_after',
            'icon' => 'title',
            'icon_position' => 'inline_after',
            'visible' => true,
            'order' => 30,
            'layout' => 'inline',
        ],
        [
            'type' => 'custom_text',
            'source' => 'custom',
            'attribute' => 'text',
            'text' => 'Legacy first',
            'visible' => true,
            'order' => 10,
            'layout' => 'inline',
        ],
        [
            'type' => 'description',
            'source' => 'content_item',
            'attribute' => 'description',
            'visible' => true,
            'order' => 20,
            'layout' => 'stacked',
        ],
        [
            'type' => 'part_group',
            'visible' => true,
            'order' => 40,
            'layout' => 'stacked',
            'children' => [
                [
                    'type' => 'custom_text',
                    'source' => 'custom',
                    'attribute' => 'text',
                    'text' => 'Nested second',
                    'visible' => true,
                    'order' => 20,
                    'layout' => 'inline',
                ],
                [
                    'type' => 'custom_text',
                    'source' => 'custom',
                    'attribute' => 'text',
                    'text' => 'Nested first',
                    'visible' => true,
                    'order' => 10,
                    'layout' => 'inline',
                ],
            ],
        ],
    ];
    step5bEditorSaveSetting(PublicContentSettings::class, 'card_templates', [$template]);
    step5bEditorPublicItem('Canonical Builder Episode');

    $component = Livewire::test(EditCardTemplate::class, [
        'family' => 'content_item',
        'key' => 'preview_target',
    ]);
    $builder = $component->instance()->getSchemaComponent('form.parts');
    $parts = $builder->getRawState();
    $customTextKey = collect($parts)->search(fn (array $part): bool => $part['type'] === 'custom_text');
    $titleKey = collect($parts)->search(fn (array $part): bool => $part['type'] === 'title');
    $groupKey = collect($parts)->search(fn (array $part): bool => $part['type'] === 'part_group');
    $titleComponents = collect($builder->getChildSchema($titleKey)->getFlatComponents(withHidden: true));
    $showLabel = $titleComponents->first(fn ($field): bool => $field instanceof Toggle && $field->getName() === '_show_label');
    $showIcon = $titleComponents->first(fn ($field): bool => $field instanceof Toggle && $field->getName() === '_show_icon');
    $labelField = $titleComponents->first(fn ($field): bool => $field instanceof TextInput && $field->getName() === 'label');
    $iconField = $titleComponents->first(fn ($field): bool => method_exists($field, 'getName') && $field->getName() === 'icon');
    $compactComponents = collect($builder->getChildSchema($customTextKey)->getFlatComponents(withHidden: true));
    $compactShowLabel = $compactComponents->first(fn ($field): bool => $field instanceof Toggle && $field->getName() === '_show_label');
    $compactShowIcon = $compactComponents->first(fn ($field): bool => $field instanceof Toggle && $field->getName() === '_show_icon');
    $compactLabelField = $compactComponents->first(fn ($field): bool => $field instanceof TextInput && $field->getName() === 'label');
    $compactIconField = $compactComponents->first(fn ($field): bool => method_exists($field, 'getName') && $field->getName() === 'icon');
    $nestedBuilder = collect($builder->getChildSchema($groupKey)->getFlatComponents(withHidden: true))
        ->first(fn ($field): bool => $field instanceof Builder && $field->getName() === 'children');
    $moveAction = $builder->getExtraItemActions()['moveToPosition'] ?? null;

    expect(array_column(array_values($parts), 'type'))
        ->toBe(['custom_text', 'description', 'title', 'part_group'])
        ->and(collect($parts)->every(fn (array $part): bool => ! array_key_exists('order', $part['data'])))->toBeTrue()
        ->and(array_column(array_values($nestedBuilder->getRawState()), 'type'))
        ->toBe(['custom_text', 'custom_text'])
        ->and(array_values($nestedBuilder->getRawState())[0]['data']['text'])->toBe('Nested first')
        ->and($builder->hasBlockNumbers())->toBeFalse()
        ->and($nestedBuilder->hasBlockNumbers())->toBeFalse()
        ->and($builder->isCollapsible())->toBeFalse()
        ->and($moveAction)->not->toBeNull()
        ->and($moveAction->getModalWidth())->toBe(Width::ExtraSmall)
        ->and($titleComponents->contains(fn ($field): bool => method_exists($field, 'getName') && $field->getName() === 'order'))->toBeFalse()
        ->and($showLabel)->toBeInstanceOf(Toggle::class)
        ->and($showLabel->isLive())->toBeTrue()
        ->and($showLabel->isDehydrated())->toBeFalse()
        ->and($showIcon)->toBeInstanceOf(Toggle::class)
        ->and($showIcon->isLive())->toBeTrue()
        ->and($showIcon->isDehydrated())->toBeFalse()
        ->and($labelField->isVisible())->toBeTrue()
        ->and($iconField->isVisible())->toBeTrue()
        ->and($compactShowLabel)->toBeInstanceOf(Toggle::class)
        ->and($compactShowIcon)->toBeInstanceOf(Toggle::class)
        ->and($compactLabelField->isVisible())->toBeFalse()
        ->and($compactIconField->isVisible())->toBeFalse();

    $component
        ->assertSee(__('admin.settings_sp3c.editor.template_settings_heading'))
        ->assertSee(__('admin.settings_sp3c.editor.parts_heading'))
        ->assertSeeHtml('data-sp3c-part-position-badge')
        ->set("data.parts.{$titleKey}.data._show_label", false)
        ->assertSet("data.parts.{$titleKey}.data.label", 'Retained title label')
        ->assertSet("data.parts.{$titleKey}.data.label_position", 'hidden')
        ->set("data.parts.{$titleKey}.data._show_icon", false)
        ->assertSet("data.parts.{$titleKey}.data.icon", 'title')
        ->assertSet("data.parts.{$titleKey}.data.icon_position", 'hidden');

    $builder = $component->instance()->getSchemaComponent('form.parts');
    $titleComponents = collect($builder->getChildSchema($titleKey)->getFlatComponents(withHidden: true));
    $labelField = $titleComponents->first(fn ($field): bool => $field instanceof TextInput && $field->getName() === 'label');
    $iconField = $titleComponents->first(fn ($field): bool => method_exists($field, 'getName') && $field->getName() === 'icon');
    expect($labelField->isVisible())->toBeFalse()
        ->and($iconField->isVisible())->toBeFalse();

    $component
        ->set("data.parts.{$titleKey}.data._show_label", true)
        ->assertSet("data.parts.{$titleKey}.data.label", 'Retained title label')
        ->assertSet("data.parts.{$titleKey}.data.label_position", 'inline_before')
        ->set("data.parts.{$titleKey}.data._show_icon", true)
        ->assertSet("data.parts.{$titleKey}.data.icon", 'title')
        ->assertSet("data.parts.{$titleKey}.data.icon_position", 'inline_before')
        ->call('setBuilderDisplayMode', 'inline');

    $builder = $component->instance()->getSchemaComponent('form.parts');
    $groupKey = collect($builder->getRawState())->search(fn (array $part): bool => $part['type'] === 'part_group');
    $nestedBuilder = collect($builder->getChildSchema($groupKey)->getFlatComponents(withHidden: true))
        ->first(fn ($field): bool => $field instanceof Builder && $field->getName() === 'children');

    expect($builder->isCollapsible())->toBeTrue()
        ->and($nestedBuilder->isCollapsible())->toBeTrue();
});

it('moves top-level and nested parts through their owning native builder actions', function (): void {
    $template = step5bEditorTemplate();
    $template['parts'] = [
        [
            'type' => 'custom_text',
            'source' => 'custom',
            'attribute' => 'text',
            'text' => 'Move A',
            'visible' => true,
            'order' => 10,
            'layout' => 'inline',
        ],
        [
            'type' => 'custom_text',
            'source' => 'custom',
            'attribute' => 'text',
            'text' => 'Move B',
            'visible' => true,
            'order' => 20,
            'layout' => 'inline',
        ],
        [
            'type' => 'part_group',
            'visible' => true,
            'order' => 30,
            'layout' => 'stacked',
            'children' => [
                [
                    'type' => 'custom_text',
                    'source' => 'custom',
                    'attribute' => 'text',
                    'text' => 'Child A',
                    'visible' => true,
                    'order' => 10,
                    'layout' => 'inline',
                ],
                [
                    'type' => 'custom_text',
                    'source' => 'custom',
                    'attribute' => 'text',
                    'text' => 'Child B',
                    'visible' => true,
                    'order' => 20,
                    'layout' => 'inline',
                ],
            ],
        ],
    ];
    step5bEditorSaveSetting(PublicContentSettings::class, 'card_templates', [$template]);
    $item = step5bEditorPublicItem('Scoped Move Episode');
    $component = Livewire::test(EditCardTemplate::class, [
        'family' => 'content_item',
        'key' => 'preview_target',
    ]);
    $builder = $component->instance()->getSchemaComponent('form.parts');
    $originalKeys = array_keys($builder->getRawState());
    $move = TestAction::make('moveToPosition')->schemaComponent('parts', 'form');
    $preview = [
        'family' => $component->get('previewFamily'),
        'sample_id' => $component->get('previewSampleId'),
        'sample_label' => $component->get('previewSampleLabel'),
        'html' => $component->get('previewHtml'),
    ];
    $previewer = Mockery::mock(CardTemplatePreviewer::class);
    $previewer->shouldReceive('preview')
        ->once()
        ->with(Mockery::type('array'), $item->getKey())
        ->andReturn($preview);
    $previewer->shouldReceive('initialSampleOptions')
        ->andReturn([$item->getKey() => $component->get('previewSampleLabel')]);
    app()->instance(CardTemplatePreviewer::class, $previewer);

    Event::fake([SettingsSaved::class]);
    $component
        ->mountAction($move, ['item' => $originalKeys[0]])
        ->assertSchemaStateSet(['position' => 1])
        ->fillForm(['position' => 3])
        ->callMountedAction();

    $builder = $component->instance()->getSchemaComponent('form.parts');
    expect(array_keys($builder->getRawState()))
        ->toBe([$originalKeys[1], $originalKeys[2], $originalKeys[0]])
        ->and(array_diff($originalKeys, array_keys($builder->getRawState())))->toBe([]);
    Event::assertNotDispatched(SettingsSaved::class);
    app()->forgetInstance(CardTemplatePreviewer::class);

    $component->callAction($move, data: ['position' => 99], arguments: ['item' => $originalKeys[1]]);
    $builder = $component->instance()->getSchemaComponent('form.parts');
    expect(array_keys($builder->getRawState()))
        ->toBe([$originalKeys[2], $originalKeys[0], $originalKeys[1]]);

    $component->callAction($move, data: ['position' => -5], arguments: ['item' => $originalKeys[1]]);
    $builder = $component->instance()->getSchemaComponent('form.parts');
    expect(array_keys($builder->getRawState()))
        ->toBe([$originalKeys[1], $originalKeys[2], $originalKeys[0]]);

    $groupKey = collect($builder->getRawState())->search(fn (array $part): bool => $part['type'] === 'part_group');
    $nestedBuilder = collect($builder->getChildSchema($groupKey)->getFlatComponents(withHidden: true))
        ->first(fn ($field): bool => $field instanceof Builder && $field->getName() === 'children');
    $childKeys = array_keys($nestedBuilder->getRawState());
    $nestedMove = TestAction::make('moveToPosition')->schemaComponent(
        str($nestedBuilder->getKey())->after('form.')->toString(),
        'form',
    );

    $component->callAction($nestedMove, data: ['position' => 2], arguments: ['item' => $childKeys[0]]);

    $builder = $component->instance()->getSchemaComponent('form.parts');
    $groupKey = collect($builder->getRawState())->search(fn (array $part): bool => $part['type'] === 'part_group');
    $nestedBuilder = collect($builder->getChildSchema($groupKey)->getFlatComponents(withHidden: true))
        ->first(fn ($field): bool => $field instanceof Builder && $field->getName() === 'children');

    $noOpPreviewer = Mockery::mock(CardTemplatePreviewer::class);
    $noOpPreviewer->shouldNotReceive('preview');
    $noOpPreviewer->shouldReceive('initialSampleOptions')
        ->andReturn([$item->getKey() => $component->get('previewSampleLabel')]);
    app()->instance(CardTemplatePreviewer::class, $noOpPreviewer);
    $component->callAction($move, data: ['position' => 1], arguments: ['item' => $originalKeys[1]]);
    app()->forgetInstance(CardTemplatePreviewer::class);

    $component
        ->callAction($move, data: ['position' => 'not-a-number'], arguments: ['item' => $originalKeys[1]])
        ->assertHasFormErrors(['position' => 'numeric'])
        ->unmountAction();

    $candidate = app(CardTemplateDraftNormalizer::class)->candidate($component->get('data'));

    expect(array_keys($nestedBuilder->getRawState()))->toBe([$childKeys[1], $childKeys[0]])
        ->and(array_keys($builder->getRawState()))->toBe([$originalKeys[1], $originalKeys[2], $originalKeys[0]])
        ->and(array_column(array_map(fn (array $part): array => $part['data'], $candidate['parts']), 'order'))
        ->toBe([10, 20, 30])
        ->and(array_column(array_map(
            fn (array $part): array => $part['data'],
            $candidate['parts'][1]['data']['children'],
        ), 'order'))->toBe([10, 20]);
    Event::assertNotDispatched(SettingsSaved::class);
});

it('renders a natively moved image at its exact preview position', function (): void {
    $template = step5bEditorTemplate();
    $template['layout'] = 'rows';
    $template['image_size'] = 'small';
    $template['parts'] = [
        [
            'type' => 'image',
            'source' => 'content_item',
            'attribute' => 'image',
            'visible' => true,
            'order' => 10,
        ],
        [
            'type' => 'custom_text',
            'source' => 'custom',
            'attribute' => 'text',
            'text' => 'FU01 preview before image',
            'visible' => true,
            'order' => 20,
        ],
        [
            'type' => 'title',
            'source' => 'content_item',
            'attribute' => 'title',
            'visible' => true,
            'order' => 30,
        ],
    ];
    step5bEditorSaveSetting(PublicContentSettings::class, 'card_templates', [$template]);
    step5bEditorPublicItem('FU01 Preview Ordered Item');
    $component = Livewire::test(EditCardTemplate::class, [
        'family' => 'content_item',
        'key' => 'preview_target',
    ]);
    $builder = $component->instance()->getSchemaComponent('form.parts');
    $parts = $builder->getRawState();
    $imageKey = collect($parts)->search(fn (array $part): bool => $part['type'] === 'image');

    expect($imageKey)->not->toBeFalse();

    $component->callAction(
        TestAction::make('moveToPosition')->schemaComponent('parts', 'form'),
        data: ['position' => 2],
        arguments: ['item' => $imageKey],
    );

    $html = $component->get('previewHtml');
    $beforePosition = strpos($html, 'FU01 preview before image');
    $imagePosition = strpos($html, 'data-test="content-item-image"');
    $titlePosition = strpos($html, 'data-card-part="title"');

    expect($html)
        ->toContain('data-card-template-layout="rows"')
        ->toContain('data-result-layout="cards"')
        ->toContain('data-card-part-flow="ordered-stack"')
        ->toContain('data-card-image-source="fallback"')
        ->and($beforePosition)->not->toBeFalse()
        ->and($imagePosition)->not->toBeFalse()
        ->and($titlePosition)->not->toBeFalse()
        ->and($beforePosition)->toBeLessThan($imagePosition)
        ->and($imagePosition)->toBeLessThan($titlePosition);
});

it('auto refreshes rendered presentation fields but leaves identity-only fields stale', function (): void {
    $template = step5bEditorTemplate();
    step5bEditorSaveSetting(PublicContentSettings::class, 'card_templates', [$template]);
    step5bEditorPublicItem('Presentation Refresh Episode');
    $component = Livewire::test(EditCardTemplate::class, [
        'family' => 'content_item',
        'key' => 'preview_target',
    ]);

    Event::fake([SettingsSaved::class]);
    $writer = Mockery::mock(CardTemplateFocusedWriter::class);
    $writer->shouldNotReceive('create', 'edit', 'delete');
    app()->instance(CardTemplateFocusedWriter::class, $writer);
    $scanner = Mockery::mock(CardTemplateReferenceScanner::class);
    $scanner->shouldNotReceive('scan');
    app()->instance(CardTemplateReferenceScanner::class, $scanner);

    $component
        ->set('data.layout', 'rows')
        ->set('data.density', 'compact')
        ->set('data.image_size', 'small')
        ->set('data.title_size', 'lg');

    expect($component->get('previewHtml'))
        ->toContain('data-result-layout="rows"')
        ->toContain('data-card-density="compact"')
        ->toContain('data-card-image-size="small"')
        ->toContain('data-card-title-size="lg"')
        ->and($component->instance()->previewIsStale())->toBeFalse();

    $freshHash = $component->get('previewDraftHash');
    $component
        ->set('data.key', 'identity_only_key')
        ->set('data.label', 'Identity-only label');

    expect($component->get('previewDraftHash'))->toBe($freshHash)
        ->and($component->instance()->previewIsStale())->toBeTrue();
    Event::assertNotDispatched(SettingsSaved::class);
});

it('configures remembered builder modes without server persistence and keeps native slide-over apply timing', function (): void {
    $template = step5bEditorTemplate();
    $template['parts'][] = [
        'type' => 'part_group',
        'visible' => true,
        'order' => 100,
        'layout' => 'stacked',
        'children' => [[
            'type' => 'custom_text',
            'source' => 'custom',
            'attribute' => 'text',
            'text' => 'Nested mode state',
            'visible' => true,
            'order' => 0,
            'layout' => 'inline',
        ]],
    ];
    step5bEditorSaveSetting(PublicContentSettings::class, 'card_templates', [$template]);
    step5bEditorPublicItem('Builder Mode Episode');
    $component = Livewire::test(EditCardTemplate::class, [
        'family' => 'content_item',
        'key' => 'preview_target',
    ]);
    $builder = $component->instance()->getSchemaComponent('form.parts');
    $groupKey = collect($builder->getRawState())
        ->search(fn (array $part): bool => $part['type'] === 'part_group');
    $nestedBuilder = collect($builder->getChildSchema($groupKey)->getComponents())
        ->first(fn ($field): bool => $field instanceof Builder && $field->getName() === 'children');
    $editAction = $builder->getEditAction();

    expect($component->get('builderDisplayMode'))->toBe('slide_over')
        ->and($builder)->toBeInstanceOf(Builder::class)
        ->and($builder->hasBlockPreviews())->toBeTrue()
        ->and($builder->getBlock('custom_text')->getColumns())->toBe(['default' => 1, 'lg' => 2])
        ->and($nestedBuilder)->toBeInstanceOf(Builder::class)
        ->and($nestedBuilder->hasBlockPreviews())->toBeTrue()
        ->and($nestedBuilder->getEditAction()->isModalSlideOver())->toBeTrue()
        ->and($nestedBuilder->getEditAction()->getModalSlideOverPosition())->toBe(SlideOverPosition::Start)
        ->and($editAction->isModalSlideOver())->toBeTrue()
        ->and($editAction->getModalSlideOverPosition())->toBe(SlideOverPosition::Start)
        ->and($editAction->getModalWidth())->toBe(Width::ThreeExtraLarge)
        ->and($editAction->isModalHeaderSticky())->toBeTrue()
        ->and($editAction->isModalFooterSticky())->toBeTrue();

    Event::fake([SettingsSaved::class]);
    $component->call('setBuilderDisplayMode', 'inline');
    $builder = $component->instance()->getSchemaComponent('form.parts');
    $groupKey = collect($builder->getRawState())
        ->search(fn (array $part): bool => $part['type'] === 'part_group');
    $nestedBuilder = collect($builder->getChildSchema($groupKey)->getComponents())
        ->first(fn ($field): bool => $field instanceof Builder && $field->getName() === 'children');

    expect($component->get('builderDisplayMode'))->toBe('inline')
        ->and($builder->hasBlockPreviews())->toBeFalse()
        ->and($nestedBuilder->hasBlockPreviews())->toBeFalse();

    $component->call('setBuilderDisplayMode', 'forged');
    $builder = $component->instance()->getSchemaComponent('form.parts');

    expect($component->get('builderDisplayMode'))->toBe('slide_over')
        ->and($builder->hasBlockPreviews())->toBeTrue();
    Event::assertNotDispatched(SettingsSaved::class);
});

it('places localized cancel beside save instead of in the header', function (): void {
    $template = step5bEditorTemplate();
    step5bEditorSaveSetting(PublicContentSettings::class, 'card_templates', [$template]);

    foreach (['he' => 'ביטול', 'en' => 'Cancel'] as $locale => $expectedLabel) {
        app()->setLocale($locale);
        $page = Livewire::test(EditCardTemplate::class, [
            'family' => 'content_item',
            'key' => 'preview_target',
        ])->instance();
        $formActions = collect($page->getFormActions());
        $headerActions = collect($page->getCachedHeaderActions());
        $previewAction = $headerActions->first(fn ($action): bool => $action->getName() === 'previewPanel');

        expect($formActions->map->getName()->all())->toBe(['save', 'cancel'])
            ->and($headerActions->map->getName()->all())->toBe(['previewPanel', 'deleteTemplate'])
            ->and($previewAction->getExtraAttributes()['class'] ?? null)->toBe('lg:hidden')
            ->and($previewAction->isModalSlideOver())->toBeTrue()
            ->and($formActions->first(fn ($action): bool => $action->getName() === 'cancel')->getLabel())
            ->toBe($expectedLabel)
            ->and($formActions->first(fn ($action): bool => $action->getName() === 'cancel')->getUrl())
            ->toBe(CardTemplateSettings::getUrl());
    }
});

it('shows invalid and family-specific empty states without falling back to a stored template', function (): void {
    $template = step5bEditorTemplate();
    step5bEditorSaveSetting(PublicContentSettings::class, 'card_templates', [$template]);

    $component = Livewire::test(EditCardTemplate::class, [
        'family' => 'content_item',
        'key' => 'preview_target',
    ])->assertSet('previewStatus', 'no_sample');

    expect($component->instance()->previewEmptyMessage())
        ->toBe(__('admin.settings_sp3c.preview.empty_content_item'));

    $component
        ->set('data.label', str_repeat('x', 241))
        ->call('refreshPreview')
        ->assertSet('previewStatus', 'invalid_draft')
        ->assertSet('previewHtml', null);
});

it('never fetches or serializes protected parts for a restricted preview shell', function (): void {
    $author = Author::factory()->create(['name' => 'Restricted Preview Contributor']);
    $group = ContentGroup::factory()->published()->create(['title' => 'Restricted Preview Group']);
    $item = step5bEditorPublicItem('Restricted Preview Item', $group, $author);
    $template = step5bEditorTemplate();
    $template['parts'] = [[
        'type' => 'metadata_row',
        'source' => 'content_item',
        'attribute' => 'transcription_count',
        'label' => 'STEP5B-PROTECTED-SECRET',
        'visible' => true,
        'order' => 10,
        'layout' => 'badge',
    ]];
    step5bEditorSaveSetting(PublicContentSettings::class, 'card_templates', [$template]);
    step5bEditorSaveSetting(AdminUxSettings::class, 'transcription_mode', TranscriptionMode::Multi->value);
    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        $queries[] = $query->sql;
    });

    $component = Livewire::test(EditCardTemplate::class, [
        'family' => 'content_item',
        'key' => 'preview_target',
    ])
        ->assertSet('previewStatus', 'restricted')
        ->assertSet('previewHtml', null)
        ->assertDontSee('STEP5B-PROTECTED-SECRET', escape: false);

    $state = json_encode([
        'data' => $component->get('data'),
        'preview_controls' => $component->get('previewControls'),
    ], JSON_THROW_ON_ERROR);

    $queries = [];

    $component
        ->assertDontSee(__('admin.settings_sp3c.preview.choose_sample'))
        ->assertDontSeeHtml('data-card-template-preview-sample-select')
        ->call('selectPreviewSample', $item->getKey())
        ->assertSet('previewControls.sample_id', null)
        ->call('callSchemaComponentMethod', 'previewSampleForm.sample_id', 'getSearchResultsForJs', ['Restricted'])
        ->call('callSchemaComponentMethod', 'previewSampleForm.sample_id', 'getOptionLabel');

    expect($state)->not->toContain('STEP5B-PROTECTED-SECRET')
        ->and($component->instance()->getSchema('previewSampleForm')->getComponents())->toBe([])
        ->and(collect($queries)->contains(fn (string $sql): bool => str_contains($sql, 'from "content_items"')))->toBeFalse()
        ->and(collect($queries)->contains(fn (string $sql): bool => str_contains($sql, 'from "content_groups"')))->toBeFalse()
        ->and(collect($queries)->contains(fn (string $sql): bool => str_contains($sql, 'from "authors"')))->toBeFalse();
});

it('keeps authorized sample selector search label selection and refresh working for every family', function (): void {
    $author = Author::factory()->create(['name' => 'Authorized Preview Contributor']);
    $group = ContentGroup::factory()->published()->create(['title' => 'Authorized Preview Group']);
    $item = step5bEditorPublicItem('Authorized Preview Item', $group, $author);
    $samples = [
        'content_item' => [
            'id' => $item->getKey(),
            'search' => 'Authorized Preview Item',
            'label' => __('admin.settings_sp3c.preview.sample_item_label', [
                'title' => $item->title,
                'group' => $group->title,
            ]),
        ],
        'content_group' => [
            'id' => $group->getKey(),
            'search' => 'Authorized Preview Group',
            'label' => $group->title,
        ],
        'contributor' => [
            'id' => $author->getKey(),
            'search' => 'Authorized Preview Contributor',
            'label' => $author->name,
        ],
    ];

    foreach ($samples as $family => $sample) {
        $template = step5bEditorTemplate($family, "preview_{$family}");
        step5bEditorSaveSetting(PublicContentSettings::class, 'card_templates', [$template]);

        $component = Livewire::test(EditCardTemplate::class, [
            'family' => $family,
            'key' => "preview_{$family}",
        ])
            ->assertSet('previewStatus', 'ready')
            ->assertSeeHtml('data-card-template-preview-sample-select');

        $select = $component->instance()->getSchemaComponent('previewSampleForm.sample_id');

        expect($select)->toBeInstanceOf(Select::class)
            ->and($select->getOptionsLimit())->toBe(CardTemplatePreviewer::SAMPLE_LIMIT)
            ->and($select->getOptions())->toHaveKey($sample['id'])
            ->and($select->getSearchResults($sample['search']))->toHaveKey($sample['id']);

        $component->set('previewControls.sample_id', $sample['id']);
        $select = $component->instance()->getSchemaComponent('previewSampleForm.sample_id');
        DB::enableQueryLog();
        DB::flushQueryLog();
        $selectedLabel = $select->getOptionLabel();
        $selectedLabelQueryCount = count(DB::getQueryLog());

        expect($select)->toBeInstanceOf(Select::class)
            ->and($selectedLabel)->toBe($sample['label'])
            ->and($selectedLabelQueryCount)->toBe(0);

        $component
            ->assertSet('previewStatus', 'ready')
            ->assertSet('previewFamily', $family)
            ->assertSet('previewSampleId', $sample['id']);
    }
});

it('caps authorized sample selector searches at fifty results', function (): void {
    foreach (range(1, 51) as $index) {
        $group = ContentGroup::factory()->published()->create([
            'title' => sprintf('Authorized Selector Group %02d', $index),
        ]);
        step5bEditorPublicItem("Authorized Selector Item {$index}", $group);
    }

    $template = step5bEditorTemplate('content_group', 'preview_content_group');
    step5bEditorSaveSetting(PublicContentSettings::class, 'card_templates', [$template]);

    $component = Livewire::test(EditCardTemplate::class, [
        'family' => 'content_group',
        'key' => 'preview_content_group',
    ]);
    $select = $component->instance()->getSchemaComponent('previewSampleForm.sample_id');

    DB::enableQueryLog();
    DB::flushQueryLog();
    $preloaded = $select->getOptions();
    $preloadQueryCount = count(DB::getQueryLog());
    DB::flushQueryLog();
    $searched = $select->getSearchResults('Authorized Selector Group');
    $searchQueryCount = count(DB::getQueryLog());

    expect($select)->toBeInstanceOf(Select::class)
        ->and($select->getOptionsLimit())->toBe(CardTemplatePreviewer::SAMPLE_LIMIT)
        ->and($preloaded)->toHaveCount(CardTemplatePreviewer::SAMPLE_PRELOAD_LIMIT)
        ->and($searched)->toHaveCount(CardTemplatePreviewer::SAMPLE_LIMIT)
        ->and($preloadQueryCount)->toBe(2)
        ->and($searchQueryCount)->toBe(2);
});

it('localizes builder summaries and preserves escaped diagnostics for unknown values', function (): void {
    $formatter = app(CardTemplatePartSummaryFormatter::class);

    foreach (['en', 'he'] as $locale) {
        app()->setLocale($locale);
        $known = $formatter->summarize([
            'source' => 'content_item',
            'attribute' => 'title',
        ]);
        $unknown = $formatter->summarize([
            'source' => '<legacy-source>',
            'attribute' => '<legacy-attribute>',
        ]);

        expect($known['title'])->toBe(__('admin.settings_sp3c.editor.unlabelled_part'))
            ->and($known['title'])->not->toContain($locale === 'he' ? 'חלק' : 'Part')
            ->and($known['context'])->toBe(__('admin.settings_sp3c.editor.part_source', [
                'source' => PublicFrontConfigRegistry::cardSourceOptions()['content_item'],
                'attribute' => PublicFrontConfigRegistry::cardAttributeOptions('content_item')['title'],
            ]))
            ->and($unknown['context'])->toContain('<legacy-source>')
            ->toContain('<legacy-attribute>');
    }

    $html = view('filament.card-templates.part-summary', [
        'source' => '<script>alert(1)</script>',
        'attribute' => '<img src=x onerror=alert(1)>',
    ])->render();

    expect($html)
        ->not->toContain('<script>alert(1)</script>')
        ->not->toContain('<img src=x onerror=alert(1)>')
        ->toContain('&lt;script&gt;alert(1)&lt;/script&gt;');
});
