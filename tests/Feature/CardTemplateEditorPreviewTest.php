<?php

use App\Enums\TranscriptionMode;
use App\Filament\Pages\CreateCardTemplate;
use App\Filament\Pages\EditCardTemplate;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\Transcription;
use App\Models\User;
use App\Settings\AdminUxSettings;
use App\Settings\PublicContentSettings;
use App\Support\PublicFront\Cards\PublicFrontCardTemplateRegistry;
use App\Support\Settings\CardTemplates\CardTemplateFocusedWriter;
use App\Support\Settings\CardTemplates\CardTemplatePreviewer;
use App\Support\Settings\CardTemplates\CardTemplateReferenceScanner;
use Filament\Facades\Filament;
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

function step5bEditorPublicItem(string $title, ?ContentGroup $group = null): ContentItem
{
    $group ??= ContentGroup::factory()->published()->create();
    $item = ContentItem::factory()->for($group)->published()->create(['title' => $title]);
    $transcription = Transcription::factory()
        ->for($item)
        ->published(now()->subMinute())
        ->create(['title' => $title]);
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
        ->and($component->instance()->previewIsStale())->toBeFalse();

    $component->set('data.title_size', 'lg');
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
        ->call('refreshPreview')
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

    $state = json_encode($component->get('data'), JSON_THROW_ON_ERROR);

    expect($state)->not->toContain('STEP5B-PROTECTED-SECRET')
        ->and(collect($queries)->contains(fn (string $sql): bool => str_contains($sql, 'from "content_items"')))->toBeFalse()
        ->and(collect($queries)->contains(fn (string $sql): bool => str_contains($sql, 'from "authors"')))->toBeFalse();
});
