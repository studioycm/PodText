<?php

namespace App\Support\PublicFront;

use App\Support\PublicContent\PublicContentCardOptions;

class PublicFrontRenderContext
{
    private ?PublicContentCardOptions $cardOptions = null;

    public function __construct(
        private readonly PublicFrontConfigResult $result,
        private readonly array $settingsValues = [],
    ) {}

    public function result(): PublicFrontConfigResult
    {
        return $this->result;
    }

    /**
     * @return array<string, mixed>
     */
    public function config(): array
    {
        return $this->result->config();
    }

    /**
     * @return array<string, mixed>
     */
    public function group(string $key): array
    {
        return $this->result->group($key);
    }

    /**
     * @return array<string, mixed>
     */
    public function settingsValues(): array
    {
        return $this->settingsValues;
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return $this->settingsValues[$key] ?? $default;
    }

    public function cardOptions(): PublicContentCardOptions
    {
        return $this->cardOptions ??= PublicContentCardOptions::fromValues($this->settingsValues);
    }

    /**
     * @return array<string, mixed>
     */
    public function cardTemplates(): array
    {
        return $this->group('card_templates');
    }

    /**
     * @return array<string, mixed>
     */
    public function displayDefaults(): array
    {
        return $this->group('display_defaults');
    }

    /**
     * @return array<string, mixed>
     */
    public function defaultImages(): array
    {
        return $this->group('default_images');
    }

    /**
     * @return array<string, mixed>
     */
    public function transcriptionPolicy(): array
    {
        return $this->group('transcription_policy');
    }

    /**
     * @return array<string, mixed>
     */
    public function itemPage(): array
    {
        return $this->group('item_page');
    }

    /**
     * @return array<string, mixed>
     */
    public function menu(): array
    {
        return $this->group('menu_config');
    }

    /**
     * @return array<string, mixed>
     */
    public function aboutPage(): array
    {
        return $this->group('about_page');
    }

    /**
     * @return array<string, mixed>
     */
    public function publicForms(): array
    {
        return $this->group('public_forms');
    }

    /**
     * @return array<string, mixed>
     */
    public function routeLabels(): array
    {
        return $this->group('route_labels');
    }

    /**
     * @return array<string, mixed>
     */
    public function podcastsPage(): array
    {
        return $this->group('podcasts_page');
    }

    /**
     * @return array<string, mixed>
     */
    public function contributorsPage(): array
    {
        return $this->group('contributors_page');
    }

    /**
     * @return array<string, mixed>
     */
    public function footer(): array
    {
        return $this->group('footer_config');
    }

    /**
     * @return array<PublicFrontInvalidConfig>
     */
    public function invalidConfig(): array
    {
        return $this->result->invalidConfig();
    }

    /**
     * @return array<array{path: string, reason: string, value_preview: string}>
     */
    public function invalidConfigArray(): array
    {
        return $this->result->invalidConfigArray();
    }

    public function hasInvalidConfig(): bool
    {
        return $this->result->hasInvalidConfig();
    }
}
