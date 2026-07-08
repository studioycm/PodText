<?php

namespace App\Support\Markdown;

use Illuminate\Support\Str;

final class SafeMarkdownRenderer
{
    /**
     * @var array<string, mixed>
     */
    private const MARKDOWN_OPTIONS = [
        'allow_unsafe_links' => false,
        'html_input' => 'strip',
    ];

    /**
     * @var array<string, mixed>
     */
    private const TRANSCRIPT_MARKDOWN_OPTIONS = [
        'allow_unsafe_links' => false,
        'html_input' => 'strip',
        'renderer' => [
            'soft_break' => "<br>\n",
        ],
    ];

    public function publicContentClasses(): string
    {
        return 'space-y-4 leading-7 text-gray-700 [&_a]:font-medium [&_a]:text-primary-700 [&_a]:underline [&_a]:underline-offset-4 [&_h1]:text-3xl [&_h1]:font-semibold [&_h1]:tracking-normal [&_h1]:text-gray-950 [&_h2]:text-2xl [&_h2]:font-semibold [&_h2]:tracking-normal [&_h2]:text-gray-950 [&_h3]:text-xl [&_h3]:font-semibold [&_h3]:tracking-normal [&_h3]:text-gray-950 [&_h4]:text-lg [&_h4]:font-semibold [&_h4]:tracking-normal [&_h4]:text-gray-950 [&_h5]:text-base [&_h5]:font-semibold [&_h5]:tracking-normal [&_h5]:text-gray-950 [&_h6]:text-sm [&_h6]:font-semibold [&_h6]:tracking-normal [&_h6]:text-gray-950 [&_li]:ms-5 [&_li]:list-disc [&_ol_li]:list-decimal [&_p]:max-w-3xl dark:text-gray-300 dark:[&_a]:text-primary-300 dark:[&_h1]:text-white dark:[&_h2]:text-white dark:[&_h3]:text-white dark:[&_h4]:text-white dark:[&_h5]:text-white dark:[&_h6]:text-white';
    }

    public function publicTranscriptClasses(): string
    {
        return 'space-y-4 break-words leading-8 text-gray-700 [&_a]:font-medium [&_a]:text-primary-700 [&_a]:underline [&_a]:underline-offset-4 [&_blockquote]:border-s-2 [&_blockquote]:border-primary-200 [&_blockquote]:ps-4 [&_blockquote]:text-gray-600 [&_br]:block [&_code]:rounded [&_code]:bg-gray-100 [&_code]:px-1 [&_code]:py-0.5 [&_code]:text-sm [&_em]:italic [&_h1]:text-3xl [&_h1]:font-semibold [&_h1]:tracking-normal [&_h1]:text-gray-950 [&_h2]:text-2xl [&_h2]:font-semibold [&_h2]:tracking-normal [&_h2]:text-gray-950 [&_h3]:text-xl [&_h3]:font-semibold [&_h3]:tracking-normal [&_h3]:text-gray-950 [&_h4]:text-lg [&_h4]:font-semibold [&_h4]:tracking-normal [&_h4]:text-gray-950 [&_h5]:text-base [&_h5]:font-semibold [&_h5]:tracking-normal [&_h5]:text-gray-950 [&_h6]:text-sm [&_h6]:font-semibold [&_h6]:tracking-normal [&_h6]:text-gray-950 [&_li]:ms-5 [&_li]:list-disc [&_ol_li]:list-decimal [&_p]:max-w-4xl [&_p]:leading-8 [&_strong]:font-semibold dark:text-gray-300 dark:[&_a]:text-primary-300 dark:[&_blockquote]:border-primary-700 dark:[&_blockquote]:text-gray-300 dark:[&_code]:bg-gray-800 dark:[&_h1]:text-white dark:[&_h2]:text-white dark:[&_h3]:text-white dark:[&_h4]:text-white dark:[&_h5]:text-white dark:[&_h6]:text-white';
    }

    public function toHtml(?string $markdown): string
    {
        return $this->renderMarkdown($markdown ?? '', self::MARKDOWN_OPTIONS);
    }

    public function toTranscriptHtml(?string $markdown): string
    {
        return $this->renderMarkdown(
            $this->normalizeTranscriptMarkdown($markdown ?? ''),
            self::TRANSCRIPT_MARKDOWN_OPTIONS,
        );
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function renderMarkdown(string $markdown, array $options): string
    {
        $markdown = $this->removeExecutableBlocks($markdown);
        $html = Str::markdown($markdown, $options);

        return $this->removeUnsafeImageSources((string) $html);
    }

    private function normalizeTranscriptMarkdown(string $markdown): string
    {
        return trim(str_replace(["\r\n", "\r"], "\n", $markdown));
    }

    private function removeExecutableBlocks(string $markdown): string
    {
        $cleaned = preg_replace('/<\s*(script|style)\b[^>]*>.*?<\s*\/\s*\1\s*>/is', '', $markdown);

        return $cleaned ?? $markdown;
    }

    private function removeUnsafeImageSources(string $html): string
    {
        $filtered = preg_replace_callback(
            '/<img\b[^>]*\bsrc="([^"]*)"[^>]*>/i',
            function (array $matches): string {
                $src = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');

                return str_starts_with(Str::lower($src), 'https://')
                    ? $matches[0]
                    : '';
            },
            $html,
        );

        return $filtered ?? $html;
    }
}
