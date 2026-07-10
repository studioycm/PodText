<?php

namespace App\Support\Importer;

class ImporterThrottle
{
    public function __construct(
        private readonly ?int $milliseconds = null,
    ) {}

    public function wait(string $operation, int $attempt = 1): void
    {
        $milliseconds = $this->milliseconds ?? (int) config('services.importer.google_throttle_ms', 0);

        if ($milliseconds <= 0) {
            return;
        }

        usleep($milliseconds * max(1, $attempt) * 1000);
    }
}
