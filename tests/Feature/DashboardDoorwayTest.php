<?php

use App\Enums\TranscriptionMode;
use App\Filament\Resources\ContentItems\Pages\ListContentItems;
use App\Filament\Resources\Imports\Pages\ListImports;
use App\Filament\Resources\Media\Pages\ListMedia;
use App\Filament\Resources\PublicFormSubmissions\Pages\ListPublicFormSubmissions;
use App\Filament\Resources\Transcriptions\Pages\ListTranscriptions;
use App\Filament\Widgets\EditorialStatsWidget;
use App\Filament\Widgets\IntakeQueueWidget;
use App\Filament\Widgets\LibraryCompositionWidget;
use App\Filament\Widgets\MediaFindingsWidget;
use App\Filament\Widgets\PublicationFunnelWidget;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\Media;
use App\Models\PublicFormSubmission;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * every-number-a-door, enforced rather than asserted.
 *
 * A dashboard number and the link beneath it are computed in two places —
 * the number in EditorialMetrics, the URL hand-written in the widget — from
 * two different expressions, with nothing binding them. So they drift, and
 * they drift silently: a card reading 2 opening a list of 9 looks exactly
 * like a card reading 2 opening a list of 2.
 *
 * This walks every widget's view data, finds every node that carries both a
 * number and a URL, follows that URL for real, and asserts the landed row
 * count is the number the card showed. Doorways are DISCOVERED, never listed
 * — a new one is covered the day it is written, and one that stops being a
 * doorway stops being checked.
 */
beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(User::factory()->admin()->create());
});

/**
 * Recursively find every {value, url} pair in a widget's view data.
 *
 * @param  mixed  $node  a widget's view data, or any node reached while recursing it
 * @return array<int, array{path: string, value: int, url: string}>
 */
function doorwaysIn(mixed $node, string $path = ''): array
{
    // Objects count too: MediaFindingsWidget's rows are BreakdownRow DTOs, and
    // an array-only walk skipped the one widget family that measures clean.
    if (is_object($node)) {
        $node = get_object_vars($node);
    }

    if (! is_array($node)) {
        return [];
    }

    $found = [];

    // The number is spelled differently per widget — 'value' on stat cards and
    // breakdown rows, 'count' on funnel stages. Discovery must know every
    // spelling or it passes over whole widgets in silence.
    $number = collect(['value', 'count'])
        ->map(fn (string $key): mixed => $node[$key] ?? null)
        ->first(fn (mixed $candidate): bool => is_numeric($candidate));

    if (isset($node['url']) && is_string($node['url']) && $number !== null) {
        $found[] = [
            'path' => $path.(isset($node['key']) && is_string($node['key']) ? ".{$node['key']}" : ''),
            'value' => (int) $number,
            'url' => $node['url'],
        ];
    }

    foreach ($node as $key => $child) {
        if (is_array($child) || is_object($child)) {
            $found = [...$found, ...doorwaysIn($child, $path === '' ? (string) $key : "{$path}.{$key}")];
        }
    }

    return $found;
}

/** The list page each doorway URL lands on. */
function doorwayTarget(string $url): ?string
{
    // Paths read from the resources themselves rather than assumed — my first
    // guess said /admin/curator and silently matched nothing, which is exactly
    // the failure the coverage assertion below exists to catch.
    return match (true) {
        str_contains($url, '/admin/content-items') => ListContentItems::class,
        str_contains($url, '/admin/media') => ListMedia::class,
        str_contains($url, '/admin/public-form-submissions') => ListPublicFormSubmissions::class,
        str_contains($url, '/admin/system/imports') => ListImports::class,
        str_contains($url, '/admin/transcriptions') => ListTranscriptions::class,
        default => null,
    };
}

it('lands every dashboard doorway on exactly the records it counted', function (): void {
    // A library with every awkward shape the dashboard counts: an expired
    // pin (is_pinned true, pin window closed), a scheduled episode, a
    // blocked one, a draft, and a genuinely visible one.
    $publishedGroup = ContentGroup::factory()->published()->create();
    $draftGroup = ContentGroup::factory()->create();

    ContentItem::factory()->published()->for($publishedGroup, 'contentGroup')->withTranscription()->create();
    ContentItem::factory()->published(now()->addWeek())->for($publishedGroup, 'contentGroup')->withTranscription()->create();
    ContentItem::factory()->published()->for($draftGroup, 'contentGroup')->withTranscription()->create();
    ContentItem::factory()->for($publishedGroup, 'contentGroup')->create();
    ContentItem::factory()->create(['is_pinned' => true, 'pinned_at' => now()->subDay(), 'pinned_until' => null]);
    ContentItem::factory()->create(['is_pinned' => true, 'pinned_at' => now()->subDays(9), 'pinned_until' => now()->subDay()]);
    PublicFormSubmission::factory()->count(2)->create();
    // Media rows with no file on disk raise a missing_file finding, so the one
    // widget family that binds its number to its door is actually exercised
    // rather than skipped for want of data.
    Media::factory()->count(2)->create();

    $widgets = [
        EditorialStatsWidget::class,
        PublicationFunnelWidget::class,
        LibraryCompositionWidget::class,
        MediaFindingsWidget::class,
    ];

    $lies = [];
    $discovered = [];

    // Both modes, because some cards only exist in one. The multi-transcription
    // doorway is hidden in single mode, so a single-mode-only walk would have
    // restored a link that nothing checked.
    foreach ([TranscriptionMode::Single, TranscriptionMode::Multi] as $mode) {
        setTestTranscriptionMode($mode);

        foreach ($widgets as $widgetClass) {
            $widget = Livewire::test($widgetClass)->instance();
            $viewData = new ReflectionMethod($widget, 'getViewData');
            $viewData->setAccessible(true);

            foreach (doorwaysIn($viewData->invoke($widget)) as $doorway) {
                $target = doorwayTarget($doorway['url']);

                if ($target === null) {
                    continue;
                }

                $discovered[class_basename($widgetClass)] = ($discovered[class_basename($widgetClass)] ?? 0) + 1;
                $discovered[$doorway['path']] = true;

                parse_str((string) parse_url($doorway['url'], PHP_URL_QUERY), $query);

                $landed = Livewire::withQueryParams($query)->test($target)->instance()->getAllTableRecordsCount();

                if ($landed !== $doorway['value']) {
                    $lies[] = sprintf(
                        '%s %s in %s mode — card says %d, door shows %d',
                        class_basename($widgetClass),
                        $doorway['path'],
                        $mode->value,
                        $doorway['value'],
                        $landed,
                    );
                }
            }
        }
    }

    // A discovery walk that quietly finds nothing would pass forever. Every
    // widget that builds doorway URLs must actually yield some.
    foreach ($widgets as $widgetClass) {
        expect($discovered[class_basename($widgetClass)] ?? 0)
            ->toBeGreaterThan(0, class_basename($widgetClass).' exposes doorways that this walk failed to discover');
    }

    // Named explicitly because it only exists in one mode: a walk that ran
    // single-mode only would pass while never touching it. Matched on the
    // suffix, since the path carries the card's array index.
    $walkedMultiTranscription = collect(array_keys($discovered))
        ->contains(fn (string $path): bool => str_ends_with($path, '.multi_transcription'));

    expect($walkedMultiTranscription)->toBeTrue('the multi-transcription doorway was never walked');

    expect($lies)->toBe([], "a number whose door shows something else is a dead end wearing a doorway's clothes:\n  ".implode("\n  ", $lies));
});

it('records that the intake queue does not pair its numbers with its doors', function (): void {
    // IntakeQueueWidget is the root cause in its purest form: the counts live
    // under 'counts' and the links under 'submissionsUrl'/'importsUrl', so
    // nothing — not even a generic walk — can tell which number belongs to
    // which door. It is excluded from the guard above because there is no
    // pairing to check, and that absence is the defect rather than the design.
    //
    // This is a tripwire, not an endorsement. Bind them and this test fails,
    // which is the signal to move the widget into the guarded set.
    $widget = Livewire::test(IntakeQueueWidget::class)->instance();
    $viewData = new ReflectionMethod($widget, 'getViewData');
    $viewData->setAccessible(true);
    $data = $viewData->invoke($widget);

    expect(doorwaysIn($data))->toBe([])
        ->and($data)->toHaveKeys(['counts', 'submissionsUrl', 'importsUrl']);
});

it('hides the multi-transcription number in single mode, where it can never move', function (): void {
    ContentItem::factory()->count(2)->create();

    $keysFor = function (string $widgetClass): array {
        $widget = Livewire::test($widgetClass)->instance();
        $viewData = new ReflectionMethod($widget, 'getViewData');
        $viewData->setAccessible(true);
        $data = $viewData->invoke($widget);

        return collect($data['cards'] ?? $data['chips'] ?? [])->pluck('key')->all();
    };

    // Transcription::booted() throws on a second transcript while the single
    // lens is active, so in single mode this number can only describe legacy
    // rows and can never change. A stat that cannot move is furniture.
    setTestTranscriptionMode(TranscriptionMode::Single);

    expect($keysFor(EditorialStatsWidget::class))->not->toContain('multi_transcription')
        ->and($keysFor(LibraryCompositionWidget::class))->not->toContain('multi_transcription');

    setTestTranscriptionMode(TranscriptionMode::Multi);

    expect($keysFor(EditorialStatsWidget::class))->toContain('multi_transcription')
        ->and($keysFor(LibraryCompositionWidget::class))->toContain('multi_transcription');
});
