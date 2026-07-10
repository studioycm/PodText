<?php

namespace Database\Seeders;

use App\Enums\HomepageSectionType;
use App\Enums\PublicationStatus;
use App\Models\Author;
use App\Models\Category;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\ContentTag;
use App\Models\HomepageSection;
use App\Models\Transcription;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DemoHebrewContentSeeder extends Seeder
{
    public const REFERENCE_KEYS = [
        'author_yael' => '01J00000000000000000000001',
        'author_noam' => '01J00000000000000000000002',
        'author_michal' => '01J00000000000000000000003',
        'author_amir' => '01J00000000000000000000004',
        'group_deep_talks' => '01J00000000000000000000010',
        'group_technology' => '01J00000000000000000000011',
        'group_people_culture' => '01J00000000000000000000012',
        'item_ai_tools' => '01J00000000000000000000020',
        'item_listening' => '01J00000000000000000000021',
        'item_hebrew_data' => '01J00000000000000000000022',
        'item_culture_archive' => '01J00000000000000000000023',
        'item_learning' => '01J00000000000000000000024',
        'item_product_decisions' => '01J00000000000000000000025',
        'item_creators_routine' => '01J00000000000000000000026',
        'item_accessible_knowledge' => '01J00000000000000000000027',
        'transcription_ai_tools' => '01J00000000000000000000040',
        'transcription_listening' => '01J00000000000000000000041',
        'transcription_hebrew_data' => '01J00000000000000000000042',
        'transcription_culture_archive' => '01J00000000000000000000043',
        'transcription_learning' => '01J00000000000000000000044',
        'transcription_product_decisions' => '01J00000000000000000000045',
        'transcription_creators_routine' => '01J00000000000000000000046',
        'transcription_accessible_knowledge' => '01J00000000000000000000047',
    ];

    public function run(): void
    {
        $publishedAt = now()->subDays(14);

        $authors = $this->seedAuthors();
        $categories = $this->seedCategories();
        $tags = $this->seedTags();
        $groups = $this->seedContentGroups($categories, $publishedAt);

        $this->seedContentItems($authors, $categories, $tags, $groups, $publishedAt);
        $this->seedHomepageSections();
    }

    /**
     * @return array<string, Author>
     */
    private function seedAuthors(): array
    {
        return [
            'yael' => Author::query()->updateOrCreate(
                ['slug' => 'yael-ben-david'],
                [
                    'reference_key' => self::REFERENCE_KEYS['author_yael'],
                    'name' => 'ד"ר יעל בן דוד',
                    'bio_markdown' => "חוקרת שפה וטכנולוגיה.\n\nמובילה שיחות על ידע, קול ותמלול בעברית.",
                ],
            ),
            'noam' => Author::query()->updateOrCreate(
                ['slug' => 'noam-levi'],
                [
                    'reference_key' => self::REFERENCE_KEYS['author_noam'],
                    'name' => 'נועם לוי',
                    'bio_markdown' => "מגיש ועורך תוכן.\n\nמתמקד בראיונות עומק ובסיפורים אנושיים.",
                ],
            ),
            'michal' => Author::query()->updateOrCreate(
                ['slug' => 'michal-cohen'],
                [
                    'reference_key' => self::REFERENCE_KEYS['author_michal'],
                    'name' => 'מיכל כהן',
                    'bio_markdown' => "עיתונאית תרבות ומדיה.\n\nכותבת על יצירה, קהילה וטכנולוגיה.",
                ],
            ),
            'amir' => Author::query()->updateOrCreate(
                ['slug' => 'amir-shalev'],
                [
                    'reference_key' => self::REFERENCE_KEYS['author_amir'],
                    'name' => 'אמיר שלו',
                    'bio_markdown' => "יזם ומרצה.\n\nחוקר את החיבור בין חינוך, מוצר וקול.",
                ],
            ),
        ];
    }

    /**
     * @return array<string, Category>
     */
    private function seedCategories(): array
    {
        $technology = Category::query()->updateOrCreate(
            ['slug' => 'technology'],
            [
                'name' => 'טכנולוגיה',
                'description_markdown' => 'פרקים על כלים, מערכות ושינויים דיגיטליים.',
                'is_visible' => true,
                'sort_order' => 10,
            ],
        );

        return [
            'technology' => $technology,
            'ai' => Category::query()->updateOrCreate(
                ['slug' => 'artificial-intelligence'],
                [
                    'parent_id' => $technology->getKey(),
                    'name' => 'בינה מלאכותית',
                    'description_markdown' => 'שיחות על מודלים, אוטומציה ושפה.',
                    'is_visible' => true,
                    'sort_order' => 11,
                ],
            ),
            'culture' => Category::query()->updateOrCreate(
                ['slug' => 'culture'],
                [
                    'name' => 'תרבות',
                    'description_markdown' => 'פרקים על יצירה, קהל וסיפורים מקומיים.',
                    'is_visible' => true,
                    'sort_order' => 20,
                ],
            ),
            'education' => Category::query()->updateOrCreate(
                ['slug' => 'education'],
                [
                    'name' => 'חינוך',
                    'description_markdown' => 'למידה, הוראה וידע נגיש.',
                    'is_visible' => true,
                    'sort_order' => 30,
                ],
            ),
            'interviews' => Category::query()->updateOrCreate(
                ['slug' => 'interviews'],
                [
                    'name' => 'ראיונות',
                    'description_markdown' => 'שיחות עומק עם יוצרות, חוקרים ויזמים.',
                    'is_visible' => true,
                    'sort_order' => 40,
                ],
            ),
        ];
    }

    /**
     * @return array<string, ContentTag>
     */
    private function seedTags(): array
    {
        return [
            'ai' => $this->contentTag('ai', 'בינה מלאכותית', 10),
            'entrepreneurship' => $this->contentTag('entrepreneurship', 'יזמות', 20),
            'education' => $this->contentTag('education', 'חינוך', 30),
            'media' => $this->contentTag('media', 'מדיה', 40),
            'hebrew' => $this->contentTag('hebrew', 'עברית', 50),
        ];
    }

    /**
     * @param  array<string, Category>  $categories
     * @return array<string, ContentGroup>
     */
    private function seedContentGroups(array $categories, CarbonInterface $publishedAt): array
    {
        $publishedAt = Carbon::instance($publishedAt);

        $groups = [
            'deep-talks' => ContentGroup::query()->updateOrCreate(
                ['slug' => 'deep-talks'],
                [
                    'reference_key' => self::REFERENCE_KEYS['group_deep_talks'],
                    'title' => 'שיחות עומק',
                    'description_markdown' => "פודקאסט על אנשים, רעיונות ותהליכים.\n\nכל פרק כולל תמלול מלא בעברית.",
                    'group_type_label_singular' => 'Podcast',
                    'group_type_label_plural' => 'Podcasts',
                    'default_item_type_label_singular' => 'Episode',
                    'default_item_type_label_plural' => 'Episodes',
                    'original_language_code' => 'he',
                    'status' => PublicationStatus::Published,
                    'published_at' => $publishedAt,
                    'homepage_order' => 10,
                ],
            ),
            'technology-in-hebrew' => ContentGroup::query()->updateOrCreate(
                ['slug' => 'technology-in-hebrew'],
                [
                    'reference_key' => self::REFERENCE_KEYS['group_technology'],
                    'title' => 'טכנולוגיה בעברית',
                    'description_markdown' => "שיחות בהירות על מוצר, דאטה ובינה מלאכותית.\n\nמיועד למי שרוצה להבין לעומק.",
                    'group_type_label_singular' => 'Podcast',
                    'group_type_label_plural' => 'Podcasts',
                    'default_item_type_label_singular' => 'Episode',
                    'default_item_type_label_plural' => 'Episodes',
                    'original_language_code' => 'he',
                    'status' => PublicationStatus::Published,
                    'published_at' => $publishedAt->copy()->addDay(),
                    'homepage_order' => 20,
                ],
            ),
            'people-and-culture' => ContentGroup::query()->updateOrCreate(
                ['slug' => 'people-and-culture'],
                [
                    'reference_key' => self::REFERENCE_KEYS['group_people_culture'],
                    'title' => 'אנשים ותרבות',
                    'description_markdown' => "סיפורים מקומיים על יצירה, קהילה ושפה.\n\nשיחות קצרות עם עומק.",
                    'group_type_label_singular' => 'Podcast',
                    'group_type_label_plural' => 'Podcasts',
                    'default_item_type_label_singular' => 'Episode',
                    'default_item_type_label_plural' => 'Episodes',
                    'original_language_code' => 'he',
                    'status' => PublicationStatus::Published,
                    'published_at' => $publishedAt->copy()->addDays(2),
                    'homepage_order' => 30,
                ],
            ),
        ];

        $groups['deep-talks']->categories()->syncWithoutDetaching([
            $categories['interviews']->getKey(),
        ]);
        $groups['technology-in-hebrew']->categories()->syncWithoutDetaching([
            $categories['technology']->getKey(),
            $categories['ai']->getKey(),
        ]);
        $groups['people-and-culture']->categories()->syncWithoutDetaching([
            $categories['culture']->getKey(),
        ]);

        return $groups;
    }

    /**
     * @param  array<string, Author>  $authors
     * @param  array<string, Category>  $categories
     * @param  array<string, ContentTag>  $tags
     * @param  array<string, ContentGroup>  $groups
     */
    private function seedContentItems(array $authors, array $categories, array $tags, array $groups, CarbonInterface $publishedAt): void
    {
        $publishedAt = Carbon::instance($publishedAt);

        $items = [
            [
                'group' => 'technology-in-hebrew',
                'reference_key' => self::REFERENCE_KEYS['item_ai_tools'],
                'transcription_reference_key' => self::REFERENCE_KEYS['transcription_ai_tools'],
                'slug' => 'ai-tools-for-editors',
                'title' => 'איך עורכים עובדים עם כלי בינה מלאכותית',
                'description' => 'שיחה מעשית על שילוב כלים חכמים בתהליך עריכה ותמלול.',
                'authors' => ['yael', 'noam'],
                'categories' => ['ai'],
                'tags' => ['ai', 'media', 'hebrew'],
                'duration' => 2140,
                'published_days_ago' => 1,
                'is_pinned' => true,
                'pin_order' => 1,
            ],
            [
                'group' => 'deep-talks',
                'reference_key' => self::REFERENCE_KEYS['item_listening'],
                'transcription_reference_key' => self::REFERENCE_KEYS['transcription_listening'],
                'slug' => 'listening-as-craft',
                'title' => 'הקשבה כמקצוע',
                'description' => 'על שאלות טובות, שקט בשיחה והיכולת לשמוע מעבר למילים.',
                'authors' => ['noam', 'michal'],
                'categories' => ['interviews'],
                'tags' => ['media', 'hebrew'],
                'duration' => 2685,
                'published_days_ago' => 2,
                'is_pinned' => true,
                'pin_order' => 2,
            ],
            [
                'group' => 'technology-in-hebrew',
                'reference_key' => self::REFERENCE_KEYS['item_hebrew_data'],
                'transcription_reference_key' => self::REFERENCE_KEYS['transcription_hebrew_data'],
                'slug' => 'hebrew-data-products',
                'title' => 'מוצרי דאטה בעברית',
                'description' => 'מה מיוחד בבניית חיפוש, תמלול וסיווג עבור עברית.',
                'authors' => ['yael', 'amir'],
                'categories' => ['technology', 'ai'],
                'tags' => ['ai', 'entrepreneurship', 'hebrew'],
                'duration' => 1920,
                'published_days_ago' => 4,
            ],
            [
                'group' => 'people-and-culture',
                'reference_key' => self::REFERENCE_KEYS['item_culture_archive'],
                'transcription_reference_key' => self::REFERENCE_KEYS['transcription_culture_archive'],
                'slug' => 'local-culture-archive',
                'title' => 'למה ארכיון קולי חשוב לתרבות מקומית',
                'description' => 'שיחה על זיכרון, קהילה והדרך שבה קול משמר סיפורים.',
                'authors' => ['michal'],
                'categories' => ['culture'],
                'tags' => ['media', 'hebrew'],
                'duration' => 1750,
                'published_days_ago' => 5,
            ],
            [
                'group' => 'deep-talks',
                'reference_key' => self::REFERENCE_KEYS['item_learning'],
                'transcription_reference_key' => self::REFERENCE_KEYS['transcription_learning'],
                'slug' => 'learning-through-conversation',
                'title' => 'למידה דרך שיחה',
                'description' => 'איך שיחה פתוחה יכולה להפוך לתוכן לימודי מדויק ונגיש.',
                'authors' => ['amir', 'yael'],
                'categories' => ['education', 'interviews'],
                'tags' => ['education', 'hebrew'],
                'duration' => 2415,
                'published_days_ago' => 7,
            ],
            [
                'group' => 'technology-in-hebrew',
                'reference_key' => self::REFERENCE_KEYS['item_product_decisions'],
                'transcription_reference_key' => self::REFERENCE_KEYS['transcription_product_decisions'],
                'slug' => 'product-decisions',
                'title' => 'החלטות מוצר שמתחילות בתמלול',
                'description' => 'על תובנות משתמשים, חיפוש בתוך שיחות והפיכת קול לידע.',
                'authors' => ['amir', 'noam'],
                'categories' => ['technology'],
                'tags' => ['entrepreneurship', 'media'],
                'duration' => 2230,
                'published_days_ago' => 9,
            ],
            [
                'group' => 'people-and-culture',
                'reference_key' => self::REFERENCE_KEYS['item_creators_routine'],
                'transcription_reference_key' => self::REFERENCE_KEYS['transcription_creators_routine'],
                'slug' => 'creators-routine',
                'title' => 'שגרת עבודה של יוצרי תוכן',
                'description' => 'מה קורה מאחורי הקלעים של פודקאסט עצמאי בעברית.',
                'authors' => ['michal', 'noam'],
                'categories' => ['culture'],
                'tags' => ['media', 'entrepreneurship'],
                'duration' => 1560,
                'published_days_ago' => 11,
            ],
            [
                'group' => 'deep-talks',
                'reference_key' => self::REFERENCE_KEYS['item_accessible_knowledge'],
                'transcription_reference_key' => self::REFERENCE_KEYS['transcription_accessible_knowledge'],
                'slug' => 'accessible-knowledge',
                'title' => 'ידע נגיש מתחיל בטקסט טוב',
                'description' => 'שיחה על תמלול, נגישות והאפשרות לחפש בתוך רעיונות.',
                'authors' => ['yael', 'amir'],
                'categories' => ['education'],
                'tags' => ['education', 'hebrew', 'ai'],
                'duration' => 2065,
                'published_days_ago' => 13,
            ],
        ];

        foreach ($items as $itemData) {
            $this->seedContentItem($itemData, $authors, $categories, $tags, $groups, $publishedAt);
        }
    }

    /**
     * @param  array<string, mixed>  $itemData
     * @param  array<string, Author>  $authors
     * @param  array<string, Category>  $categories
     * @param  array<string, ContentTag>  $tags
     * @param  array<string, ContentGroup>  $groups
     */
    private function seedContentItem(
        array $itemData,
        array $authors,
        array $categories,
        array $tags,
        array $groups,
        CarbonInterface $basePublishedAt,
    ): void {
        $basePublishedAt = Carbon::instance($basePublishedAt);

        $publishedAt = $basePublishedAt->copy()->addDays(14 - $itemData['published_days_ago']);
        $contentItem = ContentItem::query()->updateOrCreate(
            ['slug' => $itemData['slug']],
            [
                'reference_key' => $itemData['reference_key'],
                'content_group_id' => $groups[$itemData['group']]->getKey(),
                'title' => $itemData['title'],
                'description_markdown' => $itemData['description'],
                'media_url' => 'https://example.com/media/'.$itemData['slug'],
                'duration_seconds' => $itemData['duration'],
                'original_published_at' => $publishedAt->copy()->subDays(2),
                'is_pinned' => $itemData['is_pinned'] ?? false,
                'pinned_at' => ($itemData['is_pinned'] ?? false) ? $publishedAt : null,
                'pinned_until' => null,
                'pin_order' => $itemData['pin_order'] ?? null,
                'status' => PublicationStatus::Published,
                'published_at' => $publishedAt,
            ],
        );

        $contentItem->categories()->syncWithoutDetaching(
            collect($itemData['categories'])
                ->map(fn (string $key): int => $categories[$key]->getKey())
                ->all(),
        );

        $contentItem->syncTags(
            collect($itemData['tags'])
                ->map(fn (string $key): ContentTag => $tags[$key])
                ->all(),
        );

        $transcription = Transcription::query()->updateOrCreate(
            ['reference_key' => $itemData['transcription_reference_key']],
            [
                'content_item_id' => $contentItem->getKey(),
                'author_id' => $authors[$itemData['authors'][0]]->getKey(),
                'title' => $itemData['title'],
                'language_code' => 'he',
                'transcript_markdown' => $this->transcriptFor($itemData['title'], $itemData['description']),
                'status' => PublicationStatus::Published,
                'published_at' => $publishedAt->copy()->addHours(3),
                'word_count' => 96,
                'speakers' => [
                    ['name' => 'מנחה'],
                    ['name' => 'אורח'],
                ],
                'parsed_segments' => null,
            ],
        );
        $transcription->syncTranscribers(
            collect($itemData['authors'])
                ->map(fn (string $key): int => $authors[$key]->getKey())
                ->all(),
        );

        $contentItem->forceFill([
            'featured_transcription_id' => $transcription->getKey(),
        ])->save();
    }

    private function contentTag(string $slug, string $name, int $order): ContentTag
    {
        $tag = ContentTag::query()
            ->content()
            ->get()
            ->first(fn (ContentTag $tag): bool => $tag->getTranslation('slug', 'he', false) === $slug)
            ?? new ContentTag;

        $tag->forceFill([
            'name' => ['he' => $name],
            'type' => 'content',
            'order_column' => $order,
            'moderation_state' => 'approved',
            'is_enabled' => true,
            'enabled_at' => now(),
        ])->save();

        DB::table('tags')
            ->where('id', $tag->getKey())
            ->update(['slug' => json_encode(['he' => $slug])]);

        return $tag->refresh();
    }

    private function seedHomepageSections(): void
    {
        HomepageSection::query()->updateOrCreate(
            ['slug' => 'top-transcribers'],
            [
                'name' => 'המתמללים המובילים',
                'type' => HomepageSectionType::TopTranscribers,
                'category_id' => null,
                'tag_id' => null,
                'content_group_id' => null,
                'limit' => 3,
                'sort_order' => 5,
                'is_visible' => true,
            ],
        );
    }

    private function transcriptFor(string $title, string $description): string
    {
        return <<<MARKDOWN
        ## {$title}

        {$description}

        מנחה: שלום וברוכים הבאים לפרק נוסף. היום אנחנו מחברים בין רעיון, קול וטקסט.

        אורח: תודה שהזמנתם אותי. בעיניי תמלול טוב מתחיל בהקשבה מדויקת ובהבנה של ההקשר.

        מנחה: כשיש טקסט נקי, אפשר לחפש, לסמן, לשתף ולחזור לנקודות החשובות בלי לאבד את החוויה של השיחה.

        אורח: בדיוק. הטקסט לא מחליף את הקול, הוא פותח עוד דרך לגשת אליו.
        MARKDOWN;
    }
}
