<?php

namespace App\Support\PublicFront\About;

use App\Support\Markdown\SafeMarkdownRenderer;
use App\Support\PublicFront\PublicFrontConfigReader;
use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Throwable;

class PublicAboutPageRenderer
{
    public function __construct(
        private readonly SafeMarkdownRenderer $markdownRenderer,
    ) {}

    public function renderMarkdown(?string $markdown): string
    {
        return $this->markdownRenderer->toHtml($markdown);
    }

    /**
     * @param  array<string, mixed>|null  $content
     */
    public function renderRichContent(?array $content): string
    {
        if ($content === null || $content === []) {
            return '';
        }

        try {
            $html = RichContentRenderer::make($content)->toHtml();
        } catch (Throwable) {
            return '';
        }

        return $this->sanitizer()->sanitize($html);
    }

    public function imageUrl(?string $path): ?string
    {
        if (! $this->isAllowedPublicImagePath($path)) {
            return null;
        }

        return Storage::disk('public')->url($path);
    }

    /**
     * @param  array<int, array<string, mixed>>  $blocks
     * @return array<int, array{form_key: string, display_mode: string}>
     */
    public function formCtas(array $blocks): array
    {
        $enabledForms = $this->enabledForms();

        return collect($blocks)
            ->filter(fn (array $block): bool => ($block['type'] ?? null) === 'form_cta')
            ->filter(fn (array $block): bool => isset($enabledForms[$block['form_key'] ?? '']))
            ->map(fn (array $block): array => [
                'form_key' => (string) $block['form_key'],
                'display_mode' => (string) ($block['display_mode'] ?? $enabledForms[$block['form_key']]['display_mode_default'] ?? 'modal'),
            ])
            ->unique('form_key')
            ->values()
            ->all();
    }

    public function hasEnabledForm(?string $formKey): bool
    {
        if (blank($formKey)) {
            return false;
        }

        return isset($this->enabledForms()[$formKey]);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function enabledForms(): array
    {
        $definitions = app(PublicFrontConfigReader::class)
            ->read()
            ->group('public_forms')['definitions'] ?? [];

        return collect($definitions)
            ->filter(fn (mixed $definition): bool => is_array($definition))
            ->filter(fn (array $definition): bool => ($definition['enabled'] ?? false) === true && filled($definition['key'] ?? null))
            ->mapWithKeys(fn (array $definition): array => [$definition['key'] => $definition])
            ->all();
    }

    private function isAllowedPublicImagePath(?string $path): bool
    {
        if (! is_string($path) || $path === '') {
            return false;
        }

        if (str_contains($path, '../') || str_contains($path, '//') || str_starts_with($path, '/')) {
            return false;
        }

        $directories = implode('|', PublicAboutPageRegistry::imageDirectories());

        return (bool) preg_match("/^(?:{$directories})\/[A-Za-z0-9][A-Za-z0-9._\/-]*\.(?:jpe?g|png|webp)$/i", $path);
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
