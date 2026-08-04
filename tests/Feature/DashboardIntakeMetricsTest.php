<?php

use App\Enums\ExternalImageFailureReason;
use App\Enums\ImportConnectionAuthType;
use App\Enums\ImportConnectionProvider;
use App\Enums\ImportConnectionStatus;
use App\Enums\MediaAcquisitionDisposition;
use App\Enums\MediaDiagnosticReason;
use App\Enums\MediaLibraryTask;
use App\Enums\StreamEventType;
use App\Enums\TranscriptionMode;
use App\Enums\UserRole;
use App\Filament\Imports\AuthorImporter;
use App\Filament\Imports\CategoryImporter;
use App\Filament\Imports\ContentGroupImporter;
use App\Filament\Imports\ContentItemImporter;
use App\Filament\Imports\TranscriptionImporter;
use App\Models\ImportConnection;
use App\Models\Media;
use App\Models\PublicFormSubmission;
use App\Models\User;
use App\Support\Dashboard\EditorialMetrics;
use App\Support\Media\ExternalImageFailureMessage;
use Filament\Actions\Imports\Events\ImportStarted;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    setTestTranscriptionMode(TranscriptionMode::Multi);
    Storage::fake('public');
    $this->actingAs(User::factory()->admin()->create());
});

it('counts the intake queue in the snapshot', function (): void {
    PublicFormSubmission::factory()->count(2)->create();
    PublicFormSubmission::factory()->reviewed()->create();
    failedImport(failed: 3);

    $snapshot = app(EditorialMetrics::class)->intakeSnapshot();

    expect($snapshot['queue'])->toBe(['submissions' => 2, 'imports' => 1, 'failed_rows' => 3]);
});

it('merges queue rows newest-first with typed kinds and doorways', function (): void {
    PublicFormSubmission::factory()->create(['form_name_snapshot' => 'Join us']);
    failedImport();

    $queue = app(EditorialMetrics::class)->intakeQueue();

    expect($queue['counts'])->toBe(['all' => 2, 'submissions' => 1, 'imports' => 1])
        ->and(collect($queue['rows'])->pluck('type')->all())
        ->toContain(StreamEventType::Submission, StreamEventType::Import);

    $importRow = collect($queue['rows'])->firstWhere('type', StreamEventType::Import);
    expect($importRow['title'])->toBe('episodes.csv')
        ->and($importRow['url'])->toContain('failed-rows/download')
        ->and($importRow['url'])->toContain('signature=');
});

it('narrows the queue by kind and by source', function (): void {
    PublicFormSubmission::factory()->create();
    failedImport();
    $metrics = app(EditorialMetrics::class);

    expect(collect($metrics->intakeQueue(kind: StreamEventType::Submission)['rows'])->pluck('type')->unique()->all())
        ->toBe([StreamEventType::Submission])
        // D-4: manual = the full queue; a connected-provider source has no
        // attributable rows today and must return none rather than lie.
        ->and($metrics->intakeQueue(source: ImportConnectionProvider::Manual)['counts']['all'])->toBe(2)
        ->and($metrics->intakeQueue(source: ImportConnectionProvider::Spotify)['rows'])->toBe([])
        ->and($metrics->intakeQueue(source: ImportConnectionProvider::Spotify)['counts']['all'])->toBe(0);
});

it('echoes the Spotify connection test and derives reduced mode', function (): void {
    $metrics = app(EditorialMetrics::class);

    expect($metrics->spotifyConnectionEcho())->toBe(['connections' => [], 'reduced' => true]);

    ImportConnection::factory()->create([
        'name' => 'Main Spotify',
        'provider' => ImportConnectionProvider::Spotify,
        'auth_type' => ImportConnectionAuthType::ClientCredentials,
        'status' => ImportConnectionStatus::Failed,
        'last_tested_at' => now(),
    ]);

    $echo = $metrics->spotifyConnectionEcho();
    expect($echo['reduced'])->toBeTrue()
        ->and($echo['connections'][0]['status'])->toBe(ImportConnectionStatus::Failed);

    ImportConnection::query()->update(['status' => ImportConnectionStatus::Connected]);
    expect(app(EditorialMetrics::class)->spotifyConnectionEcho()['reduced'])->toBeFalse();
});

it('builds media finding bars with hidden zero rows and gallery doorways', function (): void {
    cleanMedia();
    missingFileMedia();

    $findings = app(EditorialMetrics::class)->mediaFindings();
    $missing = collect($findings['rows'])->first(
        fn ($row): bool => $row->meta('reason') === MediaDiagnosticReason::MissingFile->value,
    );

    expect($missing)->not->toBeNull()
        ->and($missing->value)->toBe(1.0)
        ->and($missing->url)
        ->toContain('tab='.MediaLibraryTask::NeedsAttention->value)
        ->and($missing->url)->toContain('filters%5Breason%5D%5Bvalue%5D='.MediaDiagnosticReason::MissingFile->value)
        // Zero-count findings are hidden (decision 5): no bar may report 0.
        ->and(collect($findings['rows'])->every(fn ($row): bool => $row->value > 0))->toBeTrue()
        ->and($findings['rate']->of)->toBe(2);
});

it('forgets the intake snapshot on intake writes', function (): void {
    $metrics = app(EditorialMetrics::class);
    expect($metrics->intakeSnapshot()['queue']['submissions'])->toBe(0);

    PublicFormSubmission::factory()->create();
    expect(app(EditorialMetrics::class)->intakeSnapshot()['queue']['submissions'])->toBe(1);

    failedImport();
    expect(app(EditorialMetrics::class)->intakeSnapshot()['queue']['imports'])->toBe(1);

    Media::factory()->create();
    ImportConnection::factory()->create(['provider' => ImportConnectionProvider::Manual]);
    // Media/connection writes must also invalidate — the snapshot may never
    // contradict a change the editor just made (decision 12's contract).
    expect(app(EditorialMetrics::class)->intakeSnapshot()['media']['total'])->toBe(1);
});

it('gates the failure CSV to admins or the owner', function (): void {
    // Vendor fact shaping these assertions: the filament.actions route group
    // is ['web'] only (ActionsServiceProvider.php:35) — the signature is not
    // enforced by middleware; it only selects the auth guard. The real
    // protection is authentication plus the policy/owner branch.
    $import = failedImport();   // owned by a freshly created other admin
    $url = app(EditorialMetrics::class)->failedRowsDownloadUrl($import);

    // Signed in as the beforeEach() admin, who does NOT own the import.
    $this->get($url)->assertOk();

    // A non-admin who does not own the import is refused by the policy.
    // (The user factory defaults to the admin role — the plain role must be
    // explicit here or this asserts nothing.)
    $this->actingAs(User::factory()->role(UserRole::User)->create());
    $this->get($url)->assertForbidden();

    // A guest is refused before the policy runs.
    auth()->logout();
    $this->get($url)->assertUnauthorized();
});

it('labels the Board-3 E4 enums through their Filament contracts', function (): void {
    expect(ExternalImageFailureReason::TimedOut->getLabel())
        ->toBe(__('admin.media_library.url_failure_timed_out'))
        ->and(MediaAcquisitionDisposition::Reused->getLabel())
        ->toBe(__('admin.media_library.storage_reused'));

    // The contract is the interface, not just the method (the corpus's
    // OrderStatus bug-shape: getColor() without implements HasColor).
    expect(ExternalImageFailureReason::TimedOut)
        ->toBeInstanceOf(HasLabel::class)
        ->and(MediaAcquisitionDisposition::Reused)
        ->toBeInstanceOf(HasLabel::class);
});

it('has a real translation behind every E4 label in both locales', function (): void {
    // speaks-both-languages, non-vacuously: getLabel() composing a key that
    // does not exist returns the raw key and every equality assert against
    // __() passes anyway. Lang::has() is the only honest probe — this is
    // what catches the storage_created gap (missing in BOTH locales until
    // this task adds it).
    foreach (['en', 'he'] as $locale) {
        foreach (ExternalImageFailureReason::cases() as $reason) {
            expect(Lang::hasForLocale("admin.media_library.url_failure_{$reason->value}", $locale))->toBeTrue();
        }

        foreach (MediaAcquisitionDisposition::cases() as $disposition) {
            expect(Lang::hasForLocale("admin.media_library.storage_{$disposition->value}", $locale))->toBeTrue();
        }
    }
});

it('routes the failure message and picker notification through the enums', function (): void {
    $message = ExternalImageFailureMessage::for(
        new InvalidArgumentException('bad image'),
    );

    expect($message)->toBe(__('admin.media_library.url_failure_invalid_image'));
});

it('stamps modal imports manual with the optional custom name', function (): void {
    // The Filament import modal is the only producer of ImportStarted
    // today and IS the manual process: the stamp is unconditional
    // (operator override 2026-08-04 — source is process-decided, never
    // user-declared).
    $import = failedImport();
    event(new ImportStarted($import, [], ['name' => 'July backfill']));
    expect($import->fresh()->provider)->toBe('manual')
        ->and($import->fresh()->name)->toBe('July backfill');

    // raw-state: the name is browser-supplied free text — blank,
    // non-string or oversized values stay null; the manual stamp lands
    // regardless.
    $other = failedImport(fileName: 'other.csv');
    event(new ImportStarted($other, [], ['name' => str_repeat('x', 500)]));
    expect($other->fresh()->provider)->toBe('manual')
        ->and($other->fresh()->name)->toBeNull();
});

it('offers only the optional name field in every importer modal schema', function (): void {
    // Source-level assertion by design: action-modal prose is NOT in the
    // Livewire HTML at mountAction time (2R gotcha), so the schema is
    // asserted where it is built, non-vacuously. No importer may offer a
    // source select — source is process-decided (operator override
    // 2026-08-04).
    foreach ([
        ContentItemImporter::class,
        AuthorImporter::class,
        TranscriptionImporter::class,
        ContentGroupImporter::class,
        CategoryImporter::class,
    ] as $importer) {
        $components = collect($importer::getOptionsFormComponents());

        expect($components->first(fn ($component): bool => $component->getName() === 'name'))
            ->toBeInstanceOf(TextInput::class)
            ->and($components->first(fn ($component): bool => $component->getName() === 'source'))
            ->toBeNull();
    }

    foreach (['en', 'he'] as $locale) {
        expect(Lang::hasForLocale('admin.import.options.name', $locale))->toBeTrue()
            ->and(Lang::hasForLocale('admin.import.options.name_helper', $locale))->toBeTrue();
    }
});

it('titles queue import rows by custom name over file name', function (): void {
    failedImport(fileName: 'episodes.csv')->forceFill(['name' => 'July backfill'])->save();

    $row = collect(app(EditorialMetrics::class)->intakeQueue()['rows'])
        ->firstWhere('type', StreamEventType::Import);

    expect($row['title'])->toBe('July backfill');
    // The merges-queue test pinning a name-less import's title to its file
    // name stays green — that is the fallback half of the same rule.
});

it('scopes the queue by stamped provider', function (): void {
    PublicFormSubmission::factory()->create();
    failedImport();                                          // legacy: provider null
    failedImport(fileName: 'drive.csv')->forceFill(['provider' => 'google_drive'])->save();

    $metrics = app(EditorialMetrics::class);

    expect($metrics->intakeQueue(source: ImportConnectionProvider::Manual)['counts'])
        ->toBe(['all' => 2, 'submissions' => 1, 'imports' => 1])
        ->and(collect($metrics->intakeQueue(source: ImportConnectionProvider::GoogleDrive)['rows'])->pluck('title')->all())
        ->toBe(['drive.csv'])
        ->and($metrics->intakeQueue(source: ImportConnectionProvider::GoogleDrive)['counts'])
        ->toBe(['all' => 1, 'submissions' => 0, 'imports' => 1])
        ->and($metrics->intakeQueue(source: ImportConnectionProvider::Spotify)['rows'])->toBe([]);
});
