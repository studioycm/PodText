<?php

use App\Enums\HomepageSectionType;
use App\Enums\TranscriptionMode;
use App\Enums\UserRole;
use App\Filament\Pages\CardTemplateSettings;
use App\Filament\Pages\CreateCardTemplate;
use App\Filament\Pages\EditCardTemplate;
use App\Models\HomepageSection;
use App\Models\User;
use App\Settings\AdminUxSettings;
use App\Settings\PublicContentSettings;
use App\Support\PublicFront\Cards\PublicFrontCardTemplateRegistry;
use App\Support\PublicFront\PublicFrontConfigCache;
use App\Support\PublicFront\PublicFrontConfigRegistry;
use App\Support\Settings\CardTemplates\CardTemplateFocusedWriter;
use App\Support\Settings\CardTemplates\CardTemplateIdentity;
use App\Support\Settings\CardTemplates\CardTemplateLibraryProjector;
use App\Support\Settings\CardTemplates\CardTemplateReferenceScanner;
use App\Support\Settings\CardTemplates\CardTemplateWriteException;
use App\Support\SettingsLifecycle\SettingsBackupManager;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Psr\Log\LoggerInterface;
use Spatie\LaravelSettings\Events\SettingsSaved;
use Spatie\LaravelSettings\SettingsContainer;
use Tests\Support\SettingsSp3cCanaryMeasurement;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    fakeSettingsBackupSnapshotQueue();
    Http::preventStrayRequests();
    Mail::fake();
    $this->actingAs(User::factory()->admin()->create());
});

function settingsSp3cForgetState(): void
{
    app()->forgetInstance(PublicContentSettings::class);
    app()->forgetInstance(AdminUxSettings::class);
    app(SettingsContainer::class)->clearCache();
}

function settingsSp3cSave(string $settingsClass, string $name, mixed $value): void
{
    DB::table('settings')->updateOrInsert(
        [
            'group' => $settingsClass::group(),
            'name' => $name,
        ],
        [
            'locked' => false,
            'payload' => json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_at' => now(),
            'updated_at' => now(),
        ],
    );

    settingsSp3cForgetState();
}

/**
 * @param  array<int, array<string, mixed>>|null  $parts
 * @return array<string, mixed>
 */
function settingsSp3cTemplate(string $key, ?array $parts = null, string $family = 'content_item'): array
{
    $source = match ($family) {
        'content_group' => 'content_group',
        'contributor' => 'author',
        default => 'content_item',
    };
    $attribute = $family === 'contributor' ? 'name' : 'title';

    return [
        'key' => $key,
        'label' => "Template {$key}",
        'family' => $family,
        'layout' => 'cards',
        'density' => 'comfortable',
        'image_size' => 'medium',
        'title_size' => 'base',
        'parts' => $parts ?? [[
            'type' => 'title',
            'source' => $source,
            'attribute' => $attribute,
            'visible' => true,
            'order' => 10,
            'layout' => 'inline',
        ]],
    ];
}

function settingsSp3cProtectedTemplate(string $key, string $sentinel = 'PROTECTED-SP3C-TOKEN'): array
{
    return settingsSp3cTemplate($key, [[
        'type' => 'metadata_row',
        'source' => 'content_item',
        'attribute' => 'transcription_count',
        'label' => $sentinel,
        'visible' => true,
        'order' => 10,
        'layout' => 'badge',
    ]]);
}

function settingsSp3cSnapshot(): array
{
    settingsSp3cForgetState();
    $settings = app(PublicContentSettings::class);
    $settings->refresh();

    return $settings->toArray();
}

it('projects a read-only library with configured virtual restricted and diagnostic records', function (): void {
    $ordinary = settingsSp3cTemplate('ordinary');
    $ordinary['label'] = 'Ordinary & "quoted"';
    $protected = settingsSp3cProtectedTemplate('protected');
    $corrupt = ['family' => 'content_item', 'key' => 'broken', 'parts' => 'not-a-list'];
    settingsSp3cSave(PublicContentSettings::class, 'card_templates', [$ordinary, $protected, $corrupt]);

    HomepageSection::factory()->create([
        'name' => 'מדור בלתי נראה <b>',
        'type' => HomepageSectionType::Latest,
        'is_visible' => false,
        'display_config' => [
            'template_family' => 'content_item',
            'template_key' => 'ordinary',
        ],
    ]);

    $projection = app(CardTemplateLibraryProjector::class)->project(settingsSp3cSnapshot());
    $records = collect($projection->records)->keyBy('record_key');
    $serialized = json_encode($projection->records, JSON_THROW_ON_ERROR);

    expect($records)->toHaveKey('configured:content_item:ordinary')
        ->toHaveKey('configured:content_item:protected')
        ->toHaveKey('virtual:content_item:default_content_item')
        ->and($records->keys()->filter(fn (string $key): bool => str_starts_with($key, 'corrupt:2:')))->toHaveCount(1)
        ->and($records['configured:content_item:protected']['parts_status'])->toBe(__('admin.settings_sp3c.library.restricted'))
        ->and($records['configured:content_item:ordinary']['explicit_references'])->toBe(1)
        ->and($serialized)->not->toContain('PROTECTED-SP3C-TOKEN')
        ->and($serialized)->not->toContain('"parts"');

    Livewire::test(CardTemplateSettings::class)
        ->assertSeeHtml('Ordinary &amp; &quot;quoted&quot;')
        ->assertSee(__('admin.settings_sp3c.library.restricted'))
        ->assertDontSee('PROTECTED-SP3C-TOKEN', escape: false);
});

it('represents each registry default exactly once and keeps the library unpaginated and non writable', function (): void {
    $default = PublicFrontCardTemplateRegistry::defaultTemplateForFamily('content_item');
    $ordinary = settingsSp3cTemplate('ordinary');
    settingsSp3cSave(PublicContentSettings::class, 'card_templates', [$ordinary, $default]);
    Event::fake([SettingsSaved::class]);

    $projection = app(CardTemplateLibraryProjector::class)->project(settingsSp3cSnapshot());
    $defaultRows = collect($projection->records)
        ->where('identity', 'content_item:default_content_item')
        ->values();
    $component = Livewire::test(CardTemplateSettings::class);
    $table = $component->instance()->getTable();

    expect($defaultRows)->toHaveCount(1)
        ->and($defaultRows[0]['record_key'])->toBe('configured:content_item:default_content_item')
        ->and($defaultRows[0]['default_override'])->toBeTrue()
        ->and($table->isPaginated())->toBeFalse()
        ->and($table->isReorderable())->toBeFalse()
        ->and($table->getBulkActions())->toBe([])
        ->and(property_exists($component->instance(), 'data'))->toBeFalse();

    $component
        ->assertActionVisible(TestAction::make('createTemplate')->table())
        ->assertActionVisible(TestAction::make('editTemplate')->table('configured:content_item:ordinary'))
        ->assertActionVisible(TestAction::make('cloneTemplate')->table('configured:content_item:ordinary'))
        ->assertActionHidden(TestAction::make('createOverride')->table('configured:content_item:ordinary'));

    $component->searchTable('ordinary');
    expect($component->instance()->getTableRecords()->keys()->all())
        ->toBe(['configured:content_item:ordinary']);
    $component->searchTable('')->filterTable('default_override', true);
    expect($component->instance()->getTableRecords()->keys()->all())
        ->toBe(['configured:content_item:default_content_item']);
    $component->resetTableFilters();

    $records = $table->getRecords();
    $editAction = (clone $table->getAction('editTemplate'))
        ->record($records->get('configured:content_item:ordinary'));
    $cloneAction = (clone $table->getAction('cloneTemplate'))
        ->record($records->get('configured:content_item:ordinary'));
    $overrideAction = (clone $table->getAction('createOverride'))
        ->record($records->get('virtual:content_group:default_content_group'));

    expect($table->getAction('createTemplate')->getUrl())->toBe(CreateCardTemplate::getUrl(['mode' => 'blank']))
        ->and($editAction->getUrl())->toBe(EditCardTemplate::getUrl(['family' => 'content_item', 'key' => 'ordinary']))
        ->and($cloneAction->getUrl())->toBe(CreateCardTemplate::getUrl([
            'mode' => 'clone',
            'family' => 'content_item',
            'key' => 'ordinary',
        ]))
        ->and($overrideAction->getUrl())->toBe(CreateCardTemplate::getUrl([
            'mode' => 'override',
            'family' => 'content_group',
            'key' => 'default_content_group',
        ]));

    Event::assertNotDispatched(SettingsSaved::class);
});

it('projects every duplicate and malformed stored row as a non editable diagnostic without normalization', function (): void {
    $duplicate = settingsSp3cTemplate('duplicate');
    $malformed = ['family' => 'content_item', 'key' => 'malformed', 'parts' => 'broken'];
    $stored = [$duplicate, $duplicate, $malformed];
    settingsSp3cSave(PublicContentSettings::class, 'card_templates', $stored);

    $projection = app(CardTemplateLibraryProjector::class)->project(settingsSp3cSnapshot());
    $diagnostics = collect($projection->records)->where('kind', 'diagnostic')->values();

    expect($diagnostics)->toHaveCount(3)
        ->and($diagnostics->where('diagnostic_reason', 'duplicate'))->toHaveCount(2)
        ->and($diagnostics->where('diagnostic_reason', 'malformed'))->toHaveCount(1)
        ->and(collect($projection->records)->where('record_key', 'configured:content_item:duplicate'))->toBeEmpty()
        ->and(settingsSp3cSnapshot()['card_templates'])->toBe($stored);
});

it('scans every homepage section in one projected query with fallback ambiguity and deterministic order', function (): void {
    settingsSp3cSave(PublicContentSettings::class, 'podcasts_page', [
        ...PublicFrontConfigRegistry::defaults()['podcasts_page'],
        'template_key' => 'groups_explicit',
        'item_template_key' => 'items_explicit',
    ]);

    HomepageSection::factory()->create([
        'name' => 'Zulu',
        'type' => HomepageSectionType::ContentGroup,
        'source_config' => ['source_type' => 'content_groups'],
        'display_config' => ['template_key' => 'groups_explicit'],
        'is_visible' => false,
    ]);
    HomepageSection::factory()->create([
        'name' => 'Alpha',
        'type' => HomepageSectionType::Latest,
        'source_config' => ['source_type' => 'latest_content_items'],
        'display_config' => [
            'template_family' => 'invalid_family',
            'template_key' => 'items_explicit',
        ],
    ]);
    HomepageSection::factory()->create([
        'name' => 'Ambiguous',
        'type' => HomepageSectionType::CuratedQuery,
        'source_config' => ['source_type' => 'unknown'],
        'display_config' => ['template_key' => 'ambiguous_key'],
    ]);
    $contributorFirst = HomepageSection::factory()->create([
        'name' => 'תורמים <ראשון>',
        'source_config' => ['source_type' => 'contributors'],
        'display_config' => ['template_key' => 'contributors_explicit'],
    ]);
    $contributorSecond = HomepageSection::factory()->create([
        'name' => 'תורמים שני',
        'source_config' => ['source_type' => 'top_transcribers'],
        'display_config' => ['template_key' => 'contributors_explicit'],
    ]);
    HomepageSection::factory()->create([
        'name' => 'Explicit family on content block',
        'source_config' => ['source_type' => 'content_block'],
        'display_config' => [
            'template_family' => 'content_group',
            'template_key' => 'block_explicit',
        ],
    ]);
    HomepageSection::factory()->create([
        'name' => 'Implicit item default',
        'source_config' => ['source_type' => 'latest_content_items'],
        'display_config' => [],
    ]);
    HomepageSection::factory()->create([
        'name' => 'Implicit group default',
        'source_config' => ['source_type' => 'content_groups'],
        'display_config' => [],
    ]);
    HomepageSection::factory()->create([
        'name' => 'Implicit contributor default',
        'source_config' => ['source_type' => 'contributors'],
        'display_config' => [],
    ]);

    $queries = [];
    DB::listen(function (QueryExecuted $query) use (&$queries): void {
        if (str_starts_with(strtolower(ltrim($query->sql)), 'select')
            && str_contains(strtolower($query->sql), 'homepage_sections')) {
            $queries[] = $query->sql;
        }
    });

    $references = app(CardTemplateReferenceScanner::class)->scan(settingsSp3cSnapshot());

    expect($queries)->toHaveCount(1)
        ->and(strtolower($queries[0]))->toContain('id', 'name', 'type', 'source_config', 'display_config')
        ->and($references->for('content_group:groups_explicit')['settings'])->toBe(['podcasts_page.template_key'])
        ->and($references->for('content_group:groups_explicit')['sections'])->toHaveCount(1)
        ->and($references->for('content_item:items_explicit')['settings'])->toBe(['podcasts_page.item_template_key'])
        ->and($references->for('content_item:items_explicit')['sections'])->toHaveCount(1)
        ->and($references->for('contributor:contributors_explicit')['sections'])->toBe([
            ['id' => $contributorFirst->id, 'name' => 'תורמים <ראשון>'],
            ['id' => $contributorSecond->id, 'name' => 'תורמים שני'],
        ])
        ->and($references->for('content_group:block_explicit')['sections'])->toHaveCount(1)
        ->and($references->for('content_item:default_content_item')['implicit'])->toBe(1)
        ->and($references->for('content_group:default_content_group')['implicit'])->toBe(1)
        ->and($references->for('contributor:default_contributor')['implicit'])->toBe(1)
        ->and($references->ambiguousKeys)->toHaveKey('ambiguous_key')
        ->and($references->sectionRows)->toBe(9);

    HomepageSection::factory()->count(40)->create();
    $queries = [];
    $scaledReferences = app(CardTemplateReferenceScanner::class)->scan(settingsSp3cSnapshot());

    expect($queries)->toHaveCount(1);

    if (getenv('SP3C_PRODUCTION_REPORT') === '1') {
        fwrite(STDOUT, json_encode([
            'reference_scan' => [
                'initial_rows' => $references->sectionRows,
                'initial_milliseconds' => round($references->milliseconds, 3),
                'scaled_rows' => $scaledReferences->sectionRows,
                'scaled_milliseconds' => round($scaledReferences->milliseconds, 3),
                'queries_at_each_scale' => count($queries),
            ],
        ], JSON_THROW_ON_ERROR).PHP_EOL);
    }
});

it('edits only the target from one fresh snapshot and preserves siblings and foreign roots', function (): void {
    $before = settingsSp3cTemplate('before');
    $target = settingsSp3cTemplate('target');
    $after = settingsSp3cTemplate('after');
    settingsSp3cSave(PublicContentSettings::class, 'card_templates', [$before, $target, $after]);
    $fingerprint = app(CardTemplateIdentity::class)->fingerprint($target);

    $podcastsPage = PublicFrontConfigRegistry::defaults()['podcasts_page'];
    $podcastsPage['title'] = 'Sequential foreign-root change';
    settingsSp3cSave(PublicContentSettings::class, 'podcasts_page', $podcastsPage);
    Event::fake([SettingsSaved::class]);

    $draft = $target;
    $draft['label'] = 'Changed target';
    app(CardTemplateFocusedWriter::class)->edit($draft, 'content_item', 'target', $fingerprint);

    Event::assertDispatchedTimes(SettingsSaved::class, 1);
    $snapshot = settingsSp3cSnapshot();

    expect($snapshot['card_templates'][0])->toBe($before)
        ->and($snapshot['card_templates'][1]['label'])->toBe('Changed target')
        ->and($snapshot['card_templates'][2])->toBe($after)
        ->and(app(CardTemplateIdentity::class)->canonicalJson($snapshot['card_templates'][0]))
        ->toBe(app(CardTemplateIdentity::class)->canonicalJson($before))
        ->and($snapshot['podcasts_page']['title'])->toBe('Sequential foreign-root change');
});

it('detects sequential stale edits collisions references and default identity mutations without saving', function (): void {
    $target = settingsSp3cTemplate('target');
    $default = PublicFrontCardTemplateRegistry::defaultTemplateForFamily('content_item');
    settingsSp3cSave(PublicContentSettings::class, 'card_templates', [$target, $default]);
    $staleFingerprint = app(CardTemplateIdentity::class)->fingerprint($target);
    $changed = $target;
    $changed['label'] = 'Changed elsewhere';
    settingsSp3cSave(PublicContentSettings::class, 'card_templates', [$changed, $default]);

    Event::fake([SettingsSaved::class]);

    expect(fn () => app(CardTemplateFocusedWriter::class)->edit(
        $target,
        'content_item',
        'target',
        $staleFingerprint,
    ))->toThrow(CardTemplateWriteException::class, 'stale');

    $freshFingerprint = app(CardTemplateIdentity::class)->fingerprint($changed);
    $collision = $changed;
    $collision['key'] = 'default_content_item';

    expect(fn () => app(CardTemplateFocusedWriter::class)->edit(
        $collision,
        'content_item',
        'target',
        $freshFingerprint,
    ))->toThrow(CardTemplateWriteException::class, 'default_identity');

    expect(fn () => app(CardTemplateFocusedWriter::class)->delete(
        'content_item',
        'default_content_item',
        app(CardTemplateIdentity::class)->fingerprint($default),
    ))->toThrow(CardTemplateWriteException::class, 'default_identity');

    Event::assertNotDispatched(SettingsSaved::class);
});

it('blocks referenced and ambiguous renames with useful deterministic blocker details', function (): void {
    $target = settingsSp3cTemplate('referenced');
    settingsSp3cSave(PublicContentSettings::class, 'card_templates', [$target]);
    $section = HomepageSection::factory()->create([
        'name' => 'מדור ייחוס <בטוח>',
        'type' => HomepageSectionType::Latest,
        'is_visible' => false,
        'display_config' => [
            'template_family' => 'content_item',
            'template_key' => 'referenced',
        ],
    ]);
    $renamed = $target;
    $renamed['key'] = 'renamed';

    try {
        app(CardTemplateFocusedWriter::class)->edit(
            $renamed,
            'content_item',
            'referenced',
            app(CardTemplateIdentity::class)->fingerprint($target),
        );

        $this->fail('The referenced rename should have been refused.');
    } catch (CardTemplateWriteException $exception) {
        expect($exception->getMessage())->toBe('referenced')
            ->and($exception->details)->toBe(["#{$section->id} מדור ייחוס <בטוח>"]);
    }

    Livewire::test(EditCardTemplate::class, [
        'family' => 'content_item',
        'key' => 'referenced',
    ])
        ->set('data.key', 'renamed')
        ->call('save')
        ->assertHasErrors('data.key')
        ->assertSeeHtml("#{$section->id} מדור ייחוס &lt;בטוח&gt;");

    $ambiguous = settingsSp3cTemplate('ambiguous_key');
    settingsSp3cSave(PublicContentSettings::class, 'card_templates', [$ambiguous]);
    $ambiguousSection = HomepageSection::factory()->create([
        'name' => 'No-family content block',
        'source_config' => ['source_type' => 'content_block'],
        'display_config' => ['template_key' => 'ambiguous_key'],
    ]);
    $ambiguousRename = $ambiguous;
    $ambiguousRename['key'] = 'renamed_ambiguous';

    try {
        app(CardTemplateFocusedWriter::class)->edit(
            $ambiguousRename,
            'content_item',
            'ambiguous_key',
            app(CardTemplateIdentity::class)->fingerprint($ambiguous),
        );

        $this->fail('The ambiguous rename should have been refused.');
    } catch (CardTemplateWriteException $exception) {
        expect($exception->getMessage())->toBe('referenced')
            ->and($exception->details)->toContain("#{$ambiguousSection->id} No-family content block");
    }

    expect(settingsSp3cSnapshot()['card_templates'][0])->toBe($ambiguous);
});

it('allows independent sibling editors to survive sequential saves and makes a second same-target editor stale', function (): void {
    $first = settingsSp3cTemplate('first');
    $second = settingsSp3cTemplate('second');
    settingsSp3cSave(PublicContentSettings::class, 'card_templates', [$first, $second]);

    $firstEditor = Livewire::test(EditCardTemplate::class, [
        'family' => 'content_item',
        'key' => 'first',
    ])->set('data.label', 'First saved');
    $secondEditor = Livewire::test(EditCardTemplate::class, [
        'family' => 'content_item',
        'key' => 'second',
    ])->set('data.label', 'Second saved');
    $firstEditor->call('save')->assertHasNoErrors();
    $secondEditor->call('save')->assertHasNoErrors();

    $templates = settingsSp3cSnapshot()['card_templates'];
    expect($templates[0]['label'])->toBe('First saved')
        ->and($templates[1]['label'])->toBe('Second saved');

    $staleOne = Livewire::test(EditCardTemplate::class, [
        'family' => 'content_item',
        'key' => 'first',
    ])->set('data.label', 'Newer first');
    $staleTwo = Livewire::test(EditCardTemplate::class, [
        'family' => 'content_item',
        'key' => 'first',
    ])->set('data.label', 'Stale first');
    $staleOne->call('save')->assertHasNoErrors();
    $staleTwo->call('save')->assertHasErrors('data.key');

    expect(settingsSp3cSnapshot()['card_templates'][0]['label'])->toBe('Newer first')
        ->and($staleTwo->get('data.label'))->toBe('Stale first');
});

it('preserves corrupt siblings exactly while a valid target edit is focused', function (): void {
    $corruptBefore = ['raw' => ['z' => 2, 'a' => 1], 'parts' => 'broken'];
    $target = settingsSp3cTemplate('target');
    $corruptAfter = 'opaque sibling';
    settingsSp3cSave(PublicContentSettings::class, 'card_templates', [$corruptBefore, $target, $corruptAfter]);
    $draft = $target;
    $draft['label'] = 'Valid target only';

    app(CardTemplateFocusedWriter::class)->edit(
        $draft,
        'content_item',
        'target',
        app(CardTemplateIdentity::class)->fingerprint($target),
    );

    $templates = settingsSp3cSnapshot()['card_templates'];
    expect($templates[0])->toBe($corruptBefore)
        ->and($templates[1]['label'])->toBe('Valid target only')
        ->and($templates[2])->toBe($corruptAfter);
});

it('creates clone and override candidates deterministically and preserves append and delete order', function (): void {
    $source = settingsSp3cTemplate(str_repeat('a', 80));
    $sibling = settingsSp3cTemplate('sibling');
    settingsSp3cSave(PublicContentSettings::class, 'card_templates', [$source, $sibling]);

    $create = Livewire::withQueryParams([
        'mode' => 'clone',
        'family' => 'content_item',
        'key' => $source['key'],
    ])->test(CreateCardTemplate::class);
    $copyKey = $create->get('data.key');

    expect(strlen($copyKey))->toBe(80)
        ->and($copyKey)->toEndWith('_copy');

    $create->call('save')->assertHasNoErrors();
    $templates = settingsSp3cSnapshot()['card_templates'];

    expect($templates[0])->toBe($source)
        ->and($templates[1])->toBe($sibling)
        ->and($templates[2]['key'])->toBe($copyKey);

    $copy = $templates[2];
    app(CardTemplateFocusedWriter::class)->delete(
        'content_item',
        $copyKey,
        app(CardTemplateIdentity::class)->fingerprint($copy),
    );
    $afterDelete = settingsSp3cSnapshot()['card_templates'];

    expect($afterDelete)->toBe([$source, $sibling]);

    Livewire::withQueryParams([
        'mode' => 'override',
        'family' => 'content_group',
        'key' => 'default_content_group',
    ])->test(CreateCardTemplate::class)
        ->call('save')
        ->assertHasNoErrors();

    $snapshot = settingsSp3cSnapshot();
    $defaultRows = collect(app(CardTemplateLibraryProjector::class)->project($snapshot)->records)
        ->where('identity', 'content_group:default_content_group');

    expect($snapshot['card_templates'][2]['key'])->toBe('default_content_group')
        ->and($defaultRows)->toHaveCount(1)
        ->and($defaultRows->first()['kind'])->toBe('configured')
        ->and($defaultRows->first()['default_override'])->toBeTrue();
});

it('builds an unsaved deterministic clone through collision suffixes and locks its source transport', function (): void {
    $source = settingsSp3cTemplate(str_repeat('a', 80));
    $source['label'] = str_repeat('ל', 120);
    $copyOne = settingsSp3cTemplate(str_repeat('a', 75).'_copy');
    $copyTwo = settingsSp3cTemplate(str_repeat('a', 73).'_copy_2');
    settingsSp3cSave(PublicContentSettings::class, 'card_templates', [$source, $copyOne, $copyTwo]);
    Event::fake([SettingsSaved::class]);

    $component = Livewire::withQueryParams([
        'mode' => 'clone',
        'family' => 'content_item',
        'key' => $source['key'],
    ])->test(CreateCardTemplate::class);

    expect($component->get('data.key'))->toBe(str_repeat('a', 73).'_copy_3')
        ->and(strlen($component->get('data.key')))->toBe(80)
        ->and(mb_strlen($component->get('data.label')))->toBe(120)
        ->and($component->get('data.label'))->toEndWith(__('admin.settings_sp3c.editor.copy_suffix', ['number' => ' 3']))
        ->and($component->get('sourceFamily'))->toBe('content_item')
        ->and($component->get('sourceKey'))->toBe($source['key'])
        ->and($component->get('sourceFingerprint'))->toBe(app(CardTemplateIdentity::class)->fingerprint($source));

    expect(fn () => $component->set('sourceKey', 'tampered'))
        ->toThrow(Exception::class, 'Cannot update locked property');

    Event::assertNotDispatched(SettingsSaved::class);
});

it('refuses stale clone sources and save-time create collisions without lifecycle effects', function (): void {
    $source = settingsSp3cTemplate('source');
    settingsSp3cSave(PublicContentSettings::class, 'card_templates', [$source]);
    $clone = Livewire::withQueryParams([
        'mode' => 'clone',
        'family' => 'content_item',
        'key' => 'source',
    ])->test(CreateCardTemplate::class);
    $changed = $source;
    $changed['label'] = 'Changed source';
    settingsSp3cSave(PublicContentSettings::class, 'card_templates', [$changed]);
    Event::fake([SettingsSaved::class]);

    $clone->call('save')->assertHasErrors('data.key');
    Event::assertNotDispatched(SettingsSaved::class);
    expect($clone->get('data.label'))->toContain(__('admin.settings_sp3c.editor.copy_suffix', ['number' => '']));

    $blank = Livewire::withQueryParams(['mode' => 'blank'])
        ->test(CreateCardTemplate::class)
        ->set('data.key', 'collision')
        ->set('data.label', 'Collision');
    settingsSp3cSave(PublicContentSettings::class, 'card_templates', [
        $changed,
        settingsSp3cTemplate('collision'),
    ]);
    Event::fake([SettingsSaved::class]);
    $blank->call('save')->assertHasErrors('data.key');
    Event::assertNotDispatched(SettingsSaved::class);
    expect($blank->get('data.key'))->toBe('collision')
        ->and($blank->get('data.label'))->toBe('Collision');
});

it('refuses missing and duplicate clone sources at save time without lifecycle effects', function (): void {
    $source = settingsSp3cTemplate('source');
    settingsSp3cSave(PublicContentSettings::class, 'card_templates', [$source]);
    $missing = Livewire::withQueryParams([
        'mode' => 'clone',
        'family' => 'content_item',
        'key' => 'source',
    ])->test(CreateCardTemplate::class);
    settingsSp3cSave(PublicContentSettings::class, 'card_templates', []);
    Event::fake([SettingsSaved::class]);

    $missing->call('save')->assertHasErrors('data.key');
    Event::assertNotDispatched(SettingsSaved::class);

    settingsSp3cSave(PublicContentSettings::class, 'card_templates', [$source]);
    $duplicate = Livewire::withQueryParams([
        'mode' => 'clone',
        'family' => 'content_item',
        'key' => 'source',
    ])->test(CreateCardTemplate::class);
    settingsSp3cSave(PublicContentSettings::class, 'card_templates', [$source, $source]);
    Event::fake([SettingsSaved::class]);

    $duplicate->call('save')->assertHasErrors('data.key');
    Event::assertNotDispatched(SettingsSaved::class);
});

it('uses exact hidden routes and mounts one editable template draft without sibling state', function (): void {
    $target = settingsSp3cTemplate('target');
    $sibling = settingsSp3cTemplate('sibling');
    settingsSp3cSave(PublicContentSettings::class, 'card_templates', [$target, $sibling]);

    expect(CardTemplateSettings::getUrl())->toEndWith('/admin/settings/card-templates')
        ->and(CreateCardTemplate::getUrl(['mode' => 'blank']))->toContain('/admin/settings/card-templates/create')
        ->and(EditCardTemplate::getUrl(['family' => 'content_item', 'key' => 'target']))
        ->toEndWith('/admin/settings/card-templates/edit/content_item/target')
        ->and(CreateCardTemplate::shouldRegisterNavigation())->toBeFalse()
        ->and(EditCardTemplate::shouldRegisterNavigation())->toBeFalse();

    $component = Livewire::test(EditCardTemplate::class, [
        'family' => 'content_item',
        'key' => 'target',
    ])->assertOk();
    $state = $component->get('data');
    $html = $component->html();

    expect(array_keys($state))->toEqualCanonicalizing([
        'key', 'label', 'family', 'layout', 'density', 'image_size', 'title_size', 'parts',
    ])
        ->and(json_encode($state, JSON_THROW_ON_ERROR))->not->toContain('sibling')
        ->and($html)->toContain('data-sp3c-part-summary')
        ->and($html)->not->toContain('Template sibling')
        ->and($component->get('savedDataHash'))->not->toBeEmpty();

    $component
        ->set('data.label', 'Saved from one draft')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(EditCardTemplate::getUrl(['family' => 'content_item', 'key' => 'target']));

    expect(settingsSp3cSnapshot()['card_templates'][0]['label'])->toBe('Saved from one draft')
        ->and(settingsSp3cSnapshot()['card_templates'][1])->toBe($sibling);
});

it('renames an unused non default template at its original index and makes the old URL 404', function (): void {
    $before = settingsSp3cTemplate('before');
    $target = settingsSp3cTemplate('rename_me');
    $after = settingsSp3cTemplate('after');
    settingsSp3cSave(PublicContentSettings::class, 'card_templates', [$before, $target, $after]);
    $oldUrl = EditCardTemplate::getUrl(['family' => 'content_item', 'key' => 'rename_me']);
    $newUrl = EditCardTemplate::getUrl(['family' => 'content_item', 'key' => 'renamed']);

    Livewire::test(EditCardTemplate::class, [
        'family' => 'content_item',
        'key' => 'rename_me',
    ])
        ->set('data.key', 'renamed')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect($newUrl);

    $templates = settingsSp3cSnapshot()['card_templates'];
    expect($templates[0])->toBe($before)
        ->and($templates[1]['key'])->toBe('renamed')
        ->and($templates[2])->toBe($after);

    $this->get($oldUrl)->assertNotFound();
    $this->get($newUrl)->assertOk();
});

it('returns redirects or refusals for guest ordinary and malformed route access', function (): void {
    $target = settingsSp3cTemplate('target');
    settingsSp3cSave(PublicContentSettings::class, 'card_templates', [$target]);
    auth()->logout();

    $this->get(CardTemplateSettings::getUrl())->assertRedirect();
    $this->get(EditCardTemplate::getUrl(['family' => 'content_item', 'key' => 'target']))->assertRedirect();

    $this->actingAs(User::factory()->role(UserRole::User)->create());
    $this->get(CardTemplateSettings::getUrl())->assertForbidden();

    $malformedUrls = [
        '/admin/settings/card-templates/edit/content_item/not%2Fvalid',
        '/admin/settings/card-templates/edit/content_item/%257Bbad%257D',
        '/admin/settings/card-templates/edit/content_item/%25bad',
        '/admin/settings/card-templates/edit/content_item/%FF',
        '/admin/settings/card-templates/edit/content_item/'.str_repeat('a', 81),
    ];
    $statuses = collect($malformedUrls)
        ->mapWithKeys(fn (string $url): array => [$url => $this->get($url)->getStatusCode()])
        ->all();

    expect($statuses)->toBe(array_fill_keys($malformedUrls, 404));
});

it('returns 404 for missing corrupt and duplicate edit identities', function (): void {
    $valid = settingsSp3cTemplate('target');
    $url = EditCardTemplate::getUrl(['family' => 'content_item', 'key' => 'target']);

    settingsSp3cSave(PublicContentSettings::class, 'card_templates', []);
    $this->get($url)->assertNotFound();

    $corrupt = $valid;
    $corrupt['parts'] = 'not-a-list';
    settingsSp3cSave(PublicContentSettings::class, 'card_templates', [$corrupt]);
    $this->get($url)->assertNotFound();

    settingsSp3cSave(PublicContentSettings::class, 'card_templates', [$valid, $valid]);
    $this->get($url)->assertNotFound();
});

it('allows direct library create and edit URLs for super administrators in both transcription modes', function (): void {
    $target = settingsSp3cTemplate('target');
    settingsSp3cSave(PublicContentSettings::class, 'card_templates', [$target]);

    foreach ([TranscriptionMode::Single, TranscriptionMode::Multi] as $mode) {
        settingsSp3cSave(AdminUxSettings::class, 'transcription_mode', $mode->value);
        $this->actingAs(User::factory()->superAdmin()->create());

        $this->get(CardTemplateSettings::getUrl())->assertOk();
        $this->get(CreateCardTemplate::getUrl(['mode' => 'blank']))->assertOk();
        $this->get(EditCardTemplate::getUrl([
            'family' => 'content_item',
            'key' => 'target',
        ]))->assertOk();
    }
});

it('shows bilingual import lock state without letting an import lock block an ordinary save', function (): void {
    $target = settingsSp3cTemplate('locked_family');
    settingsSp3cSave(PublicContentSettings::class, 'card_templates', [$target]);
    settingsSp3cSave(PublicContentSettings::class, 'import_locks', [
        'locked_paths' => ['card_templates.content_item'],
    ]);

    foreach (['he', 'en'] as $locale) {
        app()->setLocale($locale);

        Livewire::test(CardTemplateSettings::class)
            ->assertSee(__('admin.settings_sp3c.import_locks.heading'))
            ->assertSee(__('admin.settings_sp3c.import_locks.locked'));
    }

    app()->setLocale('he');
    Livewire::test(EditCardTemplate::class, [
        'family' => 'content_item',
        'key' => 'locked_family',
    ])
        ->assertSet('familyImportLocked', true)
        ->set('data.label', 'Import lock is informational')
        ->call('save')
        ->assertHasNoErrors();

    expect(settingsSp3cSnapshot()['card_templates'][0]['label'])->toBe('Import lock is informational');
});

it('keeps missing duplicate validation authorization and referenced delete failures lifecycle free', function (): void {
    $target = settingsSp3cTemplate('target');
    settingsSp3cSave(PublicContentSettings::class, 'card_templates', [$target]);
    $fingerprint = app(CardTemplateIdentity::class)->fingerprint($target);
    $section = HomepageSection::factory()->create([
        'type' => HomepageSectionType::Latest,
        'display_config' => [
            'template_family' => 'content_item',
            'template_key' => 'target',
        ],
    ]);
    Event::fake([SettingsSaved::class]);

    expect(fn () => app(CardTemplateFocusedWriter::class)->edit(
        $target,
        'content_item',
        'missing',
        $fingerprint,
    ))->toThrow(CardTemplateWriteException::class, 'missing');

    $invalid = $target;
    $invalid['unexpected_key'] = 'refuse me';
    expect(fn () => app(CardTemplateFocusedWriter::class)->edit(
        $invalid,
        'content_item',
        'target',
        $fingerprint,
    ))->toThrow(CardTemplateWriteException::class, 'validation');

    expect(fn () => app(CardTemplateFocusedWriter::class)->delete(
        'content_item',
        'target',
        $fingerprint,
    ))->toThrow(CardTemplateWriteException::class, 'referenced');

    settingsSp3cSave(PublicContentSettings::class, 'card_templates', [$target, $target]);
    expect(fn () => app(CardTemplateFocusedWriter::class)->edit(
        $target,
        'content_item',
        'target',
        $fingerprint,
    ))->toThrow(CardTemplateWriteException::class, 'duplicate');

    $this->actingAs(User::factory()->role(UserRole::User)->create());
    expect(fn () => app(CardTemplateFocusedWriter::class)->edit(
        $target,
        'content_item',
        'target',
        $fingerprint,
    ))->toThrow(CardTemplateWriteException::class, 'unauthorized');

    Event::assertNotDispatched(SettingsSaved::class);
    expect($section->exists)->toBeTrue();
});

it('keeps protected parts out of non-capable html and state and restores them on shell save', function (): void {
    $protected = settingsSp3cProtectedTemplate('protected', 'SECRET-SP3C-PROTECTED');
    settingsSp3cSave(PublicContentSettings::class, 'card_templates', [$protected]);
    settingsSp3cSave(AdminUxSettings::class, 'transcription_mode', TranscriptionMode::Multi->value);
    $this->actingAs(User::factory()->admin()->create());

    $component = Livewire::test(EditCardTemplate::class, [
        'family' => 'content_item',
        'key' => 'protected',
    ])
        ->assertSee(__('admin.settings_sp3c.editor.restricted_copy'))
        ->assertDontSee('SECRET-SP3C-PROTECTED', escape: false);

    expect($component->get('data'))->not->toHaveKey('parts')
        ->and($component->html())->not->toContain('SECRET-SP3C-PROTECTED');

    $component
        ->set('data.label', 'Safe shell label')
        ->call('save')
        ->assertHasNoErrors();
    $saved = settingsSp3cSnapshot()['card_templates'][0];

    expect($saved['label'])->toBe('Safe shell label')
        ->and(app(CardTemplateIdentity::class)->canonicalJson($saved['parts']))
        ->toBe(app(CardTemplateIdentity::class)->canonicalJson($protected['parts']));
});

it('sanitizes capability loss and refuses forged protected additions with zero save events', function (): void {
    $protected = settingsSp3cProtectedTemplate('protected', 'LOSS-SP3C-SECRET');
    $ordinary = settingsSp3cTemplate('ordinary');
    settingsSp3cSave(PublicContentSettings::class, 'card_templates', [$protected, $ordinary]);
    settingsSp3cSave(AdminUxSettings::class, 'transcription_mode', TranscriptionMode::Multi->value);
    $this->actingAs(User::factory()->superAdmin()->create());

    $component = Livewire::test(EditCardTemplate::class, [
        'family' => 'content_item',
        'key' => 'protected',
    ]);
    expect(json_encode($component->get('data'), JSON_THROW_ON_ERROR))->toContain('LOSS-SP3C-SECRET');

    settingsSp3cSave(AdminUxSettings::class, 'transcription_mode', TranscriptionMode::Single->value);
    $component->call('$refresh')->assertDontSee('LOSS-SP3C-SECRET', escape: false);

    expect($component->get('data'))->not->toHaveKey('parts');

    $this->actingAs(User::factory()->admin()->create());
    Event::fake([SettingsSaved::class]);
    $forged = Livewire::test(EditCardTemplate::class, [
        'family' => 'content_item',
        'key' => 'ordinary',
    ]);
    $parts = $forged->get('data.parts');
    $firstKey = array_key_first($parts);
    $parts[$firstKey]['data']['source'] = 'content_item';
    $parts[$firstKey]['data']['attribute'] = 'transcription_count';

    $forged
        ->set('data.parts', $parts)
        ->call('save')
        ->assertHasErrors('data.key');

    Event::assertNotDispatched(SettingsSaved::class);
    expect(settingsSp3cSnapshot()['card_templates'][1])->toBe($ordinary);
});

it('exposes protected editor state only to a current super admin in multi mode', function (): void {
    $protected = settingsSp3cProtectedTemplate('protected', 'CAPABLE-SP3C-SECRET');
    settingsSp3cSave(PublicContentSettings::class, 'card_templates', [$protected]);
    settingsSp3cSave(AdminUxSettings::class, 'transcription_mode', TranscriptionMode::Multi->value);
    $this->actingAs(User::factory()->superAdmin()->create());

    $editor = Livewire::test(EditCardTemplate::class, [
        'family' => 'content_item',
        'key' => 'protected',
    ]);

    expect(json_encode($editor->get('data'), JSON_THROW_ON_ERROR))->toContain('CAPABLE-SP3C-SECRET')
        ->and($editor->get('restricted'))->toBeFalse();
});

it('hides and hard refuses protected clone and delete for non capable actors', function (): void {
    $protected = settingsSp3cProtectedTemplate('protected', 'HIDDEN-SP3C-SECRET');
    settingsSp3cSave(PublicContentSettings::class, 'card_templates', [$protected]);
    settingsSp3cSave(AdminUxSettings::class, 'transcription_mode', TranscriptionMode::Multi->value);
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(CardTemplateSettings::class)
        ->assertActionHidden(TestAction::make('cloneTemplate')->table('configured:content_item:protected'));

    $editor = Livewire::test(EditCardTemplate::class, [
        'family' => 'content_item',
        'key' => 'protected',
    ])->assertActionHidden(TestAction::make('deleteTemplate'));

    $editor->call('deleteTemplate')->assertForbidden();

    Livewire::withQueryParams([
        'mode' => 'clone',
        'family' => 'content_item',
        'key' => 'protected',
    ])->test(CreateCardTemplate::class)->assertForbidden();

    expect(settingsSp3cSnapshot()['card_templates'][0])->toBe($protected);
});

it('uses the original protected identity for shell rename and restores fresh protected parts', function (): void {
    $protected = settingsSp3cProtectedTemplate('protected', 'RENAMED-SP3C-SECRET');
    settingsSp3cSave(PublicContentSettings::class, 'card_templates', [$protected]);
    settingsSp3cSave(AdminUxSettings::class, 'transcription_mode', TranscriptionMode::Multi->value);
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(EditCardTemplate::class, [
        'family' => 'content_item',
        'key' => 'protected',
    ])
        ->set('data.key', 'protected_renamed')
        ->set('data.label', 'Safe renamed shell')
        ->call('save')
        ->assertHasNoErrors();

    $saved = settingsSp3cSnapshot()['card_templates'][0];
    expect($saved['key'])->toBe('protected_renamed')
        ->and($saved['label'])->toBe('Safe renamed shell')
        ->and(app(CardTemplateIdentity::class)->canonicalJson($saved['parts']))
        ->toBe(app(CardTemplateIdentity::class)->canonicalJson($protected['parts']));
});

it('sanitizes protected state after super admin role demotion and on an initial single mode mount', function (): void {
    $protected = settingsSp3cProtectedTemplate('protected', 'ROLE-LOSS-SP3C-SECRET');
    settingsSp3cSave(PublicContentSettings::class, 'card_templates', [$protected]);
    settingsSp3cSave(AdminUxSettings::class, 'transcription_mode', TranscriptionMode::Multi->value);
    $superAdmin = User::factory()->superAdmin()->create();
    $this->actingAs($superAdmin);
    $component = Livewire::test(EditCardTemplate::class, [
        'family' => 'content_item',
        'key' => 'protected',
    ]);

    expect(json_encode($component->get('data'), JSON_THROW_ON_ERROR))->toContain('ROLE-LOSS-SP3C-SECRET');

    $superAdmin->update(['role' => UserRole::Admin]);
    $component->call('$refresh')->assertDontSee('ROLE-LOSS-SP3C-SECRET', escape: false);

    expect($component->get('data'))->not->toHaveKey('parts');

    settingsSp3cSave(AdminUxSettings::class, 'transcription_mode', TranscriptionMode::Single->value);
    $this->actingAs(User::factory()->superAdmin()->create());
    $singleMode = Livewire::test(EditCardTemplate::class, [
        'family' => 'content_item',
        'key' => 'protected',
    ]);

    expect($singleMode->get('data'))->not->toHaveKey('parts')
        ->and($singleMode->get('restricted'))->toBeTrue()
        ->and($singleMode->html())->not->toContain('ROLE-LOSS-SP3C-SECRET');
});

it('runs one save event one backup attempt and one cache invalidation for a successful editor save', function (): void {
    $target = settingsSp3cTemplate('lifecycle');
    settingsSp3cSave(PublicContentSettings::class, 'card_templates', [$target]);
    $backup = Mockery::mock(SettingsBackupManager::class);
    $backup->shouldReceive('createSystem')->once()->andReturnNull();
    app()->instance(SettingsBackupManager::class, $backup);
    $cache = Mockery::mock(PublicFrontConfigCache::class);
    $cache->shouldReceive('remember')
        ->once()
        ->andReturnUsing(fn (callable $resolver): mixed => $resolver());
    $cache->shouldReceive('forget')->once();
    app()->instance(PublicFrontConfigCache::class, $cache);
    $events = 0;
    Event::listen(SettingsSaved::class, function () use (&$events): void {
        $events++;
    });

    Livewire::test(EditCardTemplate::class, [
        'family' => 'content_item',
        'key' => 'lifecycle',
    ])
        ->set('data.label', 'Lifecycle saved')
        ->call('save')
        ->assertHasNoErrors();

    expect($events)->toBe(1);
});

it('keeps profiler subject on editor saves including the synchronous listener', function (): void {
    $target = settingsSp3cTemplate('profiled');
    settingsSp3cSave(PublicContentSettings::class, 'card_templates', [$target]);
    config()->set('settings.profiling.enabled', true);
    $contexts = [];
    $logger = Mockery::mock(LoggerInterface::class);
    $logger->shouldReceive('info')
        ->zeroOrMoreTimes()
        ->with('Settings page profile', Mockery::type('array'))
        ->andReturnUsing(function (string $message, array $context) use (&$contexts): void {
            $contexts[] = $context;
        });
    Log::shouldReceive('channel')
        ->zeroOrMoreTimes()
        ->with('settings_profiling')
        ->andReturn($logger);

    Livewire::test(EditCardTemplate::class, [
        'family' => 'content_item',
        'key' => 'profiled',
    ])
        ->set('data.label', 'Profiled save')
        ->call('save')
        ->assertHasNoErrors();

    expect(collect($contexts)->where('phase', 'save.settings_persist')->pluck('subject')->unique()->all())
        ->toBe(['card-template-editor'])
        ->and(collect($contexts)->where('phase', 'settings_saved.listener.total')->pluck('subject')->unique()->all())
        ->toBe(['card-template-editor']);
    config()->set('settings.profiling.enabled', false);
});

it('measures local library and unselected editor responses without ordinary leakage', function (): void {
    $environment = app()->environment();
    app()->detectEnvironment(fn (): string => 'local');

    try {
        $measured = $this->get(CardTemplateSettings::getUrl([
            'sp3a_measure' => '1',
        ]));
        $measured->assertOk()
            ->assertHeader('X-SP3A-Uncompressed-Bytes')
            ->assertHeader('X-SP3A-Total-Queries')
            ->assertHeader('X-SP3A-Settings-Reads');
        settingsSp3cForgetState();
        $editorInitial = $this->get(EditCardTemplate::getUrl([
            'family' => 'content_item',
            'key' => 'sp3a_content_item_1',
            'sp3a_measure' => '1',
        ]));
        $editorInitial->assertOk()
            ->assertHeader('X-SP3A-Uncompressed-Bytes')
            ->assertHeader('X-SP3A-Total-Queries')
            ->assertHeader('X-SP3A-Settings-Reads');
        $measure = app(SettingsSp3cCanaryMeasurement::class);
        $libraryInitialMetrics = $measure->measureHtml($measured->getContent(), []);
        $editorInitialMetrics = $measure->measureHtml($editorInitial->getContent(), []);

        $ordinary = $this->get(CardTemplateSettings::getUrl());
        $ordinary->assertOk();
        expect($ordinary->headers->has('X-SP3A-Uncompressed-Bytes'))->toBeFalse()
            ->and($ordinary->getContent())->not->toContain('sp3a_content_item_1');

        $library = Livewire::withQueryParams([
            'sp3a_measure' => '1',
        ])->test(CardTemplateSettings::class);
        $libraryMetrics = $measure->measure($library);

        expect($libraryMetrics['field_wrappers'])->toBeLessThanOrEqual(2)
            ->and($libraryMetrics['editor_controls'])->toBeLessThanOrEqual(2)
            ->and($libraryMetrics['elements'])->toBeLessThanOrEqual(1262)
            ->and($libraryMetrics['html_bytes'])->toBeLessThanOrEqual(575913)
            ->and($libraryMetrics['serialized_state_bytes'])->toBeLessThanOrEqual(13973);

        $editor = Livewire::withQueryParams([
            'sp3a_measure' => '1',
        ])->test(EditCardTemplate::class, [
            'family' => 'content_item',
            'key' => 'sp3a_content_item_1',
        ]);
        $metrics = $measure->measure($editor);

        expect($metrics['field_wrappers'])->toBeLessThanOrEqual(10)
            ->and($metrics['editor_controls'])->toBeLessThanOrEqual(3)
            ->and($metrics['wire_models'])->toBeLessThanOrEqual(3)
            ->and($metrics['elements'])->toBeLessThanOrEqual(4212);

        if (getenv('SP3C_PRODUCTION_REPORT') === '1') {
            fwrite(STDOUT, json_encode([
                'initial_get' => [
                    'library' => [
                        'headers' => collect([
                            'X-SP3A-Uncompressed-Bytes',
                            'X-SP3A-Total-Queries',
                            'X-SP3A-Settings-Reads',
                            'X-SP3A-Lifecycle-Derivations',
                            'X-SP3A-Duplicate-Lifecycle-Loads',
                        ])->mapWithKeys(fn (string $header): array => [$header => $measured->headers->get($header)])->all(),
                        'html' => $libraryInitialMetrics,
                    ],
                    'editor' => [
                        'headers' => collect([
                            'X-SP3A-Uncompressed-Bytes',
                            'X-SP3A-Total-Queries',
                            'X-SP3A-Settings-Reads',
                            'X-SP3A-Lifecycle-Derivations',
                            'X-SP3A-Duplicate-Lifecycle-Loads',
                        ])->mapWithKeys(fn (string $header): array => [$header => $editorInitial->headers->get($header)])->all(),
                        'html' => $editorInitialMetrics,
                    ],
                ],
                'library' => $libraryMetrics,
                'editor_unselected' => $metrics,
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES).PHP_EOL);
        }
    } finally {
        app()->detectEnvironment(fn (): string => $environment);
    }
});

it('keeps local measurement state locked read-only and uses the frozen SP3A editor identity', function (): void {
    $environment = app()->environment();
    app()->detectEnvironment(fn (): string => 'local');

    try {
        $component = Livewire::withQueryParams([
            'sp3a_measure' => '1',
            'sp3a_profile' => '1',
        ])->test(EditCardTemplate::class, [
            'family' => 'content_item',
            'key' => 'sp3a_content_item_1',
        ]);

        expect($component->get('measurementFixtureIdentity'))->toBe('content_item:sp3a_content_item_1');

        expect(fn () => $component->set('sp3aMeasurementMode', false))
            ->toThrow(Exception::class, 'Cannot update locked property');
        expect(fn () => $component->set('profilingMode', false))
            ->toThrow(Exception::class, 'Cannot update locked property');
        expect(fn () => $component->set('measurementFixtureIdentity', 'forged'))
            ->toThrow(Exception::class, 'Cannot update locked property');

        $library = Livewire::withQueryParams([
            'sp3a_measure' => '1',
            'sp3a_profile' => '1',
        ])->test(CardTemplateSettings::class);

        expect($library->get('measurementFixtureIdentity'))->toBe('sp3a-library');
        expect(fn () => $library->set('sp3aMeasurementMode', false))
            ->toThrow(Exception::class, 'Cannot update locked property');

        Livewire::withQueryParams([
            'sp3a_measure' => '1',
        ])->test(EditCardTemplate::class, [
            'family' => 'content_item',
            'key' => 'sp3a_content_item_1',
        ])
            ->call('save')
            ->assertHasErrors('data.key');

        Event::fake([SettingsSaved::class]);
        Livewire::withQueryParams([
            'sp3a_measure' => '1',
        ])->test(CreateCardTemplate::class)
            ->call('save')
            ->assertHasErrors('data.key');

        Livewire::withQueryParams([
            'sp3a_measure' => '1',
            'mode' => 'clone',
            'family' => 'content_item',
            'key' => 'sp3a_content_item_1',
        ])->test(CreateCardTemplate::class)
            ->call('save')
            ->assertHasErrors('data.key');

        Livewire::withQueryParams([
            'sp3a_measure' => '1',
        ])->test(EditCardTemplate::class, [
            'family' => 'content_item',
            'key' => 'sp3a_content_item_1',
        ])
            ->call('deleteTemplate')
            ->assertHasErrors('data.key');

        Event::assertNotDispatched(SettingsSaved::class);
    } finally {
        app()->detectEnvironment(fn (): string => $environment);
        config()->set('settings.profiling.enabled', false);
    }
});
