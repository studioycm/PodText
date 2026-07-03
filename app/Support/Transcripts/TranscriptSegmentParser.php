<?php

namespace App\Support\Transcripts;

class TranscriptSegmentParser
{
    /**
     * @return array<int, array{seconds: int, timestamp: string, speaker: string, markdown: string, anchor: string}>
     */
    public function parse(?string $markdown): array
    {
        $markdown = trim(str_replace(["\r\n", "\r"], "\n", $markdown ?? ''));

        if ($markdown === '') {
            return [];
        }

        preg_match_all(
            '/^\[(\d{2}:\d{2}:\d{2})\]\s*([^:\n]{1,120}):[ \t]*(.*)$/m',
            $markdown,
            $matches,
            PREG_OFFSET_CAPTURE,
        );

        if ($matches[0] === []) {
            return [];
        }

        $segments = [];
        $matchCount = count($matches[0]);

        for ($index = 0; $index < $matchCount; $index++) {
            $timestamp = $matches[1][$index][0];
            $speaker = trim($matches[2][$index][0]);
            $inlineMarkdown = trim($matches[3][$index][0]);
            $bodyStart = $matches[0][$index][1] + strlen($matches[0][$index][0]);
            $bodyEnd = $matches[0][$index + 1][1] ?? strlen($markdown);
            $bodyMarkdown = trim(substr($markdown, $bodyStart, $bodyEnd - $bodyStart));
            $segmentMarkdown = trim(implode("\n", array_filter(
                [$inlineMarkdown, $bodyMarkdown],
                fn (string $value): bool => filled($value),
            )));
            $seconds = $this->timestampToSeconds($timestamp);

            $segments[] = [
                'seconds' => $seconds,
                'timestamp' => $timestamp,
                'speaker' => $speaker,
                'markdown' => $segmentMarkdown,
                'anchor' => "t-{$seconds}",
            ];
        }

        return $segments;
    }

    private function timestampToSeconds(string $timestamp): int
    {
        [$hours, $minutes, $seconds] = array_map('intval', explode(':', $timestamp));

        return ($hours * 3600) + ($minutes * 60) + $seconds;
    }
}
