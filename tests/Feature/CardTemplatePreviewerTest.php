<?php

use App\Models\Author;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\Transcription;
use App\Settings\AdminUxSettings;
use App\Settings\PublicContentSettings;
use App\Support\PublicFront\Cards\PublicFrontCardTemplateRegistry;
use App\Support\Settings\CardTemplates\CardTemplateDraftNormalizer;
use App\Support\Settings\CardTemplates\CardTemplatePreviewer;
use App\Support\Settings\CardTemplates\CardTemplateWriteException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Http::preventStrayRequests();
    Mail::fake();
});

/**
 * @return array<string, mixed>
 */
function step5bPreviewDraft(string $family): array
{
    $template = PublicFrontCardTemplateRegistry::defaultTemplateForFamily($family);
    $template['label'] = "Unsaved {$family} preview";
    $template['parts'] = collect($template['parts'])
        ->map(fn (array $part): array => [
            'type' => $part['type'],
            'data' => Arr::except($part, 'type'),
        ])
        ->all();

    return $template;
}

function step5bCreatePublicItem(
    string $title,
    ?Author $author = null,
    ?ContentGroup $group = null,
    ?DateTimeInterface $transcriptionPublishedAt = null,
): ContentItem {
    $group ??= ContentGroup::factory()->published()->create();
    $item = ContentItem::factory()
        ->for($group)
        ->published()
        ->create(['title' => $title]);
    $transcriptionFactory = Transcription::factory()
        ->for($item)
        ->published($transcriptionPublishedAt ?? now()->subMinute());

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

it('shares exact builder transport cleanup between preview and persistence', function (): void {
    $candidate = app(CardTemplateDraftNormalizer::class)->candidate([
        ...step5bPreviewDraft('content_item'),
        'parts' => [
            [
                'type' => 'title',
                'data' => [
                    'source' => 'content_item',
                    'attribute' => 'title',
                    'visible' => false,
                    'order' => 0,
                    'label' => '',
                    'columns' => '3',
                    'gap' => 'spacious',
                    'alignment' => 'between',
                    'children' => [],
                    'icon' => null,
                ],
            ],
            [
                'type' => 'part_group',
                'data' => [
                    'visible' => true,
                    'order' => 10,
                    'children' => [
                        [
                            'type' => 'custom_text',
                            'data' => [
                                'source' => 'custom',
                                'attribute' => 'text',
                                'text' => 'Nested',
                                'visible' => true,
                                'order' => 0,
                                'children' => [],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ]);

    expect($candidate['parts'][0]['data'])
        ->toMatchArray([
            'visible' => false,
            'order' => 0,
            'label' => '',
        ])
        ->not->toHaveKeys(['columns', 'gap', 'alignment', 'children', 'icon'])
        ->and($candidate['parts'][1]['data']['children'][0]['data'])
        ->not->toHaveKey('children');
});

it('renders a public safe inert preview without resolving configured settings', function (string $family): void {
    $author = Author::factory()->create(['name' => 'Preview Contributor']);
    $item = step5bCreatePublicItem('Preview Episode', $author);

    app()->forgetInstance(PublicContentSettings::class);
    app()->bind(PublicContentSettings::class, fn (): never => throw new RuntimeException('Preview resolved configured settings.'));
    app()->forgetInstance(AdminUxSettings::class);
    app()->bind(AdminUxSettings::class, fn (): never => throw new RuntimeException('Preview resolved configured admin settings.'));

    $preview = app(CardTemplatePreviewer::class)->preview(step5bPreviewDraft($family));

    expect($preview['family'])->toBe($family)
        ->and($preview['sample_id'])->toBe(match ($family) {
            'content_item' => $item->getKey(),
            'content_group' => $item->content_group_id,
            default => $author->getKey(),
        })
        ->and($preview['html'])->toContain('data-card-template-family="'.$family.'"')
        ->and($preview['html'])->toContain(__('admin.settings_sp3c.preview.link_disabled'))
        ->and($preview['html'])->not->toContain('href=')
        ->and($preview['html'])->not->toContain('wire:click');
})->with([
    'content item' => ['content_item'],
    'content group' => ['content_group'],
    'contributor' => ['contributor'],
]);

it('selects the deterministic newest public item and rejects a forged sample identity', function (): void {
    step5bCreatePublicItem('Older Preview Episode', transcriptionPublishedAt: now()->subDays(2));
    $newer = step5bCreatePublicItem('Newer Preview Episode', transcriptionPublishedAt: now()->subDay());

    $previewer = app(CardTemplatePreviewer::class);
    $preview = $previewer->preview(step5bPreviewDraft('content_item'));

    expect($preview['sample_id'])->toBe($newer->getKey())
        ->and(fn () => $previewer->preview(step5bPreviewDraft('content_item'), PHP_INT_MAX))
        ->toThrow(CardTemplateWriteException::class, 'preview_sample_missing');
});

it('caps public sample search results at fifty in deterministic order', function (): void {
    foreach (range(1, 51) as $index) {
        $group = ContentGroup::factory()->published()->create([
            'title' => sprintf('Preview Group %02d', $index),
        ]);
        step5bCreatePublicItem("Preview Group Episode {$index}", group: $group);
    }

    $options = app(CardTemplatePreviewer::class)->sampleOptions('content_group', 'Preview Group');

    expect($options)->toHaveCount(50)
        ->and(array_values($options)[0])->toBe('Preview Group 01')
        ->and(array_values($options)[49])->toBe('Preview Group 50');
});

it('rejects an invalid unsaved draft without a configured-template fallback', function (): void {
    $draft = step5bPreviewDraft('content_item');
    $draft['family'] = 'episode';

    expect(fn () => app(CardTemplatePreviewer::class)->preview($draft))
        ->toThrow(CardTemplateWriteException::class, 'validation');
});

it('keeps one sample query plane constant by family without lazy loading', function (): void {
    $author = Author::factory()->create(['name' => 'Query Preview Contributor']);
    $item = step5bCreatePublicItem('Query Preview Episode', $author);
    $sampleIds = [
        'content_item' => $item->getKey(),
        'content_group' => $item->content_group_id,
        'contributor' => $author->getKey(),
    ];
    $previewer = app(CardTemplatePreviewer::class);
    $counts = collect(range(1, 3))->map(function () use ($previewer, $sampleIds): array {
        return collect($sampleIds)->mapWithKeys(function (int $sampleId, string $family) use ($previewer): array {
            DB::enableQueryLog();
            DB::flushQueryLog();
            $previewer->preview(step5bPreviewDraft($family), $sampleId);

            return [$family => count(DB::getQueryLog())];
        })->all();
    });

    Model::preventLazyLoading();

    try {
        foreach ($sampleIds as $family => $sampleId) {
            $previewer->preview(step5bPreviewDraft($family), $sampleId);
        }
    } finally {
        Model::preventLazyLoading(false);
    }

    expect($counts)->toHaveCount(3)
        ->and($counts->pluck('content_item')->unique())->toHaveCount(1)
        ->and($counts->pluck('content_group')->unique())->toHaveCount(1)
        ->and($counts->pluck('contributor')->unique())->toHaveCount(1);
});
