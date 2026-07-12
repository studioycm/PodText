<?php

namespace App\Support\Media;

class EpisodeEmbedInputNormalizer
{
    public function iframeSrc(?string $input): ?string
    {
        if (blank($input)) {
            return null;
        }

        $input = trim((string) $input);

        if (! str_contains($input, '<iframe')) {
            return $input;
        }

        if (preg_match('/\ssrc=(["\'])(.*?)\1/i', $input, $matches) !== 1) {
            return null;
        }

        return html_entity_decode($matches[2], ENT_QUOTES | ENT_HTML5);
    }
}
