<?php

namespace App\Console\Commands;

use App\Models\Transcription;
use App\Support\Transcriptions\TranscriptWordCounter;
use Illuminate\Console\Command;

class BackfillTranscriptionWordCounts extends Command
{
    protected $signature = 'transcriptions:backfill-word-counts
                            {--all : Recompute every transcription, including rows that already store a value}';

    protected $description = 'Compute and store word_count for transcriptions that were saved before automatic word counting.';

    public function handle(TranscriptWordCounter $counter): int
    {
        $recomputeAll = (bool) $this->option('all');
        $backfilled = 0;

        Transcription::withoutTimestamps(function () use ($counter, $recomputeAll, &$backfilled): void {
            Transcription::query()
                ->unless($recomputeAll, fn ($query) => $query->whereNull('word_count'))
                ->eachById(function (Transcription $transcription) use ($counter, &$backfilled): void {
                    $computed = $counter->count($transcription->transcript_markdown);

                    if ($transcription->word_count !== null && (int) $transcription->word_count === $computed) {
                        return;
                    }

                    $transcription->forceFill(['word_count' => $computed])->saveQuietly();

                    $backfilled++;
                });
        });

        $this->components->info("Backfilled word counts for {$backfilled} transcription(s).");

        return self::SUCCESS;
    }
}
