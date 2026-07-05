<?php

namespace App\Support\Markdown;

use Illuminate\Support\Str;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

final class SafeMarkdownRenderer
{
    public function publicContentClasses(): string
    {
        return 'space-y-4 leading-7 text-gray-700 [&_a]:font-medium [&_a]:text-primary-700 [&_a]:underline [&_a]:underline-offset-4 [&_h1]:text-3xl [&_h1]:font-semibold [&_h1]:tracking-normal [&_h1]:text-gray-950 [&_h2]:text-2xl [&_h2]:font-semibold [&_h2]:tracking-normal [&_h2]:text-gray-950 [&_h3]:text-xl [&_h3]:font-semibold [&_h3]:tracking-normal [&_h3]:text-gray-950 [&_h4]:text-lg [&_h4]:font-semibold [&_h4]:tracking-normal [&_h4]:text-gray-950 [&_h5]:text-base [&_h5]:font-semibold [&_h5]:tracking-normal [&_h5]:text-gray-950 [&_h6]:text-sm [&_h6]:font-semibold [&_h6]:tracking-normal [&_h6]:text-gray-950 [&_li]:ms-5 [&_li]:list-disc [&_ol_li]:list-decimal [&_p]:max-w-3xl dark:text-gray-300 dark:[&_a]:text-primary-300 dark:[&_h1]:text-white dark:[&_h2]:text-white dark:[&_h3]:text-white dark:[&_h4]:text-white dark:[&_h5]:text-white dark:[&_h6]:text-white';
    }

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
