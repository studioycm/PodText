<?php

namespace App\Support\Media;

use Awcodes\Curator\PathGenerators\Contracts\PathGenerator;

class CuratorPathGenerator implements PathGenerator
{
    public function __construct(
        private readonly CuratorImageUploadPolicy $policy,
    ) {}

    public function getPath(?string $baseDir = null): string
    {
        if (blank($baseDir)) {
            throw new \InvalidArgumentException('A finite app-owned media root is required.');
        }

        return $this->policy->normalizeRoot((string) $baseDir);
    }
}
