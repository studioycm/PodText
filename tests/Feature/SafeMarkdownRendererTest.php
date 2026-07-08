<?php

use App\Support\Markdown\SafeMarkdownRenderer;

function safeMarkdownRenderer(): SafeMarkdownRenderer
{
    return app(SafeMarkdownRenderer::class);
}

it('renders long utf8 transcript markdown without truncating the final token', function (): void {
    $markdown = str_repeat("מקטע עברי ארוך עם תוכן transcript\n", 1800).'FINAL_LONG_TRANSCRIPT_TOKEN';

    expect(strlen($markdown))->toBeGreaterThan(20_000);

    $html = safeMarkdownRenderer()->toTranscriptHtml($markdown);

    expect($html)
        ->not->toBe('')
        ->toContain('FINAL_LONG_TRANSCRIPT_TOKEN');
});

it('renders a large hebrew transcript without the preg null wipe', function (): void {
    $markdown = '';

    while (strlen($markdown) < 310_000) {
        $markdown .= "שורה ארוכה בעברית עם **הדגשה** וקישור בטוח https://example.com\n";
    }

    $markdown .= 'FINAL_300KB_HEBREW_TOKEN';

    $html = safeMarkdownRenderer()->toTranscriptHtml($markdown);

    expect($html)
        ->not->toBe('')
        ->toContain('FINAL_300KB_HEBREW_TOKEN');
});

it('preserves transcript markdown styling paragraphs and soft breaks', function (): void {
    $html = safeMarkdownRenderer()->toTranscriptHtml(<<<'MARKDOWN'
Paragraph one with **bold**, *italic*, and ***bold italic***.
single soft break

Paragraph two.
MARKDOWN);

    expect($html)
        ->toContain('<strong>bold</strong>')
        ->toContain('<em>italic</em>')
        ->toContain('<br')
        ->toContain('<p>Paragraph one')
        ->toContain('<p>Paragraph two.</p>')
        ->and($html)->toMatch('/<(strong><em|em><strong)>bold italic<\/(em|strong)><\/(strong|em)>/');
});

it('keeps markdown output safe without the capped sanitizer pass', function (string $markdown, array $forbidden): void {
    $html = safeMarkdownRenderer()->toTranscriptHtml($markdown);

    foreach ($forbidden as $needle) {
        expect($html)->not->toContain($needle);
    }
})->with([
    'script block' => ["Before <script>alert('x')</script> After", ['<script', '</script>', "alert('x')"]],
    'style block' => ['Before <style>body{display:none}</style> After', ['<style', '</style>', 'display:none']],
    'iframe html' => ['Before <iframe src="https://example.com"></iframe> After', ['<iframe', '</iframe>']],
    'onclick html' => ['<span onclick="alert(1)">Clickable</span>', ['onclick', 'alert(1)', '<span']],
    'onerror html' => ['<img src="x" onerror="alert(1)">', ['onerror', 'alert(1)', '<img']],
    'javascript link' => ['[unsafe](javascript:alert(1))', ['javascript:', 'alert(1)']],
    'javascript image' => ['![unsafe](javascript:alert(1))', ['javascript:', 'alert(1)', '<img']],
]);

it('keeps html entities inert while allowing safe links', function (): void {
    $html = safeMarkdownRenderer()->toTranscriptHtml('Entity: &lt;script&gt;safe&lt;/script&gt; and [safe](https://example.com/path).');

    expect($html)
        ->toContain('&lt;script&gt;safe&lt;/script&gt;')
        ->toContain('href="https://example.com/path"')
        ->not->toContain('<script>');
});

it('removes non-https markdown images while keeping https images', function (): void {
    $html = safeMarkdownRenderer()->toTranscriptHtml('![blocked](http://example.com/image.png) ![allowed](https://example.com/image.png)');

    expect($html)
        ->not->toContain('http://example.com/image.png')
        ->toContain('src="https://example.com/image.png"');
});
