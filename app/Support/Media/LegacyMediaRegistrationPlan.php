<?php

namespace App\Support\Media;

use App\Enums\ImageUploadPurpose;

readonly class LegacyMediaRegistrationPlan
{
    /**
     * @param  array<int, int>  $contentGroupIds
     * @param  array<int, int>  $contentItemIds
     * @param  array<string, array{sha256: string, locations: array<int, string>, locked: bool}>  $settingsSnapshots
     */
    public function __construct(
        public string $sourcePath,
        public ImageUploadPurpose $purpose,
        public string $sourceContents,
        public string $sourceSha256,
        public ValidatedImage $validatedImage,
        public array $contentGroupIds,
        public array $contentItemIds,
        public array $settingsSnapshots,
    ) {}

    /** @return array<int, string> */
    public function settingsLocations(): array
    {
        return collect($this->settingsSnapshots)
            ->flatMap(fn (array $snapshot): array => $snapshot['locations'])
            ->sort()
            ->values()
            ->all();
    }

    public function referenceCount(): int
    {
        return count($this->contentGroupIds)
            + count($this->contentItemIds)
            + count($this->settingsLocations());
    }

    public function fingerprint(): string
    {
        return hash('sha256', json_encode([
            'source_path' => $this->sourcePath,
            'source_sha256' => $this->sourceSha256,
            'purpose' => $this->purpose->value,
            'content_group_ids' => $this->contentGroupIds,
            'content_item_ids' => $this->contentItemIds,
            'settings' => $this->settingsSnapshots,
        ], JSON_THROW_ON_ERROR));
    }
}
