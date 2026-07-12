<?php

namespace App\Support\Media;

use Awcodes\Curator\PathGenerators\Contracts\PathGenerator;

class CuratorPathGenerator implements PathGenerator
{
    public function getPath(?string $baseDir = null): string
    {
        if (blank($baseDir)) {
            return '';
        }

        return trim((string) $baseDir, '/');
    }
}
