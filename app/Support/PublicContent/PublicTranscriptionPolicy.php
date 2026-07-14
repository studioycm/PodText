<?php

namespace App\Support\PublicContent;

use App\Support\PublicFront\PublicFrontRenderContext;
use App\Support\Transcriptions\MultiTranscriptionSurfaces;
use Throwable;

class PublicTranscriptionPolicy
{
    public const MODE_FEATURED_ONLY = 'featured_only';

    public const MODE_ALL_PUBLISHED = 'all_published';

    public function __construct(
        public readonly string $publicMode = self::MODE_FEATURED_ONLY,
        public readonly string $countMode = self::MODE_FEATURED_ONLY,
        public readonly bool $showMultipleTranscriptionsOnItemPage = false,
    ) {}

    /**
     * @return array<string>
     */
    public static function modes(): array
    {
        return [
            self::MODE_FEATURED_ONLY,
            self::MODE_ALL_PUBLISHED,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function modeOptions(): array
    {
        return [
            self::MODE_FEATURED_ONLY => __('admin.public_transcription_policy_modes.featured_only'),
            self::MODE_ALL_PUBLISHED => __('admin.public_transcription_policy_modes.all_published'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'public_mode' => self::MODE_FEATURED_ONLY,
            'count_mode' => self::MODE_FEATURED_ONLY,
            'show_multiple_transcriptions_on_item_page' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function fromConfig(array $config): self
    {
        return new self(
            publicMode: self::mode($config['public_mode'] ?? null),
            countMode: self::mode($config['count_mode'] ?? null),
            showMultipleTranscriptionsOnItemPage: (bool) ($config['show_multiple_transcriptions_on_item_page'] ?? false),
        );
    }

    public static function fromContext(?PublicFrontRenderContext $context = null): self
    {
        try {
            $context ??= app(PublicFrontRenderContext::class);

            return self::fromConfig($context->transcriptionPolicy());
        } catch (Throwable) {
            return new self;
        }
    }

    public function publicModeCountsAllPublished(): bool
    {
        return $this->modeForPublicDisplay() === self::MODE_ALL_PUBLISHED;
    }

    public function countModeCountsAllPublished(): bool
    {
        return $this->modeForCounts() === self::MODE_ALL_PUBLISHED;
    }

    public function countModeCountsFeaturedOnly(): bool
    {
        return $this->modeForCounts() === self::MODE_FEATURED_ONLY;
    }

    public function modeForPublicDisplay(): string
    {
        if (! MultiTranscriptionSurfaces::isMultiMode()) {
            return self::MODE_FEATURED_ONLY;
        }

        return $this->publicMode;
    }

    public function modeForCounts(): string
    {
        if (! MultiTranscriptionSurfaces::isMultiMode()) {
            return self::MODE_FEATURED_ONLY;
        }

        return $this->countMode;
    }

    private static function mode(mixed $mode): string
    {
        return is_string($mode) && in_array($mode, self::modes(), true)
            ? $mode
            : self::MODE_FEATURED_ONLY;
    }
}
