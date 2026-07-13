<?php

namespace App\Support\Media;

use App\Rules\ApprovedEmbedUrl;
use App\Rules\HttpsUrl;

class ContentItemMediaRules
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'media_url' => ['required', 'string', 'max:2048', new HttpsUrl],
            'embed_url' => ['nullable', 'string', 'max:2048', new ApprovedEmbedUrl],
            'embed_html' => ['nullable', 'string'],
            'external_thumbnail_url' => ['nullable', 'string', 'max:2048', new HttpsUrl],
            'direct_media_url' => ['nullable', 'string', 'max:2048', new HttpsUrl],
            'media_metadata' => ['nullable', 'array'],
        ];
    }
}
