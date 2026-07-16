<?php

namespace App\Auth\LegacyRoleBackfill;

final class ArtifactVersionException extends BackfillRefusalException
{
    public static function v1(): self
    {
        return new self('AUTHZ1-C v1 artifacts are immutable and unsupported; publish and accept a fresh v2 analysis.');
    }
}
