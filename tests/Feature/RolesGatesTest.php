<?php

use App\Enums\TranscriptionMode;
use App\Enums\UserRole;
use App\Filament\Pages\AdminUxSettings as AdminUxSettingsPage;
use App\Filament\Pages\PublicContentSettings as PublicContentSettingsPage;
use App\Filament\Resources\ContentItems\Pages\EditContentItem;
use App\Filament\Resources\ContentItems\RelationManagers\TranscriptionsRelationManager;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\UserResource;
use App\Models\ContentItem;
use App\Models\Transcription;
use App\Models\User;
use App\Settings\AdminUxSettings;
use App\Settings\PublicContentSettings;
use App\Support\PublicContent\PublicTranscriptionPolicy;
use App\Support\PublicFront\PublicFrontRenderContext;
use App\Support\Transcriptions\MultiTranscriptionSurfaces;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Spatie\LaravelSettings\SettingsContainer;
use Symfony\Component\Console\Command\Command;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    fakeSettingsBackupSnapshotQueue();
});

function roles1ClearSettingsCache(): void
{
    app()->forgetInstance(AdminUxSettings::class);
    app()->forgetInstance(PublicContentSettings::class);
    app()->forgetInstance(PublicFrontRenderContext::class);
    app(SettingsContainer::class)->clearCache();
}

function roles1SaveSetting(string $settingsClass, string $name, mixed $value): void
{
    DB::table('settings')->updateOrInsert(
        [
            'group' => $settingsClass::group(),
            'name' => $name,
        ],
        [
            'locked' => false,
            'payload' => json_encode($value),
            'created_at' => now(),
            'updated_at' => now(),
        ],
    );

    roles1ClearSettingsCache();
}

function roles1SetTranscriptionMode(TranscriptionMode $mode): void
{
    roles1SaveSetting(AdminUxSettings::class, 'transcription_mode', $mode->value);
}

/**
 * @param  array<int, array<string, mixed>>  $parts
 * @return array<string, mixed>
 */
function roles1CardTemplate(string $key, array $parts): array
{
    return [
        'key' => $key,
        'label' => "Template {$key}",
        'family' => 'content_item',
        'layout' => 'cards',
        'density' => 'comfortable',
        'image_size' => 'medium',
        'title_size' => 'base',
        'parts' => $parts,
    ];
}

/**
 * @return array<string, mixed>
 */
function roles1PlainCardPart(string $type, string $source, string $attribute, int $order = 10): array
{
    return [
        'type' => $type,
        'source' => $source,
        'attribute' => $attribute,
        'visible' => true,
        'order' => $order,
        'layout' => 'inline',
    ];
}

/**
 * @return array{type: string, data: array<string, mixed>}
 */
function roles1BuilderCardPart(string $type, string $source, string $attribute, int $order = 10): array
{
    return [
        'type' => $type,
        'data' => [
            'source' => $source,
            'attribute' => $attribute,
            'visible' => true,
            'order' => $order,
            'layout' => 'inline',
        ],
    ];
}

/**
 * @param  array<int, array<string, mixed>>|mixed  $parts
 */
function roles1HasTranscriptionCountPart(mixed $parts): bool
{
    if (! is_array($parts)) {
        return false;
    }

    foreach ($parts as $part) {
        if (! is_array($part)) {
            continue;
        }

        $data = is_array($part['data'] ?? null) ? $part['data'] : $part;

        if (($data['source'] ?? null) === 'content_item' && ($data['attribute'] ?? null) === 'transcription_count') {
            return true;
        }

        if (roles1HasTranscriptionCountPart($data['children'] ?? [])) {
            return true;
        }
    }

    return false;
}

function roles1SchemaComponentByStatePath(mixed $component, string $statePath): mixed
{
    $absoluteStatePath = str_starts_with($statePath, 'data.')
        ? $statePath
        : "data.{$statePath}";

    return collect($component->instance()->getSchema('form')->getFlatComponents(withActions: false, withHidden: true, withAbsoluteKeys: true))
        ->first(fn (mixed $schemaComponent): bool => method_exists($schemaComponent, 'getStatePath')
            && $schemaComponent->getStatePath() === $absoluteStatePath);
}

function roles1SchemaSectionByHeading(mixed $component, string $heading): mixed
{
    return collect($component->instance()->getSchema('form')->getFlatComponents(withActions: false, withHidden: true, withAbsoluteKeys: true))
        ->first(fn (mixed $schemaComponent): bool => method_exists($schemaComponent, 'getHeading')
            && (string) $schemaComponent->getHeading() === $heading);
}

it('casts hierarchical user roles and gates admin panel access by role', function (): void {
    $panel = Filament::getPanel('admin');

    $expectations = [
        UserRole::SuperAdmin->value => true,
        UserRole::Admin->value => true,
        UserRole::Moderator->value => false,
        UserRole::Transcriber->value => false,
        UserRole::User->value => false,
    ];

    foreach ($expectations as $roleValue => $canAccessAdminPanel) {
        $role = UserRole::from($roleValue);
        $user = User::factory()->role($role)->create();

        expect($user->role)->toBe($role)
            ->and($user->hasRoleAtLeast(UserRole::Admin))->toBe($canAccessAdminPanel)
            ->and($user->canAccessPanel($panel))->toBe($canAccessAdminPanel);
    }
});

it('evaluates super-admin and multi-transcription gates by mode, role, and minimum role', function (): void {
    $admin = User::factory()->admin()->create();
    $superAdmin = User::factory()->superAdmin()->create();

    roles1SetTranscriptionMode(TranscriptionMode::Single);

    expect(Gate::forUser($admin)->allows('super-admin'))->toBeFalse()
        ->and(Gate::forUser($superAdmin)->allows('super-admin'))->toBeTrue()
        ->and(Gate::forUser($admin)->allows('multi-transcription', [UserRole::Admin]))->toBeFalse()
        ->and(Gate::forUser($superAdmin)->allows('multi-transcription', [UserRole::Admin]))->toBeFalse()
        ->and(Gate::forUser($superAdmin)->allows('multi-transcription', [UserRole::SuperAdmin]))->toBeFalse();

    roles1SetTranscriptionMode(TranscriptionMode::Multi);

    expect(Gate::forUser($admin)->allows('multi-transcription', [UserRole::Admin]))->toBeTrue()
        ->and(Gate::forUser($admin)->allows('multi-transcription', [UserRole::SuperAdmin]))->toBeFalse()
        ->and(Gate::forUser($superAdmin)->allows('multi-transcription', [UserRole::Admin]))->toBeTrue()
        ->and(Gate::forUser($superAdmin)->allows('multi-transcription', [UserRole::SuperAdmin]))->toBeTrue();
});

it('assigns fixed roles by command and refuses invalid input', function (): void {
    $user = User::factory()->admin()->create();

    $this->artisan('users:assign-role', [
        'email' => $user->email,
        'role' => UserRole::SuperAdmin->value,
    ])->assertExitCode(Command::SUCCESS);

    expect($user->refresh()->role)->toBe(UserRole::SuperAdmin);

    $this->artisan('users:assign-role', [
        'email' => $user->email,
        'role' => 'owner',
    ])->assertExitCode(Command::FAILURE);

    $this->artisan('users:assign-role', [
        'email' => 'missing@example.test',
        'role' => UserRole::Admin->value,
    ])->assertExitCode(Command::FAILURE);
});

it('keeps hidden admin ux mode values byte-identical during forged admin saves', function (): void {
    foreach ([TranscriptionMode::Single, TranscriptionMode::Multi] as $storedMode) {
        roles1SetTranscriptionMode($storedMode);

        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(AdminUxSettingsPage::class)
            ->set('data.transcription_mode', $storedMode === TranscriptionMode::Single
                ? TranscriptionMode::Multi->value
                : TranscriptionMode::Single->value)
            ->call('save')
            ->assertHasNoFormErrors();

        roles1ClearSettingsCache();

        expect(app(AdminUxSettings::class)->transcription_mode)->toBe($storedMode->value);
    }
});

it('allows a super-admin to change the global transcription mode switch', function (): void {
    roles1SetTranscriptionMode(TranscriptionMode::Single);

    $this->actingAs(User::factory()->superAdmin()->create());

    Livewire::test(AdminUxSettingsPage::class)
        ->set('data.transcription_mode', TranscriptionMode::Multi->value)
        ->call('save')
        ->assertHasNoFormErrors();

    roles1ClearSettingsCache();

    expect(app(AdminUxSettings::class)->transcription_mode)->toBe(TranscriptionMode::Multi->value);
});

it('keeps hidden public transcription policy values byte-identical during forged admin saves in both modes', function (): void {
    $storedPolicy = [
        'public_mode' => PublicTranscriptionPolicy::MODE_FEATURED_ONLY,
        'count_mode' => PublicTranscriptionPolicy::MODE_FEATURED_ONLY,
        'show_multiple_transcriptions_on_item_page' => false,
    ];

    foreach ([TranscriptionMode::Single, TranscriptionMode::Multi] as $mode) {
        roles1SetTranscriptionMode($mode);
        roles1SaveSetting(PublicContentSettings::class, 'transcription_policy', $storedPolicy);

        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(PublicContentSettingsPage::class)
            ->set('data.transcription_policy.public_mode', PublicTranscriptionPolicy::MODE_ALL_PUBLISHED)
            ->set('data.transcription_policy.count_mode', PublicTranscriptionPolicy::MODE_ALL_PUBLISHED)
            ->set('data.transcription_policy.show_multiple_transcriptions_on_item_page', true)
            ->call('save')
            ->assertHasNoFormErrors();

        roles1ClearSettingsCache();

        expect(json_encode(app(PublicContentSettings::class)->transcription_policy))->toBe(json_encode($storedPolicy));
    }
});

it('allows a super-admin in multi mode to save public transcription policy values', function (): void {
    roles1SetTranscriptionMode(TranscriptionMode::Multi);

    roles1SaveSetting(PublicContentSettings::class, 'transcription_policy', [
        'public_mode' => PublicTranscriptionPolicy::MODE_FEATURED_ONLY,
        'count_mode' => PublicTranscriptionPolicy::MODE_FEATURED_ONLY,
        'show_multiple_transcriptions_on_item_page' => false,
    ]);

    $this->actingAs(User::factory()->superAdmin()->create());

    Livewire::test(PublicContentSettingsPage::class)
        ->set('data.transcription_policy.public_mode', PublicTranscriptionPolicy::MODE_ALL_PUBLISHED)
        ->set('data.transcription_policy.count_mode', PublicTranscriptionPolicy::MODE_ALL_PUBLISHED)
        ->set('data.transcription_policy.show_multiple_transcriptions_on_item_page', true)
        ->call('save')
        ->assertHasNoFormErrors();

    roles1ClearSettingsCache();

    expect(app(PublicContentSettings::class)->transcription_policy)->toBe([
        'public_mode' => PublicTranscriptionPolicy::MODE_ALL_PUBLISHED,
        'count_mode' => PublicTranscriptionPolicy::MODE_ALL_PUBLISHED,
        'show_multiple_transcriptions_on_item_page' => true,
    ]);
});

it('filters and save-guards the public card template transcription-count part', function (): void {
    $options = [
        'title' => 'Title',
        'transcription_count' => 'Transcription count',
    ];

    roles1SetTranscriptionMode(TranscriptionMode::Single);
    $this->actingAs(User::factory()->superAdmin()->create());

    expect(MultiTranscriptionSurfaces::filterCardAttributeOptions('content_item', $options))
        ->not->toHaveKey('transcription_count');

    roles1SetTranscriptionMode(TranscriptionMode::Multi);
    $this->actingAs(User::factory()->admin()->create());

    expect(MultiTranscriptionSurfaces::filterCardAttributeOptions('content_item', $options))
        ->not->toHaveKey('transcription_count');

    $this->actingAs(User::factory()->superAdmin()->create());

    expect(MultiTranscriptionSurfaces::filterCardAttributeOptions('content_item', $options))
        ->toHaveKey('transcription_count');

    $storedTemplate = roles1CardTemplate('stored_count', [
        roles1PlainCardPart('metadata_row', 'content_item', 'transcription_count'),
    ]);

    roles1SaveSetting(PublicContentSettings::class, 'card_templates', [$storedTemplate]);
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(PublicContentSettingsPage::class)
        ->set('data.card_templates', [
            roles1CardTemplate('stored_count', []),
            roles1CardTemplate('forged_count', [
                roles1BuilderCardPart('metadata_row', 'content_item', 'transcription_count'),
                [
                    'type' => 'part_group',
                    'data' => [
                        'visible' => true,
                        'order' => 20,
                        'layout' => 'grid',
                        'children' => [
                            roles1BuilderCardPart('metadata_row', 'content_item', 'transcription_count'),
                            roles1BuilderCardPart('title', 'content_item', 'title'),
                        ],
                    ],
                ],
            ]),
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    roles1ClearSettingsCache();

    $templates = collect(app(PublicContentSettings::class)->card_templates)->keyBy('key');

    expect($templates->get('stored_count')['parts'])->toBe($storedTemplate['parts'])
        ->and(roles1HasTranscriptionCountPart($templates->get('forged_count')['parts']))->toBeFalse();
});

it('applies the two visibility gates to settings and current admin transcription surfaces', function (): void {
    roles1SetTranscriptionMode(TranscriptionMode::Multi);

    $item = ContentItem::factory()->create();
    $first = Transcription::factory()->for($item)->published()->create(['title' => 'First transcript']);
    $second = Transcription::factory()->for($item)->published()->create(['title' => 'Second transcript']);

    $item->update(['featured_transcription_id' => $first->id]);

    roles1SetTranscriptionMode(TranscriptionMode::Single);
    $this->actingAs(User::factory()->superAdmin()->create());

    Livewire::test(AdminUxSettingsPage::class)
        ->assertSchemaComponentVisible('transcription_mode');

    $publicSettingsComponent = roles1SchemaSectionByHeading(
        Livewire::test(PublicContentSettingsPage::class),
        __('admin.sections.public_transcription_policy'),
    );

    expect($publicSettingsComponent)->not->toBeNull()
        ->and($publicSettingsComponent->isHidden())->toBeTrue();

    Livewire::test(EditContentItem::class, ['record' => $item->getRouteKey()])
        ->assertSchemaComponentHidden('featured_transcription_id', 'form');

    Livewire::test(TranscriptionsRelationManager::class, [
        'ownerRecord' => $item->refresh(),
        'pageClass' => EditContentItem::class,
    ])
        ->assertActionHidden(TestAction::make('create')->table())
        ->assertActionHidden(TestAction::make('setFeatured')->table($second));

    roles1SetTranscriptionMode(TranscriptionMode::Multi);
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(AdminUxSettingsPage::class)
        ->assertSchemaComponentHidden('transcription_mode');

    $publicSettingsComponent = roles1SchemaSectionByHeading(
        Livewire::test(PublicContentSettingsPage::class),
        __('admin.sections.public_transcription_policy'),
    );

    expect($publicSettingsComponent)->not->toBeNull()
        ->and($publicSettingsComponent->isHidden())->toBeTrue();

    Livewire::test(EditContentItem::class, ['record' => $item->getRouteKey()])
        ->assertSchemaComponentVisible('featured_transcription_id', 'form');

    Livewire::test(TranscriptionsRelationManager::class, [
        'ownerRecord' => $item->refresh(),
        'pageClass' => EditContentItem::class,
    ])
        ->assertActionVisible(TestAction::make('create')->table())
        ->assertActionVisible(TestAction::make('setFeatured')->table($second));

    $this->actingAs(User::factory()->superAdmin()->create());

    $publicSettingsComponent = roles1SchemaSectionByHeading(
        Livewire::test(PublicContentSettingsPage::class),
        __('admin.sections.public_transcription_policy'),
    );

    expect($publicSettingsComponent)->not->toBeNull()
        ->and($publicSettingsComponent->isHidden())->toBeFalse();
});

it('exposes the users resource only to super-admins and blocks create or delete paths', function (): void {
    $admin = User::factory()->admin()->create();
    $superAdmin = User::factory()->superAdmin()->create();

    $this->actingAs($admin);

    expect(UserResource::canViewAny())->toBeFalse();

    Livewire::test(ListUsers::class)
        ->assertForbidden();

    $this->actingAs($superAdmin);

    expect(UserResource::canViewAny())->toBeTrue()
        ->and(UserResource::canCreate())->toBeFalse()
        ->and(UserResource::canDelete($admin))->toBeFalse()
        ->and(UserResource::canDeleteAny())->toBeFalse()
        ->and(UserResource::getPages())->not->toHaveKey('create');

    Livewire::test(ListUsers::class)
        ->assertOk();

    Livewire::test(EditUser::class, ['record' => $admin->getRouteKey()])
        ->assertOk()
        ->assertSchemaComponentVisible('role', 'form')
        ->assertDontSee('password');
});

it('edits user roles while preventing self-demotion and last-super-admin demotion', function (): void {
    $superAdmin = User::factory()->superAdmin()->create();
    $otherSuperAdmin = User::factory()->superAdmin()->create();
    $admin = User::factory()->admin()->create();

    $this->actingAs($superAdmin);

    Livewire::test(EditUser::class, ['record' => $admin->getRouteKey()])
        ->set('data.role', UserRole::Moderator->value)
        ->call('save')
        ->assertHasNoFormErrors();

    expect($admin->refresh()->role)->toBe(UserRole::Moderator);

    Livewire::test(EditUser::class, ['record' => $superAdmin->getRouteKey()])
        ->set('data.role', UserRole::Admin->value)
        ->call('save')
        ->assertHasErrors(['role']);

    expect($superAdmin->refresh()->role)->toBe(UserRole::SuperAdmin);

    $otherSuperAdmin->forceFill(['role' => UserRole::Admin])->save();

    Livewire::test(EditUser::class, ['record' => $superAdmin->getRouteKey()])
        ->set('data.role', UserRole::Admin->value)
        ->call('save')
        ->assertHasErrors(['role']);

    expect($superAdmin->refresh()->role)->toBe(UserRole::SuperAdmin);
});
