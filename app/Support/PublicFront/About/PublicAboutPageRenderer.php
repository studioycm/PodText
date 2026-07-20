<?php

namespace App\Support\PublicFront\About;

use App\Enums\ImageUploadPurpose;
use App\Support\Markdown\SafeMarkdownRenderer;
use App\Support\Media\MediaIdentityResolver;
use App\Support\PublicFront\PublicFrontRenderContext;
use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Throwable;

class PublicAboutPageRenderer
{
    public function __construct(
        private readonly SafeMarkdownRenderer $markdownRenderer,
        private readonly PublicFrontRenderContext $context,
        private readonly MediaIdentityResolver $mediaIdentityResolver,
    ) {}

    public function renderMarkdown(?string $markdown): string
    {
        return $this->markdownRenderer->toHtml($markdown);
    }

    public function publicContentClasses(): string
    {
        return $this->markdownRenderer->publicContentClasses();
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

    public function imageUrl(
        ?string $path,
        ?string $referenceKey = null,
        ImageUploadPurpose $purpose = ImageUploadPurpose::AboutImage,
    ): ?string {
        try {
            $path = $this->mediaIdentityResolver->path($referenceKey, $path, $purpose);
        } catch (InvalidArgumentException $exception) {
            report($exception);

            return null;
        }

        if (! $this->isAllowedPublicImagePath($path, $purpose)) {
            return null;
        }

        return Storage::disk('public')->url($path);
    }

    /** @param iterable<int, array<string, mixed>> $records */
    public function primeImageIdentities(iterable $records, ImageUploadPurpose $purpose): void
    {
        $records = collect($records);
        $this->mediaIdentityResolver->primeReferenceKeys(
            $records->pluck('image_media_reference_key'),
            $purpose,
        );
        $this->mediaIdentityResolver->primeLegacyPaths(
            $records->pluck('image_path'),
            $purpose,
        );
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
        $definitions = $this->context->publicForms()['definitions'] ?? [];

        return collect($definitions)
            ->filter(fn (mixed $definition): bool => is_array($definition))
            ->filter(fn (array $definition): bool => ($definition['enabled'] ?? false) === true && filled($definition['key'] ?? null))
            ->mapWithKeys(fn (array $definition): array => [$definition['key'] => $definition])
            ->all();
    }

    private function isAllowedPublicImagePath(?string $path, ImageUploadPurpose $purpose): bool
    {
        if (! is_string($path) || $path === '') {
            return false;
        }

        if (str_contains($path, '../') || str_contains($path, '//') || str_starts_with($path, '/')) {
            return false;
        }

        $directory = preg_quote($purpose->root(), '/');

        return (bool) preg_match("/^{$directory}\/[A-Za-z0-9][A-Za-z0-9._-]*\.(?:jpg|png|webp)$/i", $path);
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
