<?php

use App\Enums\TranscriptionMode;
use App\Filament\Resources\ContentItems\Pages\CreateEpisodeWorkspace;
use App\Filament\Resources\ContentTags\Pages\CreateContentTag;
use App\Filament\Resources\Transcriptions\Pages\CreateTranscription;
use App\Support\UiTimezone;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Symfony\Component\Finder\SplFileInfo;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Http::preventStrayRequests();
    Mail::fake();
});

/**
 * The admin helper strings that name the UI timezone in their prose.
 *
 * @return array<int, string>
 */
function uiTimezoneHelperKeys(): array
{
    return [
        'admin.helpers.enabled_at',
        'admin.helpers.transcription_published_at',
        'admin.helpers.single.transcription_published_at',
    ];
}

it('interpolates the configured UI timezone into every admin helper string that names it', function (string $locale, string $key): void {
    $raw = Lang::get($key, [], $locale);

    expect($raw)->toBeString()
        ->and($raw)->toContain(':timezone')
        ->and($raw)->not->toContain('Asia/Jerusalem');

    expect(Lang::get($key, ['timezone' => 'Asia/Jerusalem'], $locale))
        ->toContain('Asia/Jerusalem');

    config()->set('localization.ui_timezone', 'Europe/Berlin');

    expect(Lang::get($key, ['timezone' => UiTimezone::name()], $locale))
        ->toContain('Europe/Berlin')
        ->not->toContain('Asia/Jerusalem');
})->with(['he', 'en'])->with(uiTimezoneHelperKeys());

it('passes the configured UI timezone at every call site that renders those helper strings', function (): void {
    $keys = uiTimezoneHelperKeys();

    $callSitesMissingTheReplace = collect(File::allFiles(base_path('app')))
        ->flatMap(function (SplFileInfo $file) use ($keys): array {
            $relative = str($file->getPathname())->after(base_path().DIRECTORY_SEPARATOR)->toString();

            return collect(file($file->getPathname()) ?: [])
                ->map(fn (string $line, int $index): array => [
                    'location' => $relative.':'.($index + 1),
                    'line' => $line,
                ])
                ->filter(fn (array $row): bool => collect($keys)->contains(
                    fn (string $key): bool => str_contains($row['line'], "'{$key}'"),
                ))
                ->reject(fn (array $row): bool => str_contains($row['line'], "'timezone'"))
                ->pluck('location')
                ->all();
        })
        ->values()
        ->all();

    expect($callSitesMissingTheReplace)->toBe([]);
});

it('renders the configured UI timezone in the content tag enabled-at helper text', function (): void {
    config()->set('localization.ui_timezone', 'Europe/Berlin');

    Livewire::test(CreateContentTag::class)
        ->assertOk()
        ->assertSee('Europe/Berlin')
        ->assertDontSee('Asia/Jerusalem');
});

it('renders the configured UI timezone in the single-mode transcription published-at helper text', function (): void {
    setTestTranscriptionMode(TranscriptionMode::Single);
    config()->set('localization.ui_timezone', 'Europe/Berlin');

    Livewire::test(CreateTranscription::class)
        ->assertOk()
        ->assertSee('Europe/Berlin')
        ->assertDontSee('Asia/Jerusalem');
});

it('renders the configured UI timezone in the multi-mode transcription published-at helper text', function (): void {
    setTestTranscriptionMode(TranscriptionMode::Multi);
    config()->set('localization.ui_timezone', 'Europe/Berlin');

    Livewire::test(CreateEpisodeWorkspace::class)
        ->assertOk()
        ->assertSee('Europe/Berlin')
        ->assertDontSee('Asia/Jerusalem');
});
