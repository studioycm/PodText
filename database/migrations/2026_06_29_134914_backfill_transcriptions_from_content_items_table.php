<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('content_items')
            ->whereNotNull('transcript_markdown')
            ->where('transcript_markdown', '!=', '')
            ->orderBy('id')
            ->chunkById(100, function ($contentItems): void {
                foreach ($contentItems as $contentItem) {
                    $alreadyBackfilled = DB::table('transcriptions')
                        ->where('content_item_id', $contentItem->id)
                        ->where('transcript_markdown', $contentItem->transcript_markdown)
                        ->exists();

                    if ($alreadyBackfilled) {
                        continue;
                    }

                    $authorId = DB::table('author_content_item')
                        ->where('content_item_id', $contentItem->id)
                        ->orderBy('id')
                        ->value('author_id');

                    $now = now();
                    $transcriptionId = DB::table('transcriptions')->insertGetId([
                        'reference_key' => (string) Str::ulid(),
                        'content_item_id' => $contentItem->id,
                        'author_id' => $authorId,
                        'title' => $contentItem->title,
                        'language_code' => 'he',
                        'transcript_markdown' => $contentItem->transcript_markdown,
                        'status' => $contentItem->status,
                        'published_at' => $contentItem->published_at,
                        'word_count' => null,
                        'speakers' => null,
                        'parsed_segments' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    DB::table('content_items')
                        ->where('id', $contentItem->id)
                        ->whereNull('featured_transcription_id')
                        ->update([
                            'featured_transcription_id' => $transcriptionId,
                            'updated_at' => $now,
                        ]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Backfilled canonical transcript records may have been edited after migration.
        // A rollback cannot reliably distinguish unchanged backfill rows from real user data.
    }
};
