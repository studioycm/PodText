<?php

use App\Models\Author;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\Transcription;
use App\Support\PublicFront\Cards\PublicFrontCardTemplateRegistry;
use App\Support\PublicFront\PublicFrontConfigReader;
use App\Support\PublicFront\PublicFrontConfigRegistry;
use App\Support\PublicFront\PublicFrontRenderContext;
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

/**
 * @param  array<string, array{mode: string, path: string|null}>  $overrides
 */
function step5bBindPreviewDefaultImages(array $overrides = []): void
{
    $config = PublicFrontConfigRegistry::defaults();
    $config['default_images'] = array_replace($config['default_images'], $overrides);
    $context = new PublicFrontRenderContext(
        app(PublicFrontConfigReader::class)->fromArray($config),
    );

    app()->forgetInstance(CardTemplatePreviewer::class);
    app()->forgetInstance(PublicFrontRenderContext::class);
    app()->instance(PublicFrontRenderContext::class, $context);
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

it('renders a public safe inert preview from the current validated image context', function (string $family, string $sourceAttribute): void {
    step5bBindPreviewDefaultImages([
        'content_item' => ['mode' => 'custom', 'path' => 'default-images/preview-item.jpg'],
        'content_group' => ['mode' => 'custom', 'path' => 'default-images/preview-group.jpg'],
        'contributor' => ['mode' => 'custom', 'path' => 'default-images/preview-contributor.jpg'],
    ]);
    $author = Author::factory()->create(['name' => 'Preview Contributor']);
    $item = step5bCreatePublicItem('Preview Episode', $author);

    $preview = app(CardTemplatePreviewer::class)->preview(step5bPreviewDraft($family));

    expect($preview['family'])->toBe($family)
        ->and($preview['sample_id'])->toBe(match ($family) {
            'content_item' => $item->getKey(),
            'content_group' => $item->content_group_id,
            default => $author->getKey(),
        })
        ->and($preview['html'])->toContain('data-card-template-family="'.$family.'"')
        ->and($preview['html'])->toContain($sourceAttribute)
        ->and($preview['html'])->toContain(__('admin.settings_sp3c.preview.link_disabled'))
        ->and($preview['html'])->not->toContain('href=')
        ->and($preview['html'])->not->toContain('wire:click');
})->with([
    'content item' => ['content_item', 'data-card-image-source="content_item_default"'],
    'content group' => ['content_group', 'data-card-image-source="content_group_default"'],
    'contributor' => ['contributor', 'data-contributor-image-source="contributor_default"'],
]);

it('keeps automatic preload and search item ranking in effective image parity', function (): void {
    step5bBindPreviewDefaultImages([
        'content_item' => ['mode' => 'custom', 'path' => 'default-images/ranking-item.jpg'],
    ]);

    $ownImageTie = now()->subDays(2);
    $external = step5bCreatePublicItem(
        'Ranking Parity External',
        transcriptionPublishedAt: $ownImageTie,
    );
    $external->update(['external_thumbnail_url' => 'https://example.test/ranking-external.jpg']);
    $local = step5bCreatePublicItem(
        'Ranking Parity Local',
        transcriptionPublishedAt: $ownImageTie,
    );
    $local->update(['image_path' => 'content-items/images/ranking-local.jpg']);
    $inheritedGroup = ContentGroup::factory()->published()->create([
        'title' => 'Ranking Parity Inherited Group',
        'cover_path' => 'content-groups/covers/ranking-inherited.jpg',
    ]);
    $inherited = step5bCreatePublicItem(
        'Ranking Parity Inherited',
        group: $inheritedGroup,
        transcriptionPublishedAt: now()->subMinute(),
    );
    $configuredDefault = step5bCreatePublicItem(
        'Ranking Parity Configured Default',
        transcriptionPublishedAt: now()->subSeconds(30),
    );

    $draft = ContentItem::factory()->for($external->contentGroup)->create([
        'title' => 'Ranking Parity Draft',
        'image_path' => 'content-items/images/ranking-draft.jpg',
    ]);
    $withoutTranscription = ContentItem::factory()
        ->for($external->contentGroup)
        ->published()
        ->create([
            'title' => 'Ranking Parity Without Transcription',
            'image_path' => 'content-items/images/ranking-without-transcription.jpg',
        ]);
    $future = ContentItem::factory()
        ->for($external->contentGroup)
        ->published(now()->addDay())
        ->create([
            'title' => 'Ranking Parity Future',
            'image_path' => 'content-items/images/ranking-future.jpg',
        ]);
    $futureTranscription = Transcription::factory()->for($future)->published()->create();
    $future->update(['featured_transcription_id' => $futureTranscription->getKey()]);

    $expectedIds = [
        $local->getKey(),
        $external->getKey(),
        $inherited->getKey(),
        $configuredDefault->getKey(),
    ];

    $previewer = app(CardTemplatePreviewer::class);
    $automatic = $previewer->preview(step5bPreviewDraft('content_item'));
    $preloaded = $previewer->initialSampleOptions('content_item');
    $searched = $previewer->sampleOptions('content_item', 'Ranking Parity');

    expect($local->getKey())->toBeGreaterThan($external->getKey())
        ->and($automatic['sample_id'])->toBe($local->getKey())
        ->and($automatic['html'])->toContain('data-card-image-source="item"')
        ->and(array_keys($preloaded))->toBe($expectedIds)
        ->and(array_keys($searched))->toBe($expectedIds)
        ->and($searched)->not->toHaveKeys([
            $draft->getKey(),
            $withoutTranscription->getKey(),
            $future->getKey(),
        ])
        ->and($previewer->preview(step5bPreviewDraft('content_item'), $external->getKey())['html'])
        ->toContain('data-card-image-source="item_external"')
        ->and($previewer->preview(step5bPreviewDraft('content_item'), $inherited->getKey())['html'])
        ->toContain('data-card-image-source="group"')
        ->and($previewer->preview(step5bPreviewDraft('content_item'), $configuredDefault->getKey())['html'])
        ->toContain('data-card-image-source="content_item_default"')
        ->and($previewer->sampleLabel('content_item', $withoutTranscription->getKey()))->toBeNull()
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

it('uses validated global and none modes for item ranking and rendering', function (): void {
    $globalItem = step5bCreatePublicItem('Mode Ranking Global Item');
    step5bBindPreviewDefaultImages([
        'global' => ['mode' => 'custom', 'path' => 'default-images/ranking-global.jpg'],
        'content_item' => ['mode' => 'custom', 'path' => null],
    ]);

    $globalPreview = app(CardTemplatePreviewer::class)->preview(
        step5bPreviewDraft('content_item'),
        $globalItem->getKey(),
    );

    $coveredGroup = ContentGroup::factory()->published()->create([
        'title' => 'Mode Ranking Covered Group',
        'cover_path' => 'content-groups/covers/ranking-covered.jpg',
    ]);
    $noneItem = step5bCreatePublicItem('Mode Ranking None Item', group: $coveredGroup);
    step5bBindPreviewDefaultImages([
        'global' => ['mode' => 'custom', 'path' => 'default-images/ranking-hidden-global.jpg'],
        'content_item' => ['mode' => 'none', 'path' => null],
    ]);

    $previewer = app(CardTemplatePreviewer::class);
    $nonePreview = $previewer->preview(step5bPreviewDraft('content_item'), $noneItem->getKey());

    expect($globalPreview['html'])->toContain('data-card-image-source="global_default"')
        ->and($nonePreview['html'])
        ->toContain('data-card-image-source="fallback"')
        ->not->toContain('data-card-image-source="group"')
        ->not->toContain('data-card-image-source="global_default"')
        ->and(array_keys($previewer->sampleOptions('content_item', 'Mode Ranking')))
        ->toBe([$noneItem->getKey(), $globalItem->getKey()]);
});

it('keeps automatic preload and search group ranking in effective image parity', function (): void {
    step5bBindPreviewDefaultImages([
        'content_group' => ['mode' => 'custom', 'path' => 'default-images/ranking-group.jpg'],
    ]);

    $ownGroup = ContentGroup::factory()->published()->create([
        'title' => 'Group Ranking Z Own',
        'cover_path' => 'content-groups/covers/ranking-own-group.jpg',
    ]);
    step5bCreatePublicItem('Group Ranking Own Episode', group: $ownGroup);
    $defaultGroup = ContentGroup::factory()->published()->create([
        'title' => 'Group Ranking A Default',
    ]);
    step5bCreatePublicItem('Group Ranking Default Episode', group: $defaultGroup);

    $previewer = app(CardTemplatePreviewer::class);
    $automatic = $previewer->preview(step5bPreviewDraft('content_group'));
    $expectedIds = [$ownGroup->getKey(), $defaultGroup->getKey()];

    expect($automatic['sample_id'])->toBe($ownGroup->getKey())
        ->and($automatic['html'])->toContain('data-card-image-source="group"')
        ->and(array_keys($previewer->initialSampleOptions('content_group')))->toBe($expectedIds)
        ->and(array_keys($previewer->sampleOptions('content_group', 'Group Ranking')))->toBe($expectedIds)
        ->and($previewer->preview(step5bPreviewDraft('content_group'), $defaultGroup->getKey())['html'])
        ->toContain('data-card-image-source="content_group_default"');

    step5bBindPreviewDefaultImages([
        'global' => ['mode' => 'custom', 'path' => 'default-images/ranking-global-group.jpg'],
        'content_group' => ['mode' => 'inherit', 'path' => null],
    ]);
    $globalHtml = app(CardTemplatePreviewer::class)
        ->preview(step5bPreviewDraft('content_group'), $defaultGroup->getKey())['html'];

    step5bBindPreviewDefaultImages([
        'global' => ['mode' => 'custom', 'path' => 'default-images/ranking-hidden-group.jpg'],
        'content_group' => ['mode' => 'none', 'path' => null],
    ]);
    $noneHtml = app(CardTemplatePreviewer::class)
        ->preview(step5bPreviewDraft('content_group'), $defaultGroup->getKey())['html'];

    expect($globalHtml)->toContain('data-card-image-source="global_default"')
        ->and($noneHtml)
        ->toContain('data-card-image-source="fallback"')
        ->not->toContain('data-card-image-source="global_default"');
});

it('preserves contributor ordering while rendering its configured default', function (): void {
    step5bBindPreviewDefaultImages([
        'contributor' => ['mode' => 'custom', 'path' => 'default-images/ranking-contributor.jpg'],
    ]);

    $leader = Author::factory()->create(['name' => 'Contributor Ranking Leader']);
    step5bCreatePublicItem('Contributor Ranking Leader One', $leader);
    step5bCreatePublicItem('Contributor Ranking Leader Two', $leader);
    $follower = Author::factory()->create(['name' => 'Contributor Ranking Follower']);
    step5bCreatePublicItem('Contributor Ranking Follower One', $follower);

    $previewer = app(CardTemplatePreviewer::class);

    expect($previewer->preview(step5bPreviewDraft('contributor'))['sample_id'])->toBe($leader->getKey())
        ->and(array_keys($previewer->initialSampleOptions('contributor')))
        ->toBe([$leader->getKey(), $follower->getKey()])
        ->and(array_keys($previewer->sampleOptions('contributor', 'Contributor Ranking')))
        ->toBe([$leader->getKey(), $follower->getKey()])
        ->and($previewer->preview(step5bPreviewDraft('contributor'), $follower->getKey())['html'])
        ->toContain('data-contributor-image-source="contributor_default"');
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
