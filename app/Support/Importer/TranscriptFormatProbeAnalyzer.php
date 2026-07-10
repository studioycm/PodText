<?php

namespace App\Support\Importer;

use Illuminate\Support\Str;

class TranscriptFormatProbeAnalyzer
{
    /**
     * @return array<string, mixed>
     */
    public function analyze(string $documentId, string $markdown): array
    {
        $lines = collect(preg_split('/\R/u', $markdown) ?: [])
            ->map(fn (string $line): string => trim($line));

        $timestampLines = $lines->filter(fn (string $line): bool => preg_match('/(?:\[?\b\d{1,2}:\d{2}(?::\d{2})?\b\]?)/u', $line) === 1);
        $speakerLines = $lines->filter(fn (string $line): bool => preg_match('/^(?:\[?\b\d{1,2}:\d{2}(?::\d{2})?\b\]?\s+)?(?:[-*]\s*)?[\p{Hebrew}A-Za-z][^:\n]{1,60}:\s+\S/u', $line) === 1);
        $headings = $lines->filter(fn (string $line): bool => Str::startsWith($line, '#'));
        $boldCount = preg_match_all('/\*\*[^*]+\*\*/u', $markdown);
        $nonEmpty = $lines->filter()->values();

        return [
            'bold_count' => $boldCount,
            'candidate_profile' => $this->candidateProfile($timestampLines->count(), $speakerLines->count(), $headings->count()),
            'closer_lines' => $nonEmpty->take(-5)->values()->all(),
            'document_id' => $documentId,
            'heading_count' => $headings->count(),
            'heading_samples' => $headings->take(5)->values()->all(),
            'opener_lines' => $nonEmpty->take(5)->values()->all(),
            'speaker_label_count' => $speakerLines->count(),
            'speaker_label_samples' => $speakerLines->take(5)->values()->all(),
            'timestamp_count' => $timestampLines->count(),
            'timestamp_samples' => $timestampLines->take(5)->values()->all(),
        ];
    }

    private function candidateProfile(int $timestampCount, int $speakerCount, int $headingCount): string
    {
        if ($timestampCount > 0 && $speakerCount > 0) {
            return 'timestamped_dialogue';
        }

        if ($speakerCount > 0) {
            return 'speaker_dialogue';
        }

        if ($headingCount > 0) {
            return 'heading_blocks';
        }

        return 'plain_markdown';
    }
}
