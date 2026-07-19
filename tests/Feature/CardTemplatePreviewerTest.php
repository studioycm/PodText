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
                    '_show_label' => true,
                    '_show_icon' => false,
                ],
            ],
            'discarded-non-array-part',
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
                                'order' => '',
                                '_show_label' => false,
                                '_show_icon' => true,
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
            'order' => 10,
            'label' => '',
        ])
        ->not->toHaveKeys(['columns', 'gap', 'alignment', 'children', 'icon', '_show_label', '_show_icon'])
        ->and($candidate['parts'][1]['data']['order'])->toBe(20)
        ->and($candidate['parts'][1]['data']['children'][0]['data'])
        ->toHaveKey('order', 10)
        ->not->toHaveKeys(['children', '_show_label', '_show_icon']);
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
    $groups = collect();

    foreach (range(1, 51) as $index) {
        $group = ContentGroup::factory()->published()->create([
            'title' => sprintf('Preview Group %02d', $index),
        ]);
        step5bCreatePublicItem("Preview Group Episode {$index}", group: $group);
        $groups->push($group);
    }

    $groups->last()->update(['cover_path' => 'preview/group-51.jpg']);

    $options = app(CardTemplatePreviewer::class)->sampleOptions('content_group', 'Preview Group');

    expect($options)->toHaveCount(50)
        ->and(array_values($options)[0])->toBe('Preview Group 51')
        ->and(array_values($options)[49])->toBe('Preview Group 49');
});

it('preloads ten image-first public samples independently from the fifty-result search cap', function (): void {
    foreach (range(1, 11) as $index) {
        $group = ContentGroup::factory()->published()->create([
            'title' => sprintf('Preload Group %02d', $index),
            'cover_path' => $index === 11 ? 'preview/preload-11.jpg' : null,
        ]);
        step5bCreatePublicItem("Preload Group Episode {$index}", group: $group);
    }

    $options = app(CardTemplatePreviewer::class)->initialSampleOptions('content_group');

    expect($options)->toHaveCount(CardTemplatePreviewer::SAMPLE_PRELOAD_LIMIT)
        ->and(array_values($options)[0])->toBe('Preload Group 11')
        ->and($options)->not->toHaveKey(
            ContentGroup::query()->where('title', 'Preload Group 10')->value('id'),
        );
});

it('orders episodes by their own image without treating inherited podcast covers as their own', function (): void {
    $inheritedGroup = ContentGroup::factory()->published()->create([
        'title' => 'Ordering Inherited Podcast',
        'cover_path' => 'preview/inherited-podcast.jpg',
    ]);
    step5bCreatePublicItem(
        'Ordering Episode With Inherited Cover',
        group: $inheritedGroup,
        transcriptionPublishedAt: now(),
    );
    $ownImage = step5bCreatePublicItem(
        'Ordering Episode With Own Image',
        transcriptionPublishedAt: now()->subDay(),
    );
    $ownImage->update(['external_thumbnail_url' => 'https://example.test/own-image.jpg']);

    $options = app(CardTemplatePreviewer::class)->sampleOptions('content_item', 'Ordering Episode');

    expect(array_values($options)[0])->toBe(__('admin.settings_sp3c.preview.sample_item_label', [
        'title' => $ownImage->title,
        'group' => $ownImage->contentGroup->title,
    ]));
});

it('uses the existing missing-image fallback for an image-less episode preview', function (): void {
    $group = ContentGroup::factory()->published()->create([
        'title' => 'Preview Placeholder Podcast',
        'cover_path' => 'preview/podcast-cover.jpg',
    ]);
    $item = step5bCreatePublicItem('Preview Placeholder Episode', group: $group);

    $preview = app(CardTemplatePreviewer::class)->preview(
        step5bPreviewDraft('content_item'),
        $item->getKey(),
    );

    expect($preview['html'])
        ->toContain('data-card-image-source="fallback"')
        ->not->toContain('data-card-image-source="group"')
        ->toContain($item->effectiveTypeLabelSingular());
});

it('finalizes a body-only row preview without media geometry', function (): void {
    $item = step5bCreatePublicItem('Body-only Preview Episode');
    $draft = step5bPreviewDraft('content_item');
    $draft['layout'] = 'rows';
    $draft['image_size'] = 'small';
    $draft['parts'] = [
        [
            'type' => 'custom_text',
            'data' => [
                'source' => 'custom',
                'attribute' => 'text',
                'text' => 'Preview body before title',
                'visible' => true,
                'order' => 10,
            ],
        ],
        [
            'type' => 'title',
            'data' => [
                'source' => 'content_item',
                'attribute' => 'title',
                'visible' => true,
                'order' => 20,
            ],
        ],
    ];

    $html = app(CardTemplatePreviewer::class)->preview($draft, $item->getKey())['html'];

    expect($html)
        ->toContain('data-card-template-layout="rows"')
        ->toContain('data-result-layout="cards"')
        ->toContain('data-card-part-flow="body-only"')
        ->toContain('data-card-renderer-parts="custom_text,title"')
        ->not->toContain('md:grid-cols-[minmax(8rem,12rem)_minmax(0,1fr)]')
        ->not->toContain('data-test="content-item-image"');
});

it('renders every repeated top-level image once in an inert ordered preview', function (): void {
    $item = step5bCreatePublicItem('Multiple-image Preview Episode');
    $draft = step5bPreviewDraft('content_item');
    $draft['layout'] = 'rows';
    $draft['image_size'] = 'small';
    $draft['parts'] = [
        [
            'type' => 'image',
            'data' => [
                'source' => 'content_item',
                'attribute' => 'image',
                'visible' => true,
                'order' => 10,
            ],
        ],
        [
            'type' => 'custom_text',
            'data' => [
                'source' => 'custom',
                'attribute' => 'text',
                'text' => 'Preview between images',
                'visible' => true,
                'order' => 20,
            ],
        ],
        [
            'type' => 'image',
            'data' => [
                'source' => 'content_item',
                'attribute' => 'image',
                'visible' => true,
                'order' => 30,
            ],
        ],
        [
            'type' => 'title',
            'data' => [
                'source' => 'content_item',
                'attribute' => 'title',
                'visible' => true,
                'order' => 40,
            ],
        ],
    ];

    $html = app(CardTemplatePreviewer::class)->preview($draft, $item->getKey())['html'];

    expect(substr_count($html, 'data-test="content-item-image"'))->toBe(2)
        ->and($html)
        ->toContain('data-result-layout="cards"')
        ->toContain('data-card-part-flow="ordered-stack"')
        ->toContain('data-card-renderer-parts="image,custom_text,image,title"')
        ->not->toContain('href=');
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

it('keeps ordered-flow preview variants query-neutral and free of lazy loading', function (): void {
    $item = step5bCreatePublicItem('Ordered-flow Query Preview Episode');
    $item->update(['description_markdown' => null]);
    $item->contentGroup->update(['description_markdown' => null]);
    $sampleIds = [
        'content_item' => $item->getKey(),
        'content_group' => $item->content_group_id,
    ];
    $draftsByFamily = collect($sampleIds)->mapWithKeys(function (int $sampleId, string $family): array {
        $base = [
            ...step5bPreviewDraft($family),
            'layout' => 'rows',
            'image_size' => 'small',
        ];
        $part = fn (string $type, string $source, string $attribute, int $order, array $extra = []): array => [
            'type' => $type,
            'data' => [
                'source' => $source,
                'attribute' => $attribute,
                'visible' => true,
                'order' => $order,
                ...$extra,
            ],
        ];
        $image = fn (int $order): array => $part('image', $family, 'image', $order);
        $title = fn (int $order): array => $part('title', $family, 'title', $order);

        return [$family => [
            'sample_id' => $sampleId,
            'drafts' => [
                'leading' => [...$base, 'parts' => [$image(10), $title(20)]],
                'body_only' => [...$base, 'parts' => [$title(10)]],
                'media_only' => [
                    ...$base,
                    'parts' => [
                        $part('description', $family, 'description', 10),
                        $image(20),
                    ],
                ],
                'hidden' => [
                    ...$base,
                    'image_size' => 'hidden',
                    'parts' => [$image(10), $title(20)],
                ],
                'ordered' => [
                    ...$base,
                    'parts' => [
                        $part('custom_text', 'custom', 'text', 10, ['text' => 'Query-neutral body']),
                        $image(20),
                        $title(30),
                        $image(40),
                    ],
                ],
            ],
        ]];
    });
    $previewer = app(CardTemplatePreviewer::class);
    $queryCounts = $draftsByFamily->map(function (array $case) use ($previewer): array {
        return collect($case['drafts'])->map(function (array $draft) use ($case, $previewer): int {
            DB::enableQueryLog();
            DB::flushQueryLog();
            $previewer->preview($draft, $case['sample_id']);

            return count(DB::getQueryLog());
        })->all();
    });

    Model::preventLazyLoading();

    try {
        foreach ($draftsByFamily as $case) {
            foreach ($case['drafts'] as $draft) {
                $previewer->preview($draft, $case['sample_id']);
            }
        }
    } finally {
        Model::preventLazyLoading(false);
    }

    foreach ($queryCounts as $family => $counts) {
        $baseline = $counts['leading'];
        $deltas = collect($counts)->map(fn (int $count): int => $count - $baseline)->all();

        expect($deltas, $family)->toBe([
            'leading' => 0,
            'body_only' => 0,
            'media_only' => 0,
            'hidden' => 0,
            'ordered' => 0,
        ]);
    }
});
