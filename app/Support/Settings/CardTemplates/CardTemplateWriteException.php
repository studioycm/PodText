<?php

namespace App\Support\Settings\CardTemplates;

use RuntimeException;

class CardTemplateWriteException extends RuntimeException
{
    /**
     * @param  array<int, string>  $details
     */
    public function __construct(string $reason, public readonly array $details = [])
    {
        parent::__construct($reason);
    }

    /**
     * @param  array<int, string>  $details
     */
    public static function named(string $reason, array $details = []): self
    {
        return new self($reason, $details);
    }
}
