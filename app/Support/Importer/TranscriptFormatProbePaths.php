<?php

namespace App\Support\Importer;

class TranscriptFormatProbePaths
{
    public function __construct(
        private readonly ?string $sampleDirectory = null,
        private readonly ?string $findingsPath = null,
    ) {}

    public function sampleDirectory(): string
    {
        return $this->sampleDirectory ?? storage_path('app/importer/probe');
    }

    public function samplePath(string $documentId): string
    {
        $safeId = preg_replace('/[^A-Za-z0-9_-]/', '_', $documentId) ?: 'document';

        return $this->sampleDirectory()."/{$safeId}.md";
    }

    public function findingsPath(): string
    {
        return $this->findingsPath ?? base_path('docs/research/importer/01-transcript-format-probe.md');
    }
}
