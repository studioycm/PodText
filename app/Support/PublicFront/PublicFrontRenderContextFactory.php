<?php

namespace App\Support\PublicFront;

use App\Settings\PublicContentSettings;

class PublicFrontRenderContextFactory
{
    public function __construct(
        private readonly PublicFrontConfigReader $reader,
    ) {}

    public function make(?PublicContentSettings $settings = null): PublicFrontRenderContext
    {
        return new PublicFrontRenderContext(
            result: $this->reader->read($settings),
        );
    }
}
