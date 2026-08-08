<?php

namespace App\Console\Commands;

use App\Models\Transcription;
use App\Support\Transcripts\TranscriptSegmentParser;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('transcriptions:backfill-parsed-segments')]
#[Description('Persist parsed transcript segments for transcriptions saved before automatic derivation.')]
class BackfillTranscriptionParsedSegments extends Command
{
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
