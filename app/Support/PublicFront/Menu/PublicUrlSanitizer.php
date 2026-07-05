<?php

namespace App\Support\PublicFront\Menu;

class PublicUrlSanitizer
{
    public function https(?string $url): ?string
    {
        if (! is_string($url) || trim($url) === '') {
            return null;
        }

        $url = trim($url);

        if (! str_starts_with(strtolower($url), 'https://')) {
            return null;
        }

        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        return $url;
    }
}
