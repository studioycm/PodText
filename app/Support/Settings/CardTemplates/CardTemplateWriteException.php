<?php

namespace App\Support\Settings\CardTemplates;

use App\Support\PublicFront\PublicFrontInvalidConfig;
use RuntimeException;

class CardTemplateWriteException extends RuntimeException
{
    /**
     * @param  array<int, string>  $details
     * @param  array<int, PublicFrontInvalidConfig>  $issues
     */
    public function __construct(
        string $reason,
        public readonly array $details = [],
        public readonly array $issues = [],
    ) {
        parent::__construct($reason);
    }

    /**
     * @param  array<int, string>  $details
     */
    public static function named(string $reason, array $details = []): self
    {
        return new self($reason, $details);
    }

    /**
     * @param  array<int, PublicFrontInvalidConfig>  $issues
     */
    public static function validation(array $issues): self
    {
        return new self('validation', issues: array_values($issues));
    }
}
