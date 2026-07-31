<?php

namespace App\Console\Commands;

use App\Models\Transcription;
use App\Support\Transcriptions\TranscriptWordCounter;
use Illuminate\Console\Command;

class BackfillTranscriptionWordCounts extends Command
{
    protected $signature = 'transcriptions:backfill-word-counts';

    protected $description = 'Compute and store word_count for transcriptions that were saved before automatic word counting.';

    public function handle(TranscriptWordCounter $counter): int
    {
        $backfilled = 0;

        Transcription::withoutTimestamps(function () use ($counter, &$backfilled): void {
            Transcription::query()
                ->whereNull('word_count')
                ->eachById(function (Transcription $transcription) use ($counter, &$backfilled): void {
                    $transcription->forceFill([
                        'word_count' => $counter->count($transcription->transcript_markdown),
                    ])->saveQuietly();

                    $backfilled++;
                });
        });

        $this->components->info("Backfilled word counts for {$backfilled} transcription(s).");

        return self::SUCCESS;
    }
}
