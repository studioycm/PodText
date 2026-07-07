<?php

namespace Database\Seeders;

use App\Models\Author;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\Transcription;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $host = Author::factory()->create([
            'name' => 'ד"ר יעל בן־דוד',
            'slug' => 'yael-ben-david',
            'bio_markdown' => "חוקרת שפה וטכנולוגיה.\n\nכותבת על עברית, קול וידע.",
        ]);

        $guest = Author::factory()->create([
            'name' => 'נועם לוי',
            'slug' => 'noam-levi',
            'bio_markdown' => "יוצר תוכן ואיש רדיו.\n\nמתמחה בשיחות עומק.",
        ]);

        $publishedGroup = ContentGroup::factory()->published(now()->subDays(10))->create([
            'title' => 'שיחות תמלול',
            'slug' => 'sichot-tamlul',
            'description_markdown' => "פודקאסט על תמלול, שפה וטכנולוגיה.\n\nכולל עברית עם ניקוד: שָׁלוֹם.",
        ]);

        ContentGroup::factory()->create([
            'title' => 'פודקאסט בטיוטה',
            'slug' => 'draft-group',
            'description_markdown' => 'פודקאסט זה איננו גלוי לציבור.',
        ]);

        $publishedItem = ContentItem::factory()->for($publishedGroup)->published(now()->subDays(9))->create([
            'title' => 'איך מתחילים לתמלל בעברית',
            'slug' => 'hebrew-transcription-start',
            'description_markdown' => 'מבוא קצר לתהליך העבודה.',
            'media_url' => 'https://example.com/media/hebrew-transcription-start',
            'duration_seconds' => 1840,
            'original_published_at' => now()->subDays(12),
        ]);

        $publishedTranscription = Transcription::factory()
            ->for($publishedItem)
            ->forAuthor($host)
            ->published($publishedItem->published_at)
            ->create([
                'title' => $publishedItem->title,
                'transcript_markdown' => "## פתיחה\n\nשָׁלוֹם וברוכים הבאים.\n\nזהו תמלול לדוגמה עם **Markdown**.",
            ]);
        $publishedTranscription->syncTranscribers([$host, $guest]);
        $publishedItem->update(['featured_transcription_id' => $publishedTranscription->id]);

        $draftItem = ContentItem::factory()->for($publishedGroup)->create([
            'title' => 'פרק בטיוטה',
            'slug' => 'draft-item',
            'description_markdown' => 'פרק שאינו גלוי עדיין.',
            'media_url' => 'https://example.com/media/draft-item',
        ]);
        Transcription::factory()
            ->for($draftItem)
            ->forAuthor($host)
            ->create([
                'title' => $draftItem->title,
                'transcript_markdown' => 'תוכן בטיוטה.',
            ]);

        $futureItem = ContentItem::factory()->for($publishedGroup)->published(now()->addWeek())->create([
            'title' => 'פרק עתידי',
            'slug' => 'future-item',
            'description_markdown' => 'פרק מתוזמן לעתיד.',
            'media_url' => 'https://example.com/media/future-item',
        ]);
        Transcription::factory()
            ->for($futureItem)
            ->forAuthor($guest)
            ->published($futureItem->published_at)
            ->create([
                'title' => $futureItem->title,
                'transcript_markdown' => 'תוכן שיתפרסם בעתיד.',
            ]);
    }
}
