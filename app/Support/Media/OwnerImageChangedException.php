<?php

namespace App\Support\Media;

use RuntimeException;

class OwnerImageChangedException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The owner image changed while the media operation was running.');
    }
}
