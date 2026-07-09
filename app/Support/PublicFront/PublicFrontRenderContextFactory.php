<?php

namespace App\Support\PublicFront;

use App\Settings\PublicContentSettings;
use Throwable;

class PublicFrontRenderContextFactory
{
    public function __construct(
        private readonly PublicFrontConfigReader $reader,
    ) {}

    public function make(?PublicContentSettings $settings = null): PublicFrontRenderContext
    {
        $hasExplicitSettings = $settings instanceof PublicContentSettings;

        try {
            $settings ??= app(PublicContentSettings::class);
            $settingsValues = $this->settingsValues($settings);
        } catch (Throwable) {
            $settings = null;
            $settingsValues = [];
        }

        return new PublicFrontRenderContext(
            result: $hasExplicitSettings && $settings instanceof PublicContentSettings
                ? $this->reader->read($settings)
                : $this->reader->read(),
            settingsValues: $settingsValues,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function settingsValues(PublicContentSettings $settings): array
    {
        try {
            return $settings->getRepository()->getPropertiesInGroup(PublicContentSettings::group());
        } catch (Throwable) {
            return $settings->toArray();
        }
    }
}
