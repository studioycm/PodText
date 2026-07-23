<?php

namespace App\Support\Media;

readonly class StorageImageCandidate
{
    public function __construct(
        public string $sourceId,
        public string $sourceLabel,
        public string $disk,
        public string $path,
        public string $filename,
        public string $mode,
        public string $token,
    ) {}

    /** @return array{token: string, filename: string, source: string} */
    public function publicData(): array
    {
        return [
            'token' => $this->token,
            'filename' => $this->filename,
            'source' => $this->sourceLabel,
        ];
    }
}
