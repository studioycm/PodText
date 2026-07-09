<?php

namespace App\Support\PublicFront;

use App\Settings\PublicContentSettings;
use Throwable;

class PublicFrontConfigReader
{
    public function __construct(
        private readonly PublicFrontConfigValidator $validator = new PublicFrontConfigValidator,
        private readonly PublicFrontConfigCache $cache = new PublicFrontConfigCache,
    ) {}

    public function read(?PublicContentSettings $settings = null): PublicFrontConfigResult
    {
        if ($settings instanceof PublicContentSettings) {
            return $this->readFresh($settings);
        }

        return $this->cache->remember(fn (): PublicFrontConfigResult => $this->readFresh($settings));
    }

    public function readFresh(?PublicContentSettings $settings = null): PublicFrontConfigResult
    {
        try {
            $rawConfig = $this->rawConfig($settings ?? app(PublicContentSettings::class));
        } catch (Throwable $exception) {
            return new PublicFrontConfigResult(
                PublicFrontConfigRegistry::defaults(),
                [PublicFrontInvalidConfig::make('public_content', 'settings_unavailable', $exception::class)],
            );
        }

        return $this->validator->validate($rawConfig);
    }

    /**
     * @return array<string, mixed>
     */
    public function all(?PublicContentSettings $settings = null): array
    {
        return $this->read($settings)->config();
    }

    /**
     * @return array<string, mixed>
     */
    public function group(string $key, ?PublicContentSettings $settings = null): array
    {
        return $this->read($settings)->group($key);
    }

    public function fromArray(array $config): PublicFrontConfigResult
    {
        return $this->validator->validate($config);
    }

    /**
     * @return array<string, mixed>
     */
    private function rawConfig(PublicContentSettings $settings): array
    {
        try {
            $values = $settings->getRepository()->getPropertiesInGroup(PublicContentSettings::group());
        } catch (Throwable) {
            $values = $settings->toArray();
        }

        return collect(PublicFrontConfigRegistry::settingsKeys())
            ->mapWithKeys(fn (string $key): array => [$key => $values[$key] ?? PublicFrontConfigRegistry::defaults()[$key]])
            ->all();
    }
}
