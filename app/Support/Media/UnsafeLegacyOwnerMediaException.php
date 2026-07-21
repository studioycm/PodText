<?php

namespace App\Support\Media;

use InvalidArgumentException;

class UnsafeLegacyOwnerMediaException extends InvalidArgumentException
{
    public function __construct(
        public readonly LegacyOwnerMediaDiagnostic $diagnostic,
        string $message = 'The owner has an unsafe legacy media identity.',
    ) {
        parent::__construct($message);
    }
}
