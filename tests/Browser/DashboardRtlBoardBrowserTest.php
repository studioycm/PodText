<?php

use App\Enums\PublicationStatus;
use App\Enums\TranscriptionMode;
use App\Filament\Pages\Dashboard;
use App\Models\Category;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\PublicFormSubmission;
use App\Models\Transcription;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

// Decision 9: the RTL evidence group runs on demand
// (`--group=rtl-board`), excluded from the main gate in phpunit.xml so the
// gate stays deterministic — the compiled-sentinels mechanism.
uses(RefreshDatabase::class)->group('rtl-board');

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    setTestTranscriptionMode(TranscriptionMode::Multi);
    app()->setLocale('he');
    Carbon::setTestNow(Carbon::parse('2026-07-31 10:00:00', 'Asia/Jerusalem'));
    $this->actingAs(User::factory()->admin()->create());
});

afterEach(function (): void {
    Carbon::setTestNow();
});

/** Every board populated; per-run-unique names for disk writes (test-residue). */
function rtlBoardFixture(): void
{
    $token = 'rtl'.getmypid();

    $group = ContentGroup::factory()->published()->create(['title' => "פודקאסט {$token}"]);
    $category = Category::factory()->create();

    $visible = ContentItem::factory()->for($group)
        ->published(Carbon::parse('2026-07-29 09:00'))
        ->create(['title' => "פרק גלוי {$token}", 'embed_url' => 'https://open.spotify.com/episode/r1']);
    $visible->categories()->attach($category);
    Transcription::factory()->for($visible)
        ->published(Carbon::parse('2026-07-29 10:00'))
        ->create();

    $blocked = ContentItem::factory()->for($group)
        ->published(Carbon::parse('2026-07-28 09:00'))
        ->create(['title' => "פרק חסום {$token}", 'embed_url' => 'https://open.spotify.com/episode/r2']);
    $blocked->categories()->attach($category);

    ContentItem::factory()->for($group)->create(['status' => PublicationStatus::Draft]);

    PublicFormSubmission::factory()->create(['form_name_snapshot' => "פנייה {$token}"]);
    failedImport(fileName: "{$token}.csv");
    cleanMedia();
    missingFileMedia();
}

/** The labelled waitFor helper every driver script shares (house pattern). */
function rtlBoardHelpers(): string
{
    return <<<'JS'
        const waitFor = async (callback, label, timeout = 8000) => {
            const started = performance.now();
            while (performance.now() - started < timeout) {
                const value = callback();
                if (value) { return value; }
                await new Promise((resolve) => setTimeout(resolve, 100));
            }
            throw new Error(`waitFor timed out at step: ${label}`);
        };

        // Lazy widgets hydrate on intersection: scroll toward a testid and
        // wait for it as a CONDITION (single-read-race discipline).
        const scrollUntil = async (selector, label, timeout = 15000) => await waitFor(() => {
            const el = document.querySelector(selector);
            if (el) { el.scrollIntoView({block: 'center'}); return el; }
            window.scrollBy(0, 400);
            return null;
        }, label, timeout);
    JS;
}

it('walks the three lenses on the RTL board with the LTR islands intact', function (): void {
    Storage::fake('public');
    rtlBoardFixture();

    $helpers = rtlBoardHelpers();
    $page = visit(Dashboard::getUrl())->resize(1280, 900);

    // Board 1 — Overview: the context echo renders at the top immediately.
    $page->assertNoJavaScriptErrors()
        ->assertPresent('[data-testid="dashboard-scope-echo"]');

    $overview = $page->script(<<<JS
        async () => {
            {$helpers}

            const echo = document.querySelector('[data-testid="dashboard-scope-echo"]');
            await scrollUntil('[data-testid="funnel-segment-visible"]', 'funnel-hydrated');
            await scrollUntil('[data-testid="stream-row"]', 'stream-hydrated');
            const streamTime = await waitFor(
                () => document.querySelector('[data-testid="stream-row"] time'),
                'stream-time',
            );

            return {
                echoDirection: getComputedStyle(echo).direction,
                timeDir: streamTime.getAttribute('dir'),
            };
        }
    JS);

    expect($overview['echoDirection'] ?? null)->toBe('rtl')
        ->and($overview['timeDir'] ?? null)->toBe('ltr');

    // Board 2 — Blockers: the burn-down copy composes remaining=1 of
    // total=2 from the fixture (one invisible of two published).
    $page->click(__('admin.dashboard.lenses.blockers'));

    $burndownText = $page->script(<<<JS
        async () => {
            {$helpers}

            const burndown = await scrollUntil('[data-testid="queue-burndown"]', 'burndown-hydrated');

            return burndown.textContent;
        }
    JS);

    expect((string) $burndownText)->toContain(
        __('admin.dashboard.queue.burndown_invisible', ['remaining' => 1, 'total' => 2]),
    );
    $page->assertNoJavaScriptErrors();

    // Board 3 — Intake: queue rows, connection empty state, findings bars.
    $page->click(__('admin.dashboard.lenses.intake'));

    $intake = $page->script(<<<JS
        async () => {
            {$helpers}

            await scrollUntil('[data-testid="intake-row"]', 'queue-hydrated');
            const intakeTime = await waitFor(
                () => document.querySelector('[data-testid="intake-row"] time'),
                'intake-time',
            );
            const connectionEmpty = await scrollUntil('[data-testid="connection-empty"]', 'connection-hydrated');
            const findingRow = await scrollUntil('[data-testid="media-finding-row"]', 'findings-hydrated');

            return {
                intakeTimeDir: intakeTime.getAttribute('dir'),
                connectionEmptyShown: connectionEmpty !== null,
                // The findings bar track is a deliberate LTR island inside
                // the RTL card.
                barIsland: findingRow.querySelector('[dir="ltr"]') !== null,
            };
        }
    JS);

    expect($intake['intakeTimeDir'] ?? null)->toBe('ltr')
        ->and($intake['connectionEmptyShown'] ?? null)->toBeTrue()
        ->and($intake['barIsland'] ?? null)->toBeTrue();

    $page->assertNoJavaScriptErrors();
});
