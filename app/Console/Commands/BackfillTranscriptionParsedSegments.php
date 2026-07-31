<?php

namespace App\Console\Commands;

use App\Models\Transcription;
use App\Support\Transcripts\TranscriptSegmentParser;
use Illuminate\Console\Command;

class BackfillTranscriptionParsedSegments extends Command
{
    protected $signature = 'transcriptions:backfill-parsed-segments';

    protected $description = 'Persist parsed transcript segments for transcriptions saved before automatic derivation.';

    public function handle(TranscriptSegmentParser $parser): int
    {
        $backfilled = 0;

        Transcription::withoutTimestamps(function () use ($parser, &$backfilled): void {
            Transcription::query()
                ->whereNull('parsed_segments')
                ->eachById(function (Transcription $transcription) use ($parser, &$backfilled): void {
                    $transcription->forceFill([
                        'parsed_segments' => $parser->parse($transcription->transcript_markdown),
                    ])->saveQuietly();

                    $backfilled++;
                });
        });

        $this->components->info("Backfilled parsed segments for {$backfilled} transcription(s).");

        return self::SUCCESS;
    }
}
