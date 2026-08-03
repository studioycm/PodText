<?php

use App\Filament\Resources\PublicFormSubmissions\PublicFormSubmissionResource;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\PublicFormSubmission;
use App\Models\Transcription;
use App\Support\PublicContent\PublicContentCardOptions;
use App\Support\PublicContent\PublicContentItemQueries;
use App\Support\PublicFront\Cards\PublicContentItemCardPresenter;
use App\Support\PublicFront\Cards\PublicFrontCardTemplate;
use App\Support\UiFormats;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders public card word counts through the ui formats home', function (): void {
    $group = ContentGroup::factory()->published()->create();
    $item = ContentItem::factory()->for($group)->published(now()->subDay())->create();
    Transcription::factory()->for($item)->published(now()->subDay())->create([
        'word_count' => 12345,
    ]);

    $template = PublicFrontCardTemplate::fromArray([
        'key' => 'formats_item',
        'label' => 'Formats test template',
        'family' => 'content_item',
        'layout' => 'cards',
        'density' => 'comfortable',
        'image_size' => 'hidden',
        'title_size' => 'base',
        'parts' => [[
            'type' => 'metadata_row',
            'source' => 'transcription',
            'attribute' => 'word_count',
            'visible' => true,
            'order' => 10,
        ]],
    ]);

    $card = app(PublicContentItemCardPresenter::class)->present(
        PublicContentItemQueries::base()->whereKey($item)->firstOrFail(),
        new PublicContentCardOptions,
        $template,
    );

    $wordCountPart = collect($card['parts'])->firstWhere('attribute', 'word_count');

    expect($wordCountPart)->not->toBeNull()
        ->and($wordCountPart['badges'][0]['label'])->toBe(UiFormats::number(12345))
        ->and($wordCountPart['badges'][0]['label'])->toBe('12,345');
});

it('formats the new-submissions navigation badge and hides it at zero', function (): void {
    PublicFormSubmission::factory()->count(2)->create();
    PublicFormSubmission::factory()->reviewed()->create();

    expect(PublicFormSubmissionResource::getNavigationBadge())->toBe(UiFormats::number(2));

    PublicFormSubmission::query()->each(fn (PublicFormSubmission $submission) => $submission->delete());

    expect(PublicFormSubmissionResource::getNavigationBadge())->toBeNull();
});

it('keeps the two routed straggler sites off raw number_format', function (): void {
    // Deliberately scoped to exactly the two sites routed with the post-B1
    // batch — the app-wide number/date sweep (and any widening of
    // UiFormatsPolicyTest's scanned paths) is separately parked work.
    $sources = [
        app_path('Support/PublicFront/Cards/PublicContentItemCardPresenter.php'),
        app_path('Filament/Resources/PublicFormSubmissions/PublicFormSubmissionResource.php'),
    ];

    foreach ($sources as $path) {
        expect(str_contains((string) file_get_contents($path), 'number_format('))
            ->toBeFalse("{$path} must format numbers through UiFormats::number().");
    }
});
