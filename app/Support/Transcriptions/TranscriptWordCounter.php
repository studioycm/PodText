<?php

namespace App\Support\Transcriptions;

class TranscriptWordCounter
{
    /**
     * Count words in canonical transcript Markdown after removing tags and
     * Markdown syntax punctuation, matching the public viewer definition.
     */
    public function count(?string $markdown): int
    {
        $plainText = str((string) $markdown)
            ->stripTags()
            ->replaceMatches('/[\\[\\]()`*_#>:-]+/u', ' ')
            ->squish()
            ->toString();

        if ($plainText === '') {
            return 0;
        }

        return count(preg_split('/\s+/u', $plainText, flags: PREG_SPLIT_NO_EMPTY) ?: []);
    }
}
