<?php

use App\Filament\Pages\AboutSettings;
use App\Filament\Pages\DisplaySettings;
use App\Filament\Pages\EpisodePageSettings;
use App\Filament\Pages\HomepageSettings;
use App\Filament\Pages\ManagePublicForms;
use App\Filament\Pages\MenuHeaderSettings;
use App\Filament\Pages\PublicContentSettingsSubjectPage;
use App\Filament\Pages\SettingsSubjectOwnershipRegistry;
use App\Filament\Support\IntegerTextInputState;
use App\Models\User;
use App\Settings\PublicContentSettings;
use App\Support\PublicContent\PublicContentCardOptions;
use App\Support\PublicFront\Cards\PublicFrontCardTemplateRegistry;
use App\Support\PublicFront\ItemPage\PublicItemPageRegistry;
use App\Support\PublicFront\PublicFrontConfigReader;
use App\Support\PublicFront\PublicFrontConfigRegistry;
use App\Support\PublicFront\PublicFrontConfigValidator;
use App\Support\PublicFront\PublicFrontRenderContext;
use App\Support\Settings\CardTemplates\CardTemplateFocusedWriter;
use App\Support\Settings\CardTemplates\CardTemplateIdentity;
use App\Support\SettingsLifecycle\PublicContentSettingsWriteCoordinator;
use App\Support\SettingsLifecycle\PublicSettingsPackage;
use App\Support\SettingsLifecycle\SettingsBackupManager;
use App\Support\SettingsLifecycle\SettingsImportLocks;
use App\Support\SettingsLifecycle\SettingsLifecycleSchema;
use App\Support\SettingsLifecycle\SettingsSubjectBaseline;
use App\Support\SettingsLifecycle\SettingsSubjectThreeWayMerger;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Spatie\LaravelSettings\Events\SettingsSaved;
use Spatie\LaravelSettings\SettingsContainer;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    fakeSettingsBackupSnapshotQueue();
});

function clearPublicFrontSettingsCache(): void
{
    app()->forgetInstance(PublicContentSettings::class);
    app()->forgetInstance(PublicFrontRenderContext::class);
    app(SettingsContainer::class)->clearCache();
}

function sortPublicFrontJsonKeys(mixed $value): mixed
{
    if (! is_array($value)) {
        return $value;
    }

    $value = array_map(sortPublicFrontJsonKeys(...), $value);

    if (array_is_list($value)) {
        return $value;
    }

    ksort($value);

    return $value;
}

class NativeSettingsLifecycleProbePage extends HomepageSettings
{
    public int $beforeFillCalls = 0;

    public int $afterFillCalls = 0;

    public int $beforeSaveCalls = 0;

    public int $afterSaveCalls = 0;

    protected function beforeFill(): void
    {
        parent::beforeFill();

        $this->beforeFillCalls++;
    }

    protected function afterFill(): void
    {
        $this->afterFillCalls++;

        parent::afterFill();
    }

    protected function beforeSave(): void
    {
        $this->beforeSaveCalls++;
    }

    protected function afterSave(): void
    {
        $this->afterSaveCalls++;

        parent::afterSave();
    }
}

class CardTemplatesSubjectLifecycleProbePage extends PublicContentSettingsSubjectPage
{
    protected static bool $shouldRegisterNavigation = false;

    protected function settingsSubject(): string
    {
        return SettingsSubjectOwnershipRegistry::CARD_TEMPLATES;
    }

    protected function subjectSchema(): Tab
    {
        return $this->cardTemplatesTab();
    }
}

class MediaFloatAdmissionProbePage extends MenuHeaderSettings
{
    protected static bool $shouldRegisterNavigation = false;

    /**
     * @param  array<string, mixed>  $data
     */
    public function admitUnadaptedPayload(array $data): void
    {
        $this->data = $data;

        $this->mutateFormDataBeforeSave($data);
    }

    public function pendingMediaIdentity(): mixed
    {
        return data_get($this->data, 'menu_config.logo.light_media_reference_key');
    }
}

class IntegerTransportProbeSettingsPage extends SettingsPage
{
    protected static string $settings = PublicContentSettings::class;

    protected static bool $shouldRegisterNavigation = false;

    public function mount(): void
    {
        $this->form->fill([
            'integer_value' => 3,
            'menu_config' => [
                'logo' => [
                    'light_media_reference_key' => null,
                ],
            ],
            'unrelated_ratio' => 2,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('integer_value')
                    ->integer()
                    ->dehydrateStateUsing(IntegerTextInputState::dehydrate(...)),
                TextInput::make('menu_config.logo.light_media_reference_key'),
                TextInput::make('unrelated_ratio')
                    ->numeric(),
            ])
            ->statePath('data');
    }
}

function saveConcurrentPublicContentSettings(array $changes): void
{
    clearPublicFrontSettingsCache();

    $settings = app(PublicContentSettings::class);

    foreach ($changes as $property => $value) {
        $settings->{$property} = $value;
    }

    $settings->save();
    clearPublicFrontSettingsCache();
}

function task4PublicFormsConfig(): array
{
    return [
        'require_email_verification' => false,
        'definitions' => [
            [
                'key' => 'contact',
                'name' => 'Contact',
                'heading' => 'Contact',
                'description' => null,
                'submit_label' => 'Send',
                'success_message' => 'Sent',
                'enabled' => false,
                'display_mode_default' => 'modal',
                'fields' => [
                    [
                        'key' => 'message',
                        'type' => 'text',
                        'label' => 'Message',
                        'required' => false,
                        'options' => [],
                        'validation_semantics' => 'none',
                        'min_length' => 1,
                        'max_length' => 100,
                    ],
                ],
                'settings' => [
                    'rate_limit_attempts' => 5,
                    'rate_limit_decay_seconds' => 600,
                    'submitter_email_verification' => 'off',
                ],
            ],
        ],
    ];
}

function task6SelectablePublicFormsConfig(): array
{
    $publicForms = task4PublicFormsConfig();
    $publicForms['definitions'][0]['fields'][0] = [
        'key' => 'topic',
        'type' => 'select',
        'label' => 'Topic',
        'required' => true,
        'options' => [
            [
                'value' => 'support',
                'label' => 'Support',
            ],
        ],
        'validation_semantics' => 'none',
    ];

    return $publicForms;
}

function mergeSettingsSubjectPayloads(array $opened, array $local, array $fresh): array
{
    $baseline = app(SettingsSubjectBaseline::class);

    return app(SettingsSubjectThreeWayMerger::class)->merge(
        $baseline->capture($opened),
        $baseline->capture($opened),
        $local,
        $fresh,
    );
}

it('keeps dynamic map units stable across add remove and existence transitions', function (): void {
    $cases = [
        'local removes the last key' => [
            'opened' => ['podcasts_page' => ['title' => 'Old']],
            'local' => ['podcasts_page' => []],
            'fresh' => ['podcasts_page' => ['title' => 'Old']],
            'expected' => ['podcasts_page' => []],
            'conflict' => null,
        ],
        'fresh removes the last key while local is unchanged' => [
            'opened' => ['podcasts_page' => ['title' => 'Old']],
            'local' => ['podcasts_page' => ['title' => 'Old']],
            'fresh' => ['podcasts_page' => []],
            'expected' => ['podcasts_page' => []],
            'conflict' => null,
        ],
        'local adds the first key' => [
            'opened' => ['podcasts_page' => []],
            'local' => ['podcasts_page' => ['title' => 'Local']],
            'fresh' => ['podcasts_page' => []],
            'expected' => ['podcasts_page' => ['title' => 'Local']],
            'conflict' => null,
        ],
        'fresh adds the first key while local is unchanged' => [
            'opened' => ['podcasts_page' => []],
            'local' => ['podcasts_page' => []],
            'fresh' => ['podcasts_page' => ['title' => 'Fresh']],
            'expected' => ['podcasts_page' => ['title' => 'Fresh']],
            'conflict' => null,
        ],
        'local and fresh add disjoint keys' => [
            'opened' => ['podcasts_page' => []],
            'local' => ['podcasts_page' => ['title' => 'Local']],
            'fresh' => ['podcasts_page' => ['description' => 'Fresh']],
            'expected' => ['podcasts_page' => [
                'description' => 'Fresh',
                'title' => 'Local',
            ]],
            'conflict' => null,
        ],
        'local and fresh remove disjoint keys' => [
            'opened' => ['podcasts_page' => [
                'description' => 'Old description',
                'title' => 'Old title',
            ]],
            'local' => ['podcasts_page' => ['description' => 'Old description']],
            'fresh' => ['podcasts_page' => ['title' => 'Old title']],
            'expected' => ['podcasts_page' => []],
            'conflict' => null,
        ],
        'local removes while fresh changes the same key' => [
            'opened' => ['podcasts_page' => ['title' => 'Old']],
            'local' => ['podcasts_page' => []],
            'fresh' => ['podcasts_page' => ['title' => 'Fresh']],
            'expected' => ['podcasts_page' => ['title' => 'Fresh']],
            'conflict' => 'podcasts_page.title',
        ],
    ];

    foreach ($cases as $name => $case) {
        $merge = mergeSettingsSubjectPayloads(
            $case['opened'],
            $case['local'],
            $case['fresh'],
        );

        expect($merge['payload'], $name)->toBe($case['expected'])
            ->and($merge['conflict_path'], $name)->toBe($case['conflict']);
    }
});

it('has user-facing bilingual labels for every conflict-reachable subject unit', function (): void {
    $schema = app(SettingsLifecycleSchema::class);
    $editableProperties = collect(SettingsSubjectOwnershipRegistry::all())
        ->filter(fn (array $subject): bool => $subject['editable'])
        ->flatMap(fn (array $subject): array => $subject['properties'])
        ->unique()
        ->values()
        ->all();
    $paths = collect($schema->unitPaths())
        ->filter(fn (string $path): bool => in_array(str($path)->before('.')->toString(), $editableProperties, true))
        ->values()
        ->all();
    $failures = [];

    foreach (['en', 'he'] as $locale) {
        app()->setLocale($locale);

        foreach ($paths as $path) {
            $label = $schema->labelFor($path);

            if ($label === $path || str_contains($label, '.') || str_starts_with($label, 'admin.settings_paths')) {
                $failures[] = "{$locale}:{$path}=>{$label}";
            }
        }
    }

    app()->setLocale(config('app.locale'));

    expect($failures)->toBe([]);
});

it('maps validator semantic findings to lifecycle units', function (): void {
    $payload = [
        'route_labels' => [
            [
                'route_key' => 'home',
                'label' => '<script>bad</script>',
            ],
            [
                'route_key' => 'unknown-route',
                'label' => 'Unknown',
            ],
        ],
        'card_templates' => [
            [
                'key' => 'content_item_default',
                'family' => 'content_item',
                'label' => '<script>bad</script>',
                'layout' => 'cards',
                'density' => 'comfortable',
                'image_size' => 'medium',
                'title_size' => 'base',
                'parts' => [],
            ],
        ],
        'public_forms' => [
            'require_email_verification' => false,
            'definitions' => [
                [
                    'key' => 'contact',
                    'name' => 'Contact',
                    'enabled' => false,
                    'fields' => [
                        [
                            'key' => 'message',
                            'type' => 'text',
                            'label' => 'Message',
                            'min_length' => 20,
                            'max_length' => 10,
                        ],
                    ],
                ],
            ],
        ],
    ];
    $result = app(PublicFrontConfigValidator::class)->validate($payload);
    $schema = app(SettingsLifecycleSchema::class);
    $mapped = collect($result->invalidConfig())
        ->mapWithKeys(fn ($finding): array => [
            $finding->path => $schema->unitPathsForSemanticPath($finding->path, $payload),
        ]);

    expect($mapped->get('route_labels.0.label'))->toBe(['route_labels.home'])
        ->and($mapped->get('route_labels.1.route_key'))->toBe([])
        ->and($mapped->get('card_templates.0.label'))->toBe(['card_templates.content_item'])
        ->and($mapped->get('public_forms.definitions.0.fields.0.max_length'))
        ->toBe(['public_forms.definitions'])
        ->and($schema->labelFor('route_labels'))->not->toBe('route_labels');
});

it('omits only null and blank optional numerics while accepting only integer-like values', function (): void {
    $validator = app(PublicFrontConfigValidator::class);

    foreach ([null, '', '   '] as $omitted) {
        $config = task4PublicFormsConfig();
        $config['definitions'][0]['fields'][0]['min_length'] = $omitted;
        $result = $validator->validateGroups(
            ['public_forms' => $config],
            ['public_forms'],
        );

        expect($result->invalidConfig())->toBe([])
            ->and($result->group('public_forms')['definitions'][0]['fields'][0])
            ->not->toHaveKey('min_length');
    }

    $zero = task4PublicFormsConfig();
    $zero['definitions'][0]['fields'][0]['min_length'] = 0;
    $zeroResult = $validator->validateGroups(
        ['public_forms' => $zero],
        ['public_forms'],
    );
    $integerString = task4PublicFormsConfig();
    $integerString['definitions'][0]['fields'][0]['min_length'] = '12';
    $integerStringResult = $validator->validateGroups(
        ['public_forms' => $integerString],
        ['public_forms'],
    );
    $forged = task4PublicFormsConfig();
    $forged['definitions'][0]['fields'][0]['min_length'] = [];
    $forgedResult = $validator->validateGroups(
        ['public_forms' => $forged],
        ['public_forms'],
    );
    $invalidIntegerResults = collect([1.5, '1.5', true, false, [], new stdClass, 'not-a-number'])
        ->map(function (mixed $invalidInteger) use ($validator) {
            $config = task4PublicFormsConfig();
            $config['definitions'][0]['fields'][0]['min_length'] = $invalidInteger;

            return $validator->validateGroups(
                ['public_forms' => $config],
                ['public_forms'],
            );
        });

    expect($zeroResult->invalidConfig())->toBe([])
        ->and($zeroResult->group('public_forms')['definitions'][0]['fields'][0]['min_length'])
        ->toBe(0)
        ->and($integerStringResult->invalidConfig())->toBe([])
        ->and($integerStringResult->group('public_forms')['definitions'][0]['fields'][0]['min_length'])
        ->toBe(12)
        ->and(collect($forgedResult->invalidConfig())->contains(
            fn ($finding): bool => $finding->path === 'public_forms.definitions.0.fields.0.min_length'
                && $finding->reason === 'expected_integer',
        ))->toBeTrue()
        ->and($invalidIntegerResults->every(
            fn ($result): bool => collect($result->invalidConfig())->contains(
                fn ($finding): bool => $finding->path === 'public_forms.definitions.0.fields.0.min_length'
                    && $finding->reason === 'expected_integer',
            ),
        ))->toBeTrue();
});

it('normalizes only declared integer field transport values', function (): void {
    $admin = User::factory()->admin()->create();
    $integerComponent = Livewire::actingAs($admin)
        ->test(HomepageSettings::class);
    $integerFields = collect($integerComponent->instance()->getSchema('form')->getFlatComponents(
        withActions: false,
        withHidden: true,
        withAbsoluteKeys: true,
    ));
    $integerField = $integerFields->first(fn (mixed $field): bool => $field instanceof TextInput
        && $field->getStatePath(isAbsolute: false) === 'homepage_item_limit');
    $component = Livewire::actingAs($admin)
        ->test(IntegerTransportProbeSettingsPage::class);
    $fields = collect($component->instance()->getSchema('form')->getFlatComponents(
        withActions: false,
        withHidden: true,
        withAbsoluteKeys: true,
    ));
    $mediaIdentity = $fields->first(fn (mixed $field): bool => $field instanceof TextInput
        && $field->getStatePath(isAbsolute: false) === 'menu_config.logo.light_media_reference_key');
    $unrelatedRatio = $fields->first(fn (mixed $field): bool => $field instanceof TextInput
        && $field->getStatePath(isAbsolute: false) === 'unrelated_ratio');

    expect($integerField)->toBeInstanceOf(TextInput::class)
        ->and(array_values($integerField->getStateToDehydrate(3.0))[0])->toBe(3)
        ->and(array_values($mediaIdentity->getStateToDehydrate(42.0))[0])->toBe(42.0)
        ->and(array_values($unrelatedRatio->getStateToDehydrate(2.0))[0])->toBe(2.0)
        ->and((new ReflectionClass(PublicContentSettingsSubjectPage::class))
            ->hasMethod('normalizeFilamentWholeNumberState'))->toBeFalse();
});

it('keeps integer field transport conversion inside safe runtime bounds', function (): void {
    $roundedUpperBoundary = (float) PHP_INT_MAX;
    $roundedLowerBoundary = (float) PHP_INT_MIN;
    $upperResult = IntegerTextInputState::dehydrate($roundedUpperBoundary);

    expect(IntegerTextInputState::dehydrate(3.0))->toBe(3)
        ->and(IntegerTextInputState::dehydrate(1.5))->toBe(1.5)
        ->and($upperResult)->toBeFloat()
        ->and($upperResult)->toBe($roundedUpperBoundary)
        ->and(IntegerTextInputState::dehydrate($roundedLowerBoundary))->toBe(PHP_INT_MIN);
});

it('passes a whole float media identity unchanged to strict admission and rejects it without persistence', function (): void {
    $admin = User::factory()->admin()->create();
    $component = Livewire::actingAs($admin)
        ->test(MediaFloatAdmissionProbePage::class);
    $pending = $component->instance()->getSchema('form')->getState();
    data_set($pending, 'menu_config.logo.light_media_reference_key', 42.0);
    $validation = app(PublicFrontConfigValidator::class)->validateGroups(
        $pending,
        SettingsSubjectOwnershipRegistry::validatorGroups(SettingsSubjectOwnershipRegistry::MENU_HEADER),
    );
    $storedBefore = app(PublicContentSettings::class)
        ->getRepository()
        ->getPropertiesInGroup(PublicContentSettings::group());

    expect(data_get($pending, 'menu_config.logo.light_media_reference_key'))->toBe(42.0)
        ->and(collect($validation->invalidConfig())->contains(
            fn ($finding): bool => $finding->path === 'menu_config.logo.light_media_reference_key'
                && $finding->reason === 'expected_string',
        ))->toBeTrue();

    Event::fake([SettingsSaved::class]);

    $instance = $component->instance();

    try {
        $instance->admitUnadaptedPayload($pending);
        $this->fail('Strict settings admission should halt for a whole-float media identity.');
    } catch (Halt) {
        // The page reports the invalid lifecycle unit before persistence.
    }

    expect($instance->getErrorBag()->keys())->toContain('data.menu_config.logo')
        ->and($instance->pendingMediaIdentity())->toBe(42.0);
    Event::assertNotDispatched(SettingsSaved::class);
    clearPublicFrontSettingsCache();

    expect(app(PublicContentSettings::class)
        ->getRepository()
        ->getPropertiesInGroup(PublicContentSettings::group()))
        ->toBe($storedBefore);
});

it('halts a locally changed invalid nested settings unit without writing any setting', function (): void {
    $settings = app(PublicContentSettings::class);
    $settings->public_forms = task4PublicFormsConfig();
    $settings->save();
    clearPublicFrontSettingsCache();

    $component = Livewire::actingAs(User::factory()->admin()->create())
        ->test(ManagePublicForms::class)
        ->set('data.public_forms.definitions', [
            [
                'key' => 'contact',
                'name' => 'Contact',
                'heading' => 'Contact',
                'description' => null,
                'submit_label' => 'Send',
                'success_message' => 'Sent',
                'enabled' => false,
                'display_mode_default' => 'modal',
                'fields' => [
                    [
                        'type' => 'text',
                        'data' => [
                            'key' => 'message',
                            'label' => 'Message',
                            'required' => false,
                            'options' => [],
                            'validation_semantics' => 'none',
                            'min_length' => 20,
                            'max_length' => 10,
                        ],
                    ],
                ],
                'settings' => [
                    'rate_limit_attempts' => 5,
                    'rate_limit_decay_seconds' => 600,
                    'submitter_email_verification' => 'off',
                ],
            ],
        ]);
    $storedBefore = app(PublicContentSettings::class)
        ->getRepository()
        ->getPropertiesInGroup(PublicContentSettings::group());

    Event::fake([SettingsSaved::class]);

    $component
        ->call('save')
        ->assertHasFormErrors(['public_forms.definitions'])
        ->assertNotified(__('admin.validation.settings_unit_invalid', [
            'unit' => app(SettingsLifecycleSchema::class)->labelFor('public_forms.definitions'),
        ]));

    Event::assertNotDispatched(SettingsSaved::class);
    clearPublicFrontSettingsCache();

    expect(app(PublicContentSettings::class)
        ->getRepository()
        ->getPropertiesInGroup(PublicContentSettings::group()))
        ->toBe($storedBefore);
});

it('rejects a forged upload path on a subject settings page before settings writes', function (): void {
    $component = Livewire::actingAs(User::factory()->admin()->create())
        ->test(HomepageSettings::class);
    $storedBefore = app(PublicContentSettings::class)
        ->getRepository()
        ->getPropertiesInGroup(PublicContentSettings::group());

    Event::fake([SettingsSaved::class]);

    $component
        ->call('_startUpload', 'data.forged_upload', [], false)
        ->assertForbidden();

    Event::assertNotDispatched(SettingsSaved::class);
    clearPublicFrontSettingsCache();

    expect(app(PublicContentSettings::class)
        ->getRepository()
        ->getPropertiesInGroup(PublicContentSettings::group()))
        ->toBe($storedBefore);
});

it('keeps top-level public-form clones collision-free and disabled without changing nested identities', function (): void {
    $settings = app(PublicContentSettings::class);
    $settings->public_forms = task6SelectablePublicFormsConfig();
    $settings->save();
    clearPublicFrontSettingsCache();

    $component = Livewire::actingAs(User::factory()->admin()->create())
        ->test(ManagePublicForms::class);
    $definitions = $component->get('data.public_forms.definitions');
    $definitionItem = array_key_first($definitions);
    $schemaComponents = collect($component->instance()->getSchema('form')->getFlatComponents(
        withActions: false,
        withHidden: true,
        withAbsoluteKeys: true,
    ));
    $definitionsRepeater = $schemaComponents->first(
        fn (mixed $field): bool => $field instanceof Repeater
            && $field->getStatePath(isAbsolute: false) === 'public_forms.definitions',
    );
    assert($definitionsRepeater instanceof Repeater);

    $component->callAction(TestAction::make('clonePublicForm')
        ->schemaComponent((string) str($definitionsRepeater->getKey())->after('.'))
        ->arguments(['item' => $definitionItem]));
    $clonedDefinitions = array_values($component->get('data.public_forms.definitions'));

    expect($clonedDefinitions)->toHaveCount(2)
        ->and($clonedDefinitions[0]['key'])->toBe('contact')
        ->and($clonedDefinitions[1]['key'])->not->toBe('contact')
        ->and($clonedDefinitions[1]['key'])->toMatch('/^[a-z][a-z0-9_-]*$/')
        ->and($clonedDefinitions[1]['enabled'])->toBeFalse()
        ->and($clonedDefinitions[1]['fields'][0]['data']['key'])->toBe('topic')
        ->and($clonedDefinitions[1]['fields'][0]['data']['options'][0]['value'])->toBe('support');
});

it('clears only a cloned public-form field identity', function (): void {
    $settings = app(PublicContentSettings::class);
    $settings->public_forms = task6SelectablePublicFormsConfig();
    $settings->save();
    clearPublicFrontSettingsCache();

    $component = Livewire::actingAs(User::factory()->admin()->create())
        ->test(ManagePublicForms::class);
    $definitions = $component->get('data.public_forms.definitions');
    $definitionItem = array_key_first($definitions);
    $fieldItem = array_key_first($definitions[$definitionItem]['fields']);
    $schemaComponents = collect($component->instance()->getSchema('form')->getFlatComponents(
        withActions: false,
        withHidden: true,
        withAbsoluteKeys: true,
    ));
    $fieldsBuilder = $schemaComponents->first(
        fn (mixed $field): bool => $field instanceof Builder
            && $field->getStatePath(isAbsolute: false) === 'fields',
    );
    assert($fieldsBuilder instanceof Builder);

    $component->callAction(TestAction::make('clone')
        ->schemaComponent((string) str($fieldsBuilder->getKey())->after('.'))
        ->arguments(['item' => $fieldItem]));
    $fields = array_values($component->get("data.public_forms.definitions.{$definitionItem}.fields"));
    $clonedOptions = array_values($fields[1]['data']['options']);

    expect($fields)->toHaveCount(2)
        ->and($fields[0]['data']['key'])->toBe('topic')
        ->and($fields[1]['data']['key'])->toBeNull()
        ->and($fields[1]['data']['label'])->toBe('Topic')
        ->and($clonedOptions[0]['value'])->toBe('support');
});

it('clears only a cloned public-form option identity', function (): void {
    $settings = app(PublicContentSettings::class);
    $settings->public_forms = task6SelectablePublicFormsConfig();
    $settings->save();
    clearPublicFrontSettingsCache();

    $component = Livewire::actingAs(User::factory()->admin()->create())
        ->test(ManagePublicForms::class);
    $definitions = $component->get('data.public_forms.definitions');
    $definitionItem = array_key_first($definitions);
    $fieldItem = array_key_first($definitions[$definitionItem]['fields']);
    $optionItem = array_key_first($definitions[$definitionItem]['fields'][$fieldItem]['data']['options']);
    $schemaComponents = collect($component->instance()->getSchema('form')->getFlatComponents(
        withActions: false,
        withHidden: true,
        withAbsoluteKeys: true,
    ));
    $optionsRepeater = $schemaComponents->first(
        fn (mixed $field): bool => $field instanceof Repeater
            && $field->getStatePath(isAbsolute: false) === 'options',
    );
    assert($optionsRepeater instanceof Repeater);

    $component->callAction(TestAction::make('clone')
        ->schemaComponent((string) str($optionsRepeater->getKey())->after('.'))
        ->arguments(['item' => $optionItem]));
    $options = array_values($component->get(
        "data.public_forms.definitions.{$definitionItem}.fields.{$fieldItem}.data.options",
    ));

    expect($options)->toHaveCount(2)
        ->and($options[0]['value'])->toBe('support')
        ->and($options[1]['value'])->toBeNull()
        ->and($options[1]['label'])->toBe('Support');
});

it('requires distinct public-form field and option identities before settings writes', function (): void {
    $settings = app(PublicContentSettings::class);
    $settings->public_forms = task6SelectablePublicFormsConfig();
    $settings->save();
    clearPublicFrontSettingsCache();

    $component = Livewire::actingAs(User::factory()->admin()->create())
        ->test(ManagePublicForms::class);
    $definitions = $component->get('data.public_forms.definitions');
    $definitionItem = array_key_first($definitions);
    $fieldItem = array_key_first($definitions[$definitionItem]['fields']);
    $field = $definitions[$definitionItem]['fields'][$fieldItem];
    $field['data']['options'][] = [
        'value' => 'support',
        'label' => 'Duplicate support',
    ];
    $definitions[$definitionItem]['fields'] = [$field, $field];
    $storedBefore = app(PublicContentSettings::class)
        ->getRepository()
        ->getPropertiesInGroup(PublicContentSettings::group());

    Event::fake([SettingsSaved::class]);

    $component
        ->set('data.public_forms.definitions', $definitions)
        ->call('save');
    $errorPaths = $component->instance()->getErrorBag()->keys();

    expect(collect($errorPaths)->filter(
        fn (string $path): bool => str_starts_with($path, 'data.public_forms.definitions.')
            && str_ends_with($path, '.data.key'),
    ))->toHaveCount(2)
        ->and(collect($errorPaths)->filter(
            fn (string $path): bool => str_starts_with($path, 'data.public_forms.definitions.')
                && str_ends_with($path, '.value'),
        ))->toHaveCount(4);
    Event::assertNotDispatched(SettingsSaved::class);
    clearPublicFrontSettingsCache();

    expect(app(PublicContentSettings::class)
        ->getRepository()
        ->getPropertiesInGroup(PublicContentSettings::group()))
        ->toBe($storedBefore);
});

it('keeps the post-save editable baseline canonical across a second unchanged save', function (): void {
    $settings = app(PublicContentSettings::class);
    $settings->public_forms = task4PublicFormsConfig();
    $settings->save();
    clearPublicFrontSettingsCache();

    $component = Livewire::actingAs(User::factory()->admin()->create())
        ->test(ManagePublicForms::class)
        ->set('data.public_forms.require_email_verification', true)
        ->call('save')
        ->assertHasNoFormErrors();

    $freshPublicForms = task4PublicFormsConfig();
    $freshPublicForms['require_email_verification'] = true;
    $freshPublicForms['definitions'][0]['name'] = 'Changed elsewhere after first save';
    saveConcurrentPublicContentSettings(['public_forms' => $freshPublicForms]);

    Event::fake([SettingsSaved::class]);

    $component
        ->call('save')
        ->assertHasNoFormErrors();

    Event::assertDispatchedTimes(SettingsSaved::class, 1);
    clearPublicFrontSettingsCache();

    expect(app(PublicContentSettings::class)->public_forms['definitions'][0]['name'])
        ->toBe('Changed elsewhere after first save');
});

it('rejects a forged array in an optional numeric field without writing settings', function (): void {
    $settings = app(PublicContentSettings::class);
    $settings->public_forms = task4PublicFormsConfig();
    $settings->save();
    clearPublicFrontSettingsCache();
    $component = Livewire::actingAs(User::factory()->admin()->create())
        ->test(ManagePublicForms::class);
    $definitions = $component->get('data.public_forms.definitions');
    $definitionKey = array_key_first($definitions);
    $fieldKey = array_key_first($definitions[$definitionKey]['fields']);
    $numericPath = "data.public_forms.definitions.{$definitionKey}.fields.{$fieldKey}.data.min_length";
    $storedBefore = app(PublicContentSettings::class)
        ->getRepository()
        ->getPropertiesInGroup(PublicContentSettings::group());

    Event::fake([SettingsSaved::class]);

    $component
        ->set($numericPath, [])
        ->call('save')
        ->assertSet($numericPath, []);

    expect($component->instance()->getErrorBag()->keys())->not->toBeEmpty();
    Event::assertNotDispatched(SettingsSaved::class);
    clearPublicFrontSettingsCache();

    expect(app(PublicContentSettings::class)
        ->getRepository()
        ->getPropertiesInGroup(PublicContentSettings::group()))
        ->toBe($storedBefore);
});

it('halts a same-unit edit when stored optional numeric data is fractional', function (): void {
    $publicForms = task4PublicFormsConfig();
    $publicForms['definitions'][0]['fields'][0]['min_length'] = 1.5;
    $settings = app(PublicContentSettings::class);
    $settings->public_forms = $publicForms;
    $settings->save();
    clearPublicFrontSettingsCache();
    $component = Livewire::actingAs(User::factory()->admin()->create())
        ->test(ManagePublicForms::class);
    $definitions = $component->get('data.public_forms.definitions');
    $definitionKey = array_key_first($definitions);
    $namePath = "data.public_forms.definitions.{$definitionKey}.name";
    $storedBefore = app(PublicContentSettings::class)
        ->getRepository()
        ->getPropertiesInGroup(PublicContentSettings::group());

    Event::fake([SettingsSaved::class]);

    $component
        ->set($namePath, 'Pending same-unit edit')
        ->call('save')
        ->assertHasFormErrors(['public_forms.definitions'])
        ->assertSet($namePath, 'Pending same-unit edit')
        ->assertNotified(__('admin.validation.settings_unit_invalid', [
            'unit' => app(SettingsLifecycleSchema::class)->labelFor('public_forms.definitions'),
        ]));

    Event::assertNotDispatched(SettingsSaved::class);
    clearPublicFrontSettingsCache();

    expect(app(PublicContentSettings::class)
        ->getRepository()
        ->getPropertiesInGroup(PublicContentSettings::group()))
        ->toBe($storedBefore);
});

it('rejects a known card-template edit when a missing identity makes the whole section unsafe', function (): void {
    $known = PublicFrontCardTemplateRegistry::defaultTemplateForFamily('content_item');
    $known['key'] = 'known-template';
    $known['label'] = 'Known template';
    $invalid = $known;
    $invalid['key'] = 'missing-family';
    $invalid['label'] = 'Missing family';
    unset($invalid['family']);
    $settings = app(PublicContentSettings::class);
    $settings->card_templates = [$known, $invalid];
    $settings->save();
    clearPublicFrontSettingsCache();
    $component = Livewire::actingAs(User::factory()->admin()->create())
        ->test(CardTemplatesSubjectLifecycleProbePage::class);
    $templates = $component->get('data.card_templates');
    $templateKey = array_key_first($templates);
    $labelPath = "data.card_templates.{$templateKey}.label";
    $storedBefore = app(PublicContentSettings::class)
        ->getRepository()
        ->getPropertiesInGroup(PublicContentSettings::group());

    Event::fake([SettingsSaved::class]);

    $component
        ->set($labelPath, 'Pending known template edit')
        ->call('save')
        ->assertHasFormErrors(['card_templates'])
        ->assertSet($labelPath, 'Pending known template edit')
        ->assertNotified(__('admin.validation.settings_unit_invalid', [
            'unit' => app(SettingsLifecycleSchema::class)->labelFor('card_templates'),
        ]));

    Event::assertNotDispatched(SettingsSaved::class);
    clearPublicFrontSettingsCache();

    expect(app(PublicContentSettings::class)
        ->getRepository()
        ->getPropertiesInGroup(PublicContentSettings::group()))
        ->toBe($storedBefore);
});

it('composes with the native settings lifecycle and refills once after a successful save', function (): void {
    $component = Livewire::actingAs(User::factory()->admin()->create())
        ->test(NativeSettingsLifecycleProbePage::class)
        ->assertSet('beforeFillCalls', 1)
        ->assertSet('afterFillCalls', 1)
        ->assertSet('beforeSaveCalls', 0)
        ->assertSet('afterSaveCalls', 0)
        ->set('data.homepage_item_limit', 19)
        ->call('save')
        ->assertSet('beforeFillCalls', 2)
        ->assertSet('afterFillCalls', 2)
        ->assertSet('beforeSaveCalls', 1)
        ->assertSet('afterSaveCalls', 1)
        ->assertHasNoFormErrors();

    expect((new ReflectionMethod(
        PublicContentSettingsSubjectPage::class,
        'mount',
    ))->getDeclaringClass()->getName())->toBe(SettingsPage::class)
        ->and((new ReflectionMethod(
            PublicContentSettingsSubjectPage::class,
            'fillForm',
        ))->getDeclaringClass()->getName())->toBe(SettingsPage::class)
        ->and($component->get('data.homepage_item_limit'))->toBe(19.0)
        ->and(app(PublicContentSettings::class)->homepage_item_limit)->toBe(19)
        ->and(collect($component->get('settingsOpenedRawUnitBaseline'))
            ->every(fn (array $state): bool => array_keys($state) === ['exists', 'hash']
                && strlen($state['hash']) === 64))->toBeTrue()
        ->and(collect($component->get('settingsOpenedEditableUnitBaseline'))
            ->every(fn (array $state): bool => array_keys($state) === ['exists', 'hash']
                && strlen($state['hash']) === 64))->toBeTrue();
});

it('returns forbidden when an unauthorized subject settings save is invoked', function (): void {
    $component = Livewire::actingAs(User::factory()->admin()->create())
        ->test(HomepageSettings::class);

    $this->actingAs(User::factory()->regularUser()->create());

    $component
        ->call('save')
        ->assertForbidden();
});

it('rolls back a native settings page save when its coordinator lease is lost before commit', function (): void {
    $coordinator = new PublicContentSettingsWriteCoordinator(leaseSeconds: 1, waitSeconds: 0);
    app()->instance(PublicContentSettingsWriteCoordinator::class, $coordinator);

    $component = Livewire::actingAs(User::factory()->admin()->create())
        ->test(HomepageSettings::class)
        ->set('data.homepage_item_limit', 33);

    Event::listen(
        SettingsSaved::class,
        fn () => Carbon::setTestNow(now()->addSeconds(2)),
    );

    try {
        expect(fn () => $component->call('save'))
            ->toThrow(RuntimeException::class, 'lost ownership');
    } finally {
        Carbon::setTestNow();
        clearPublicFrontSettingsCache();
    }

    expect(app(PublicContentSettings::class)->homepage_item_limit)->not->toBe(33);
});

it('preserves a fresh changed unit when the local unit is unchanged', function (): void {
    $component = Livewire::actingAs(User::factory()->admin()->create())
        ->test(HomepageSettings::class);

    saveConcurrentPublicContentSettings([
        'homepage_item_limit' => 31,
    ]);

    $component
        ->set('data.show_latest_section', false)
        ->call('save')
        ->assertHasNoFormErrors();

    clearPublicFrontSettingsCache();

    expect(app(PublicContentSettings::class)->homepage_item_limit)->toBe(31)
        ->and(app(PublicContentSettings::class)->show_latest_section)->toBeFalse();
});

it('persists a local changed unit when the fresh unit is unchanged', function (): void {
    Livewire::actingAs(User::factory()->admin()->create())
        ->test(HomepageSettings::class)
        ->set('data.homepage_item_limit', 27)
        ->call('save')
        ->assertHasNoFormErrors();

    clearPublicFrontSettingsCache();

    expect(app(PublicContentSettings::class)->homepage_item_limit)->toBe(27);
});

it('accepts the same unit changed to the same value locally and concurrently', function (): void {
    $component = Livewire::actingAs(User::factory()->admin()->create())
        ->test(HomepageSettings::class)
        ->set('data.homepage_item_limit', 29);

    saveConcurrentPublicContentSettings([
        'homepage_item_limit' => 29,
    ]);

    $component
        ->call('save')
        ->assertHasNoFormErrors();

    clearPublicFrontSettingsCache();

    expect(app(PublicContentSettings::class)->homepage_item_limit)->toBe(29);
});

it('halts a divergent same-unit edit with zero settings write', function (): void {
    $component = Livewire::actingAs(User::factory()->admin()->create())
        ->test(HomepageSettings::class)
        ->set('data.homepage_item_limit', 23)
        ->set('data.show_latest_section', false);

    saveConcurrentPublicContentSettings([
        'homepage_item_limit' => 37,
    ]);

    Event::fake([SettingsSaved::class]);

    $component->call('save');

    Event::assertNotDispatched(SettingsSaved::class);
    clearPublicFrontSettingsCache();

    expect(app(PublicContentSettings::class)->homepage_item_limit)->toBe(37)
        ->and(app(PublicContentSettings::class)->show_latest_section)->toBeTrue();
});

it('merges disjoint local and fresh lifecycle units', function (): void {
    $component = Livewire::actingAs(User::factory()->admin()->create())
        ->test(HomepageSettings::class)
        ->set('data.pinned_item_limit', 13);

    saveConcurrentPublicContentSettings([
        'homepage_item_limit' => 41,
    ]);

    $component
        ->call('save')
        ->assertHasNoFormErrors();

    clearPublicFrontSettingsCache();

    expect(app(PublicContentSettings::class)->homepage_item_limit)->toBe(41)
        ->and(app(PublicContentSettings::class)->pinned_item_limit)->toBe(13);
});

it('treats a nested lifecycle list as one conflict unit', function (): void {
    $about = PublicFrontConfigRegistry::defaults()['about_page'];
    $about['blocks'] = [];
    saveConcurrentPublicContentSettings(['about_page' => $about]);

    $component = Livewire::actingAs(User::factory()->admin()->create())
        ->test(AboutSettings::class)
        ->set('data.about_page.blocks', [
            [
                'type' => 'heading',
                'data' => [
                    'key' => 'local-heading',
                    'heading' => 'Local heading',
                ],
            ],
        ]);

    $freshAbout = $about;
    $freshAbout['blocks'] = [
        [
            'type' => 'heading',
            'key' => 'fresh-heading',
            'heading' => 'Fresh heading',
            'visible' => true,
            'sort' => 10,
        ],
    ];
    saveConcurrentPublicContentSettings(['about_page' => $freshAbout]);

    Event::fake([SettingsSaved::class]);
    $conflictTitle = __('admin.validation.settings_unit_changed', [
        'unit' => app(SettingsLifecycleSchema::class)->labelFor('about_page.blocks'),
    ]);

    $component
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified($conflictTitle);

    Event::assertNotDispatched(SettingsSaved::class);
    clearPublicFrontSettingsCache();

    // toEqual: native JSON column round-trip (driver storage difference) —
    // mysql's JSON column re-serializes each block's own key order on
    // write; block list order stays enforced.
    expect(app(PublicContentSettings::class)->about_page['blocks'])->toEqual($freshAbout['blocks']);
});

it('holds one stable public content settings lock before writer work', function (): void {
    $first = new PublicContentSettingsWriteCoordinator(waitSeconds: 0);
    $second = new PublicContentSettingsWriteCoordinator(waitSeconds: 0);

    $lockWasHeldBeforeRead = $first->withinLock(function () use ($second): bool {
        try {
            $second->withinLock(fn (): null => null);
        } catch (LockTimeoutException) {
            return app(PublicContentSettings::class)->homepage_item_limit > 0;
        }

        return false;
    });

    expect($lockWasHeldBeforeRead)->toBeTrue();
});

it('releases the public content settings lock after an exception', function (): void {
    $coordinator = new PublicContentSettingsWriteCoordinator(waitSeconds: 0);

    expect(fn () => $coordinator->withinLock(
        fn (): never => throw new RuntimeException('write failed'),
    ))->toThrow(RuntimeException::class, 'write failed');

    expect($coordinator->withinLock(fn (): string => 'released'))->toBe('released');
});

it('refuses concurrent public content settings writer entry while its group lock is held', function (): void {
    $coordinator = new PublicContentSettingsWriteCoordinator(waitSeconds: 0);
    $heldLock = Cache::lock(PublicContentSettingsWriteCoordinator::LOCK_KEY, 30);

    expect($heldLock->get())->toBeTrue();

    try {
        expect(fn () => $coordinator->withinLock(fn (): null => null))
            ->toThrow(LockTimeoutException::class);
    } finally {
        $heldLock->release();
    }
});

it('starts database transactions only after acquiring the public content settings lock', function (): void {
    $coordinator = new PublicContentSettingsWriteCoordinator(waitSeconds: 0);
    $baselineTransactionLevel = DB::transactionLevel();

    $state = $coordinator->transaction(function (): array {
        $competingLock = Cache::lock(PublicContentSettingsWriteCoordinator::LOCK_KEY, 30);

        return [
            'transaction_level' => DB::transactionLevel(),
            'competing_lock_acquired' => $competingLock->get(),
        ];
    });

    expect($state)->toBe([
        'transaction_level' => $baselineTransactionLevel + 1,
        'competing_lock_acquired' => false,
    ]);
});

it('rolls back a coordinated transaction when its settings lock lease is lost before commit', function (): void {
    $coordinator = new PublicContentSettingsWriteCoordinator(leaseSeconds: 1, waitSeconds: 0);
    $settings = app(PublicContentSettings::class);
    $settings->homepage_item_limit = 17;
    $settings->save();
    clearPublicFrontSettingsCache();

    try {
        expect(fn () => $coordinator->transaction(function (): void {
            $settings = app(PublicContentSettings::class);
            $settings->homepage_item_limit = 29;
            $settings->save();
            Carbon::setTestNow(now()->addSeconds(2));
        }))->toThrow(RuntimeException::class, 'lost ownership');
    } finally {
        Carbon::setTestNow();
        clearPublicFrontSettingsCache();
    }

    expect(app(PublicContentSettings::class)->homepage_item_limit)->toBe(17);
});

it('does not report lease loss after a coordinated transaction has already committed', function (): void {
    $coordinator = new PublicContentSettingsWriteCoordinator(leaseSeconds: 1, waitSeconds: 0);
    Event::listen(
        TransactionCommitted::class,
        fn () => Carbon::setTestNow(now()->addSeconds(2)),
    );

    try {
        expect($coordinator->transaction(fn (): string => 'committed'))->toBe('committed');
    } finally {
        Carbon::setTestNow();
    }
});

it('refuses nested acquisition through the same settings writer coordinator', function (): void {
    $coordinator = new PublicContentSettingsWriteCoordinator(waitSeconds: 0);

    expect(fn () => $coordinator->withinLock(
        fn () => $coordinator->withinLock(fn (): null => null),
    ))->toThrow(LogicException::class, 'already owns');
});

it('serializes disjoint card template and import lock writes without losing either change', function (): void {
    $this->actingAs(User::factory()->admin()->create());
    $cardCoordinator = new PublicContentSettingsWriteCoordinator(waitSeconds: 0);
    app()->instance(
        PublicContentSettingsWriteCoordinator::class,
        $cardCoordinator,
    );
    $cardWriter = app(CardTemplateFocusedWriter::class);
    app()->instance(
        PublicContentSettingsWriteCoordinator::class,
        new PublicContentSettingsWriteCoordinator(waitSeconds: 0),
    );
    $importLocks = app(SettingsImportLocks::class);

    $template = PublicFrontCardTemplateRegistry::defaultTemplateForFamily('content_item');
    $template['key'] = 'coordinated-template';
    $template['label'] = 'Before coordinated edit';
    $settings = app(PublicContentSettings::class);
    $settings->card_templates = [$template];
    $settings->save();
    clearPublicFrontSettingsCache();

    $draft = $template;
    $draft['label'] = 'After coordinated edit';
    $fingerprint = app(CardTemplateIdentity::class)->fingerprint($template);

    expect(fn () => $cardWriter->edit(
        $draft,
        'content_item',
        'coordinated-template',
        $fingerprint,
        beforePersist: fn () => $importLocks->save(['homepage_item_limit']),
    ))->toThrow(
        LockTimeoutException::class,
        'Timed out acquiring the public content settings write lock.',
    );

    $importLocks->save(['homepage_item_limit']);
    $cardWriter->edit(
        $draft,
        'content_item',
        'coordinated-template',
        $fingerprint,
    );
    clearPublicFrontSettingsCache();

    expect($importLocks->lockedPaths())->toBe(['homepage_item_limit'])
        ->and(app(PublicContentSettings::class)->card_templates[0]['label'])->toBe('After coordinated edit');
});

it('serializes selected import against a page shaped write and preserves both disjoint changes', function (): void {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    $importCoordinator = new PublicContentSettingsWriteCoordinator(waitSeconds: 0);
    app()->instance(
        PublicContentSettingsWriteCoordinator::class,
        $importCoordinator,
    );

    $settings = app(PublicContentSettings::class);
    $settings->homepage_item_limit = 11;
    $settings->show_latest_section = false;
    $settings->save();
    $package = PublicSettingsPackage::fromCurrentSettings();

    clearPublicFrontSettingsCache();
    $settings = app(PublicContentSettings::class);
    $settings->homepage_item_limit = 23;
    $settings->save();
    clearPublicFrontSettingsCache();

    $pageCoordinator = new PublicContentSettingsWriteCoordinator(waitSeconds: 0);
    $manager = app(SettingsBackupManager::class);

    expect(fn () => $pageCoordinator->transaction(function () use ($manager, $package, $admin): void {
        $settings = app(PublicContentSettings::class);
        $settings->refresh();
        $settings->show_latest_section = true;
        $settings->save();

        $manager->import($package, ['homepage_item_limit'], $admin);
    }))->toThrow(LockTimeoutException::class, 'Timed out acquiring the public content settings write lock.');

    clearPublicFrontSettingsCache();
    expect(app(PublicContentSettings::class)->homepage_item_limit)->toBe(23)
        ->and(app(PublicContentSettings::class)->show_latest_section)->toBeFalse();

    $pageCoordinator->transaction(function (): void {
        $settings = app(PublicContentSettings::class);
        $settings->refresh();
        $settings->show_latest_section = true;
        $settings->save();
    });
    $manager->import($package, ['homepage_item_limit'], $admin);
    clearPublicFrontSettingsCache();

    expect(app(PublicContentSettings::class)->homepage_item_limit)->toBe(11)
        ->and(app(PublicContentSettings::class)->show_latest_section)->toBeTrue();
});

it('refuses backup restore while the public content settings mutex is held', function (): void {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    app()->instance(
        PublicContentSettingsWriteCoordinator::class,
        new PublicContentSettingsWriteCoordinator(waitSeconds: 0),
    );

    $settings = app(PublicContentSettings::class);
    $settings->homepage_item_limit = 11;
    $settings->save();
    $backup = app(SettingsBackupManager::class)->createManual('Coordinated restore source', $admin);
    $heldLock = Cache::lock(PublicContentSettingsWriteCoordinator::LOCK_KEY, 30);
    expect($heldLock->get())->toBeTrue();

    try {
        expect(fn () => app(SettingsBackupManager::class)->restore($backup, $admin))
            ->toThrow(
                LockTimeoutException::class,
                'Timed out acquiring the public content settings write lock.',
            );
    } finally {
        $heldLock->release();
    }
});

it('serializes normalization apply before recomputing its settings candidate', function (): void {
    app()->instance(
        PublicContentSettingsWriteCoordinator::class,
        new PublicContentSettingsWriteCoordinator(waitSeconds: 0),
    );
    DB::table('settings')
        ->where('group', PublicContentSettings::group())
        ->where('name', 'display_defaults')
        ->update([
            'payload' => json_encode(['layout' => 'invalid-layout'], JSON_THROW_ON_ERROR),
        ]);
    clearPublicFrontSettingsCache();

    $heldLock = Cache::lock(PublicContentSettingsWriteCoordinator::LOCK_KEY, 30);
    expect($heldLock->get())->toBeTrue();

    try {
        expect(fn () => $this->artisan('settings:normalize-public-content', ['--apply' => true])->run())
            ->toThrow(LockTimeoutException::class);
    } finally {
        $heldLock->release();
    }
});

it('loads public front defaults when settings rows are missing', function (): void {
    DB::table('settings')->where('group', PublicContentSettings::group())->delete();

    clearPublicFrontSettingsCache();

    $result = app(PublicFrontConfigReader::class)->read();
    $defaults = app(PublicFrontConfigValidator::class)
        ->validate(PublicFrontConfigRegistry::defaults())
        ->config();

    expect($result->config())->toBe($defaults);
});

it('merges nested stored config arrays with defaults', function (): void {
    $result = app(PublicFrontConfigReader::class)->fromArray([
        'display_defaults' => [
            'layout' => 'rows',
        ],
        'item_page' => [
            'dates' => [
                'display' => 'site',
            ],
        ],
        'menu_config' => [
            'enabled' => true,
        ],
    ]);

    $menuConfig = $result->group('menu_config');

    expect($result->group('display_defaults'))->toMatchArray([
        'layout' => 'rows',
        'density' => 'comfortable',
        'image_size' => 'medium',
        'image_fit' => 'cover',
        'image_radius' => 'mid_rounded',
        'title_size' => 'base',
        'page_size' => 12,
        'transcription_display' => 'effective_only',
    ])->and($menuConfig['enabled'])->toBeTrue()
        ->and($result->group('item_page')['dates'])->toMatchArray([
            'display' => 'site',
            'site_published' => [
                'label_mode' => 'long',
                'label_override' => null,
                'icon' => 'OutlinedCalendar',
                'icon_position' => 'inline_before',
            ],
            'original_published' => [
                'label_mode' => 'short',
                'label_override' => null,
                'icon' => 'OutlinedCalendar',
                'icon_position' => 'inline_before',
            ],
        ])
        ->and($menuConfig['items'])->not->toBeEmpty()
        ->and($menuConfig['theme_selector'])->toMatchArray([
            'enabled' => true,
            'mode' => 'light_dark_system',
        ]);
});

it('creates item page settings defaults through the settings migration', function (): void {
    $settings = app(PublicContentSettings::class);

    expect(DB::table('settings')
        ->where('group', PublicContentSettings::group())
        ->where('name', 'item_page')
        ->exists())->toBeTrue()
        ->and($settings->item_page)->toMatchArray(PublicFrontConfigRegistry::defaults()['item_page']);
});

it('backfills item page header settings through the settings migration', function (): void {
    $legacyItemPage = [
        'dates' => [
            'display' => 'site',
        ],
        'badges' => [
            'info' => [
                'size' => 'md',
                'color' => 'primary',
            ],
        ],
    ];

    DB::table('settings')->updateOrInsert(
        [
            'group' => PublicContentSettings::group(),
            'name' => 'item_page',
        ],
        [
            'locked' => false,
            'payload' => json_encode($legacyItemPage),
            'created_at' => now(),
            'updated_at' => now(),
        ],
    );

    $migration = include base_path('database/settings/2026_07_09_000001_add_public_item_page_header_settings.php');
    $migration->up();

    $itemPage = json_decode(
        DB::table('settings')
            ->where('group', PublicContentSettings::group())
            ->where('name', 'item_page')
            ->value('payload'),
        true,
    );

    $infoFields = sortPublicFrontJsonKeys($itemPage['info_fields']);
    $defaultInfoFields = sortPublicFrontJsonKeys(PublicItemPageRegistry::defaultInfoFields());

    expect($itemPage)->toMatchArray([
        'show_breadcrumbs' => true,
        'podcast_identity' => [
            'mode' => 'badge',
            'color' => 'primary',
            'custom_color' => null,
            'icon' => 'OutlinedRectangleGroup',
            'icon_position' => 'inline_before',
            'position' => 'above_title',
            'size' => 'sm',
        ],
        'badges' => [
            'info' => [
                'size' => 'md',
                'color' => 'primary',
                'custom_color' => null,
            ],
        ],
    ])->and($itemPage['dates']['display'])->toBe('site')
        ->and($itemPage['dates']['site_published'])->toMatchArray(
            PublicFrontConfigRegistry::defaults()['item_page']['dates']['site_published'],
        )
        ->and($infoFields)->toBe($defaultInfoFields);
});

it('backfills item page podcast identity presentation settings through the settings migration', function (): void {
    DB::table('settings')->updateOrInsert(
        [
            'group' => PublicContentSettings::group(),
            'name' => 'item_page',
        ],
        [
            'locked' => false,
            'payload' => json_encode([
                'podcast_identity' => [
                    'mode' => 'text',
                    'color' => 'success',
                    'icon' => 'OutlinedRectangleGroup',
                    'icon_position' => 'inline_after',
                ],
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ],
    );

    $migration = include base_path('database/settings/2026_07_09_000002_extend_public_item_page_podcast_identity_settings.php');
    $migration->up();

    $itemPage = json_decode(
        DB::table('settings')
            ->where('group', PublicContentSettings::group())
            ->where('name', 'item_page')
            ->value('payload'),
        true,
    );

    expect($itemPage['podcast_identity'])->toMatchArray([
        'mode' => 'text',
        'color' => 'success',
        'icon' => 'OutlinedRectangleGroup',
        'icon_position' => 'inline_after',
        'position' => 'above_title',
        'size' => 'sm',
    ]);
});

it('backfills item page transcript actions setting through the settings migration', function (): void {
    DB::table('settings')->updateOrInsert(
        [
            'group' => PublicContentSettings::group(),
            'name' => 'item_page',
        ],
        [
            'locked' => false,
            'payload' => json_encode([
                'show_breadcrumbs' => false,
                'show_transcript_actions_menu' => 'yes',
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ],
    );

    $migration = include base_path('database/settings/2026_07_09_000003_add_public_item_page_transcript_actions_setting.php');
    $migration->up();

    $itemPage = json_decode(
        DB::table('settings')
            ->where('group', PublicContentSettings::group())
            ->where('name', 'item_page')
            ->value('payload'),
        true,
    );

    expect($itemPage)->toMatchArray([
        'show_breadcrumbs' => false,
        'show_transcript_actions_menu' => false,
    ]);
});

it('aligns transcription display defaults through the settings migration', function (): void {
    DB::table('settings')->updateOrInsert(
        [
            'group' => PublicContentSettings::group(),
            'name' => 'display_defaults',
        ],
        [
            'locked' => false,
            'payload' => json_encode([
                'layout' => 'cards',
                'transcription_display' => 'effective_plus_count',
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ],
    );

    DB::table('settings')->updateOrInsert(
        [
            'group' => PublicContentSettings::group(),
            'name' => 'podcasts_page',
        ],
        [
            'locked' => false,
            'payload' => json_encode([
                'group_page' => [
                    'transcription_display' => 'effective_plus_count',
                ],
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ],
    );

    DB::table('settings')->updateOrInsert(
        [
            'group' => PublicContentSettings::group(),
            'name' => 'contributors_page',
        ],
        [
            'locked' => false,
            'payload' => json_encode([
                'directory' => [
                    'transcription_display' => 'effective_plus_count',
                ],
                'top_transcribers' => [
                    'transcription_display' => 'effective_plus_count',
                ],
                'page' => [
                    'transcription_display' => 'effective_plus_count',
                ],
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ],
    );

    $migration = include base_path('database/settings/2026_07_09_000004_align_public_transcription_display_defaults.php');
    $migration->up();

    $displayDefaults = json_decode(
        DB::table('settings')
            ->where('group', PublicContentSettings::group())
            ->where('name', 'display_defaults')
            ->value('payload'),
        true,
    );
    $podcastsPage = json_decode(
        DB::table('settings')
            ->where('group', PublicContentSettings::group())
            ->where('name', 'podcasts_page')
            ->value('payload'),
        true,
    );
    $contributorsPage = json_decode(
        DB::table('settings')
            ->where('group', PublicContentSettings::group())
            ->where('name', 'contributors_page')
            ->value('payload'),
        true,
    );

    expect($displayDefaults['transcription_display'])->toBe('effective_only')
        ->and($podcastsPage['group_page']['transcription_display'])->toBe('effective_only')
        ->and($contributorsPage['directory']['transcription_display'])->toBe('effective_only')
        ->and($contributorsPage['top_transcribers']['transcription_display'])->toBe('effective_only')
        ->and($contributorsPage['page']['transcription_display'])->toBe('effective_only');
});

it('normalizes item page date and badge settings safely', function (): void {
    $result = app(PublicFrontConfigValidator::class)->validate([
        'item_page' => [
            'show_breadcrumbs' => 'yes',
            'show_transcript_actions_menu' => '<script>alert(1)</script>',
            'podcast_identity' => [
                'mode' => 'pill',
                'color' => 'bg-red-500',
                'icon' => 'App\\Icons\\Unsafe',
                'icon_position' => 'after',
                'position' => 'inside-title',
                'size' => 'huge',
                'class' => 'rounded-full',
            ],
            'info_fields' => [
                [
                    'field' => 'site_published_date',
                    'label_mode' => 'long',
                    'label_override' => 'Published locally',
                    'icon' => 'OutlinedCalendar',
                    'icon_position' => 'inline_before',
                    'size' => 'sm',
                    'color' => 'primary',
                ],
                [
                    'field' => 'raw_html',
                    'label_mode' => 'verbose',
                    'label_override' => '<b>Bad</b>',
                    'icon' => 'heroicon-o-calendar',
                    'icon_position' => 'beside',
                    'size' => 'xl',
                    'color' => '#fff',
                    'class' => 'text-red-500',
                ],
            ],
            'dates' => [
                'display' => 'p-4 text-red-500',
                'raw_classes' => 'gap-4',
                'site_published' => [
                    'label_mode' => 'verbose',
                    'label_override' => '<script>alert(1)</script>',
                    'icon' => 'heroicon-o-calendar',
                    'icon_position' => 'before',
                    'raw_classes' => 'text-red-500',
                ],
                'original_published' => 'bad-value',
                'transcription_date' => [
                    'enabled' => 'yes',
                    'label_mode' => 'hidden',
                    'icon' => 'OutlinedDocumentText',
                    'icon_position' => 'after',
                ],
            ],
            'badges' => [
                'info' => [
                    'size' => 'xl',
                    'color' => '#fff',
                    'class' => 'bg-red-500',
                ],
            ],
        ],
    ]);

    $itemPage = $result->group('item_page');
    $paths = collect($result->invalidConfig())
        ->map(fn ($invalidConfig): string => $invalidConfig->path)
        ->all();

    expect($itemPage['dates']['display'])->toBe('both')
        ->and($itemPage['show_breadcrumbs'])->toBeTrue()
        ->and($itemPage['show_transcript_actions_menu'])->toBeFalse()
        ->and($itemPage['podcast_identity'])->toMatchArray([
            'mode' => 'badge',
            'color' => 'primary',
            'icon' => 'OutlinedRectangleGroup',
            'icon_position' => 'inline_after',
            'position' => 'above_title',
            'size' => 'sm',
        ])
        ->and($itemPage['info_fields'])->toHaveCount(2)
        ->and($itemPage['info_fields'][0])->toMatchArray([
            'field' => 'site_published_date',
            'label_mode' => 'long',
            'label_override' => 'Published locally',
            'icon' => 'OutlinedCalendar',
            'icon_position' => 'inline_before',
            'size' => 'sm',
            'color' => 'primary',
        ])
        ->and($itemPage['info_fields'][1])->toMatchArray([
            'field' => 'duration',
            'label_mode' => 'hidden',
            'label_override' => null,
            'icon' => 'OutlinedDocumentText',
            'icon_position' => 'inline_before',
            'size' => 'sm',
            'color' => 'gray',
        ])
        ->and($itemPage['dates']['site_published'])->toMatchArray([
            'label_mode' => 'long',
            'label_override' => null,
            'icon' => 'OutlinedCalendar',
            'icon_position' => 'inline_before',
        ])
        ->and($itemPage['dates']['original_published'])->toMatchArray(
            PublicFrontConfigRegistry::defaults()['item_page']['dates']['original_published'],
        )
        ->and($itemPage['dates']['transcription_date'])->toMatchArray([
            'enabled' => true,
            'label_mode' => 'hidden',
            'icon' => 'OutlinedDocumentText',
            'icon_position' => 'inline_after',
        ])
        ->and($itemPage['badges']['info'])->toMatchArray([
            'size' => 'sm',
            'color' => 'gray',
        ])
        ->and($paths)->toContain('item_page.show_breadcrumbs')
        ->and($paths)->toContain('item_page.show_transcript_actions_menu')
        ->and($paths)->toContain('item_page.podcast_identity.mode')
        ->and($paths)->toContain('item_page.podcast_identity.color')
        ->and($paths)->toContain('item_page.podcast_identity.icon')
        ->and($paths)->toContain('item_page.podcast_identity.position')
        ->and($paths)->toContain('item_page.podcast_identity.size')
        ->and($paths)->toContain('item_page.podcast_identity.class')
        ->and($paths)->toContain('item_page.info_fields.1.field')
        ->and($paths)->toContain('item_page.info_fields.1.label_mode')
        ->and($paths)->toContain('item_page.info_fields.1.label_override')
        ->and($paths)->toContain('item_page.info_fields.1.icon')
        ->and($paths)->toContain('item_page.info_fields.1.icon_position')
        ->and($paths)->toContain('item_page.info_fields.1.size')
        ->and($paths)->toContain('item_page.info_fields.1.color')
        ->and($paths)->toContain('item_page.info_fields.1.class')
        ->and($paths)->toContain('item_page.dates.display')
        ->and($paths)->toContain('item_page.dates.raw_classes')
        ->and($paths)->toContain('item_page.dates.site_published.label_mode')
        ->and($paths)->toContain('item_page.dates.site_published.label_override')
        ->and($paths)->toContain('item_page.dates.site_published.icon')
        ->and($paths)->toContain('item_page.dates.site_published.raw_classes')
        ->and($paths)->toContain('item_page.dates.original_published')
        ->and($paths)->toContain('item_page.dates.transcription_date.enabled')
        ->and($paths)->toContain('item_page.badges.info.size')
        ->and($paths)->toContain('item_page.badges.info.color')
        ->and($paths)->toContain('item_page.badges.info.class');
});

it('reports unknown top level and nested keys safely', function (): void {
    $result = app(PublicFrontConfigValidator::class)->validate([
        'unexpected_group' => [],
        'display_defaults' => [
            'layout' => 'cards',
            'raw_classes' => 'p-4',
        ],
        'menu_config' => [
            'enabled' => true,
            'debug' => true,
        ],
    ]);

    $paths = collect($result->invalidConfig())
        ->map(fn ($invalidConfig): string => $invalidConfig->path)
        ->all();

    expect($paths)->toContain('unexpected_group')
        ->and($paths)->toContain('display_defaults.raw_classes')
        ->and($paths)->toContain('menu_config.debug');
});

it('rejects unsafe class css sql blade html and javascript values', function (): void {
    $result = app(PublicFrontConfigValidator::class)->validate([
        'display_defaults' => [
            'layout' => 'p-4 text-red-500',
        ],
        'menu_config' => [
            'items' => [
                [
                    'route_key' => 'home',
                    'label' => '<iframe src="https://example.com"></iframe>',
                ],
                [
                    'external_url' => 'javascript:alert(1)',
                    'label' => 'Bad URL',
                ],
                [
                    'route_key' => 'search',
                    'label' => 'Search',
                    'view' => 'resources/views/public/card.blade.php',
                ],
            ],
        ],
        'about_page' => [
            'blocks' => [
                [
                    'key' => 'intro',
                    'label' => 'App\\Filament\\Pages\\PublicContentSettings::class',
                ],
            ],
        ],
        'public_forms' => [
            [
                'key' => 'contact',
                'label' => 'select * from users where id = 1',
            ],
        ],
    ]);

    $paths = collect($result->invalidConfig())
        ->map(fn ($invalidConfig): string => $invalidConfig->path)
        ->all();

    expect($result->group('display_defaults')['layout'])->toBe('cards')
        ->and(collect($result->group('menu_config')['items'])->pluck('route_key')->filter()->values()->all())->toBe(['home', 'search'])
        ->and($paths)->toContain('display_defaults.layout')
        ->and($paths)->toContain('menu_config.items.0.label')
        ->and($paths)->toContain('menu_config.items.1.external_url')
        ->and($paths)->toContain('menu_config.items.2.view')
        ->and($paths)->toContain('about_page.blocks.0.label')
        ->and($paths)->toContain('public_forms.definitions.0.name');
});

it('saves sanitized public front config through the settings page while preserving card settings', function (): void {
    $this->actingAs(User::factory()->create());

    Livewire::test(HomepageSettings::class)
        ->set('data.homepage_item_limit', 9)
        ->set('data.pinned_item_limit', 4)
        ->set('data.show_latest_section', false)
        ->call('save')
        ->assertHasNoFormErrors();

    Livewire::test(DisplaySettings::class)
        ->set('data.default_public_sort', 'title_desc')
        ->set('data.default_result_layout', 'rows')
        ->set('data.homepage_card_image_size', 'large')
        ->set('data.homepage_card_density', 'compact')
        ->set('data.homepage_card_title_size', 'lg')
        ->set('data.homepage_show_group_badge', false)
        ->set('data.homepage_show_authors', false)
        ->set('data.homepage_show_categories', false)
        ->set('data.homepage_show_tags', false)
        ->set('data.homepage_show_duration', false)
        ->set('data.homepage_show_effective_date', false)
        ->set('data.homepage_show_description', false)
        ->set('data.homepage_description_lines', 1)
        ->set('data.homepage_cards_per_page', 7)
        ->set('data.display_defaults.layout', 'rows')
        ->set('data.display_defaults.density', 'compact')
        ->set('data.display_defaults.image_size', 'large')
        ->set('data.display_defaults.title_size', 'lg')
        ->set('data.display_defaults.page_size', 16)
        ->call('save')
        ->assertHasNoFormErrors();

    Livewire::test(EpisodePageSettings::class)
        ->set('data.item_page_layout', 'media_first')
        ->set('data.item_page.show_breadcrumbs', false)
        ->set('data.item_page.show_transcript_actions_menu', true)
        ->set('data.item_page.podcast_identity.mode', 'text')
        ->set('data.item_page.podcast_identity.color', 'image_2')
        ->set('data.item_page.podcast_identity.size', 'lg')
        ->set('data.item_page.podcast_identity.position', 'title_row_after')
        ->set('data.item_page.podcast_identity.icon', 'podcast')
        ->set('data.item_page.podcast_identity.icon_position', 'inline_after')
        ->set('data.item_page.info_fields', [
            [
                'field' => 'categories',
                'label_mode' => 'long',
                'label_override' => null,
                'icon' => 'OutlinedFolder',
                'icon_position' => 'inline_before',
                'size' => 'sm',
                'color' => 'info',
            ],
            [
                'field' => 'site_published_date',
                'label_mode' => 'short',
                'label_override' => 'Site',
                'icon' => 'OutlinedCalendar',
                'icon_position' => 'inline_after',
                'size' => 'md',
                'color' => 'primary',
            ],
        ])
        ->set('data.item_page.dates.display', 'both')
        ->set('data.item_page.dates.site_published.label_mode', 'long')
        ->set('data.item_page.dates.site_published.label_override', 'Published here')
        ->set('data.item_page.dates.site_published.icon', 'calendar')
        ->set('data.item_page.dates.site_published.icon_position', 'inline_after')
        ->set('data.item_page.dates.original_published.label_mode', 'short')
        ->set('data.item_page.dates.original_published.label_override', null)
        ->set('data.item_page.dates.original_published.icon', 'calendar')
        ->set('data.item_page.dates.original_published.icon_position', 'inline_before')
        ->set('data.item_page.dates.transcription_date.enabled', true)
        ->set('data.item_page.dates.transcription_date.label_mode', 'hidden')
        ->set('data.item_page.dates.transcription_date.label_override', null)
        ->set('data.item_page.dates.transcription_date.icon', 'document')
        ->set('data.item_page.dates.transcription_date.icon_position', 'hidden')
        ->set('data.item_page.badges.info.size', 'md')
        ->set('data.item_page.badges.info.color', 'primary')
        ->call('save')
        ->assertHasNoFormErrors();

    Livewire::test(MenuHeaderSettings::class)
        ->set('data.menu_config.enabled', true)
        ->set('data.route_labels', [
            [
                'route_key' => 'podcasts',
                'label' => 'Podcasts',
            ],
            [
                'route_key' => 'search',
                'label' => 'Search',
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    clearPublicFrontSettingsCache();

    $settings = app(PublicContentSettings::class);
    $config = app(PublicFrontConfigReader::class)->read($settings);
    $cardOptions = PublicContentCardOptions::fromSettings($settings);

    expect($settings->display_defaults)->toMatchArray([
        'layout' => 'rows',
        'density' => 'compact',
        'image_size' => 'large',
        'image_fit' => 'cover',
        'image_radius' => 'mid_rounded',
        'title_size' => 'lg',
        'page_size' => 16,
        'transcription_display' => 'effective_only',
    ])->and($config->group('item_page'))->toMatchArray([
        'show_breadcrumbs' => false,
        'show_transcript_actions_menu' => true,
        'podcast_identity' => [
            'mode' => 'text',
            'color' => 'image_2',
            'custom_color' => null,
            'icon' => 'OutlinedRectangleGroup',
            'icon_position' => 'inline_after',
            'position' => 'title_row_after',
            'size' => 'lg',
        ],
        'info_fields' => [
            [
                'field' => 'categories',
                'label_mode' => 'long',
                'label_override' => null,
                'icon' => 'OutlinedFolder',
                'icon_position' => 'inline_before',
                'size' => 'sm',
                'color' => 'info',
                'custom_color' => null,
            ],
            [
                'field' => 'site_published_date',
                'label_mode' => 'short',
                'label_override' => 'Site',
                'icon' => 'OutlinedCalendar',
                'icon_position' => 'inline_after',
                'size' => 'md',
                'color' => 'primary',
                'custom_color' => null,
            ],
        ],
        'dates' => [
            'display' => 'both',
            'site_published' => [
                'label_mode' => 'long',
                'label_override' => 'Published here',
                'icon' => 'OutlinedCalendar',
                'icon_position' => 'inline_after',
            ],
            'original_published' => [
                'label_mode' => 'short',
                'label_override' => null,
                'icon' => 'OutlinedCalendar',
                'icon_position' => 'inline_before',
            ],
            'transcription_date' => [
                'enabled' => true,
                'label_mode' => 'hidden',
                'label_override' => null,
                'icon' => 'OutlinedDocumentText',
                'icon_position' => 'hidden',
            ],
        ],
        'badges' => [
            'info' => [
                'size' => 'md',
                'color' => 'primary',
                'custom_color' => null,
            ],
        ],
    ])->and($config->group('route_labels'))->toBe([
        [
            'route_key' => 'podcasts',
            'label' => 'Podcasts',
        ],
        [
            'route_key' => 'search',
            'label' => 'Search',
        ],
    ])->and($config->group('menu_config')['enabled'])->toBeTrue()
        ->and($config->group('menu_config')['items'])->not->toBeEmpty()
        ->and($cardOptions->imageSize)->toBe('large')
        ->and($cardOptions->density)->toBe('compact')
        ->and($cardOptions->titleSize)->toBe('lg')
        ->and($cardOptions->transcriptionDisplay)->toBe('effective_only')
        ->and($cardOptions->cardsPerPage)->toBe(7);
});

it('does not introduce settings only models', function (): void {
    expect(class_exists('App\\Models\\CardTemplate'))->toBeFalse()
        ->and(class_exists('App\\Models\\PublicMenu'))->toBeFalse()
        ->and(class_exists('App\\Models\\PublicMenuItem'))->toBeFalse()
        ->and(class_exists('App\\Models\\AboutPage'))->toBeFalse()
        ->and(class_exists('App\\Models\\AboutPageBlock'))->toBeFalse()
        ->and(class_exists('App\\Models\\TeamProfile'))->toBeFalse()
        ->and(class_exists('App\\Models\\PublicFormDefinition'))->toBeFalse()
        ->and(class_exists('App\\Models\\PublicDisplaySection'))->toBeFalse()
        ->and(class_exists('App\\Models\\PublicLooper'))->toBeFalse();
});

it('has translated item page settings labels in both locales', function (): void {
    foreach (['en', 'he'] as $locale) {
        app()->setLocale($locale);

        foreach ([
            'site_published_long',
            'site_published_short',
            'original_published_long',
            'original_published_short',
            'transcription_date_long',
            'transcription_date_short',
        ] as $key) {
            expect(__("public.dates.{$key}"))->not->toBe("public.dates.{$key}");
        }

        foreach ([
            'categories_long',
            'duration_short',
            'reading_time_long',
            'tags_short',
            'transcribers_long',
            'transcription_count_short',
            'word_count_long',
        ] as $key) {
            expect(__("public.item_page.info_fields.{$key}"))->not->toBe("public.item_page.info_fields.{$key}");
        }

        foreach ([
            'admin.sections.public_front_item_page_header',
            'admin.sections.public_front_item_page_info_fields',
            'admin.sections.public_front_item_page_transcript_controls',
            'admin.descriptions.public_front_item_page_transcript_controls',
            'admin.fields.item_page_show_breadcrumbs',
            'admin.fields.item_page_show_transcript_actions_menu',
            'admin.fields.item_page_podcast_identity_mode',
            'admin.fields.item_page_podcast_identity_position',
            'admin.fields.item_page_podcast_identity_size',
            'admin.fields.item_page_info_fields',
            'admin.helpers.item_page_show_transcript_actions_menu',
            'admin.helpers.item_page_info_field_key',
            'admin.item_page_info_fields.site_published_date',
            'admin.item_page_podcast_identity_modes.badge',
            'admin.item_page_podcast_identity_colors.image_2',
            'admin.item_page_podcast_identity_positions.title_row_after',
            'admin.item_page_podcast_identity_sizes.lg',
            'public.viewer.actions',
            'public.viewer.decrease_font',
            'public.viewer.fullscreen',
            'public.viewer.hide_player',
            'public.viewer.increase_font',
            'public.viewer.reset_font',
            'public.viewer.show_player',
        ] as $translationKey) {
            expect(__($translationKey))->not->toBe($translationKey);
        }
    }

    app()->setLocale(config('app.locale'));
});
