<?php

namespace Database\Seeders;

use App\Models\Author;
use App\Models\ContentGroup;
use App\Models\ContentItem;
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
            'title' => 'קבוצה בטיוטה',
            'slug' => 'draft-group',
            'description_markdown' => 'קבוצה זו אינה גלויה לציבור.',
        ]);

        $publishedItem = ContentItem::factory()->for($publishedGroup)->published(now()->subDays(9))->create([
            'title' => 'איך מתחילים לתמלל בעברית',
            'slug' => 'hebrew-transcription-start',
            'description_markdown' => 'מבוא קצר לתהליך העבודה.',
            'media_url' => 'https://example.com/media/hebrew-transcription-start',
            'duration_seconds' => 1840,
            'transcript_markdown' => "## פתיחה\n\nשָׁלוֹם וברוכים הבאים.\n\nזהו תמלול לדוגמה עם **Markdown**.",
            'original_published_at' => now()->subDays(12),
        ]);

        $publishedItem->authors()->attach([$host->id, $guest->id]);

        ContentItem::factory()->for($publishedGroup)->create([
            'title' => 'פרק בטיוטה',
            'slug' => 'draft-item',
            'description_markdown' => 'פרק שאינו גלוי עדיין.',
            'media_url' => 'https://example.com/media/draft-item',
            'transcript_markdown' => 'תוכן בטיוטה.',
        ]);

        ContentItem::factory()->for($publishedGroup)->published(now()->addWeek())->create([
            'title' => 'פרק עתידי',
            'slug' => 'future-item',
            'description_markdown' => 'פרק מתוזמן לעתיד.',
            'media_url' => 'https://example.com/media/future-item',
            'transcript_markdown' => 'תוכן שיתפרסם בעתיד.',
        ]);
    }
}
