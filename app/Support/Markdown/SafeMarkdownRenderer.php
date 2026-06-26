<?php

namespace App\Support\Markdown;

use Illuminate\Support\Str;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

final class SafeMarkdownRenderer
{
    public function toHtml(?string $markdown): string
    {
        $markdown = $this->removeExecutableBlocks($markdown ?? '');

        $html = Str::markdown($markdown, [
            'allow_unsafe_links' => false,
            'html_input' => 'strip',
        ]);

        return $this->sanitizer()->sanitize($html);
    }

    private function removeExecutableBlocks(string $markdown): string
    {
        return preg_replace('/<\s*(script|style)\b[^>]*>.*?<\s*\/\s*\1\s*>/is', '', $markdown) ?? '';
    }

    private function sanitizer(): HtmlSanitizer
    {
        $config = (new HtmlSanitizerConfig)
            ->allowSafeElements()
            ->allowLinkSchemes(['http', 'https', 'mailto'])
            ->allowMediaSchemes(['https']);

        return new HtmlSanitizer($config);
    }
}
