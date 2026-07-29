<?php

namespace App\Support\Media;

use App\Enums\ImageUploadPurpose;
use App\Enums\MediaAttachmentRole;
use App\Enums\MediaMutationOperationType;
use App\Enums\MediaMutationStatus;
use App\Models\Media;
use App\Settings\PublicContentSettings;
use App\Support\PublicFront\PublicFrontConfigRegistry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Throwable;

class MediaIntegrityReporter
{
    public function __construct(
        private readonly CuratorImageUploadPolicy $policy,
        private readonly MediaRecordScope $scope,
        private readonly MediaReferenceFinder $references,
        private readonly StoredMediaValidator $storedMediaValidator,
    ) {}

    /**
     * @return array{
     *     media: array<int, array<string, mixed>>,
     *     duplicate_locations: array<int, array<string, mixed>>,
     *     attachment_issues: array<int, array<string, mixed>>,
     *     orphan_attachments: array<int, array<string, mixed>>,
     *     settings_identity_issues: array<int, array<string, mixed>>,
     *     incomplete_mutations: array<int, array<string, mixed>>
     * }
     */
    public function report(): array
    {
        $media = Media::query()->orderBy('id')->get();
        $mediaById = $media->keyBy(fn (Media $record): int => (int) $record->getKey());
        $attachments = $this->attachmentRows();
        $attachmentsByMedia = $attachments->groupBy('media_id');
        $duplicateLocations = $this->duplicateLocations($media);
        $duplicateLocationKeys = collect($duplicateLocations)
            ->mapWithKeys(fn (array $row): array => [$this->locationKey($row['disk'], $row['path']) => true]);
        $attachmentIssues = $this->attachmentIssues($attachments, $mediaById);
        $attachmentIssueIds = collect($attachmentIssues)->pluck('attachment_id')->flip();

        $this->references->prime($media);

        try {
            $mediaRows = $media
                ->map(fn (Media $record): array => $this->mediaRow(
                    $record,
                    $attachmentsByMedia->get((int) $record->getKey(), collect()),
                    $duplicateLocationKeys->has($this->locationKey((string) $record->disk, (string) $record->path)),
                    $attachmentIssueIds,
                ))
                ->all();
        } finally {
            $this->references->clearPrime();
        }

        $report = [
            'media' => $mediaRows,
            'duplicate_locations' => $duplicateLocations,
            'attachment_issues' => $attachmentIssues,
            'orphan_attachments' => collect($attachmentIssues)
                ->filter(fn (array $issue): bool => collect($issue['issues'])->intersect([
                    'invalid_attachable_type',
                    'missing_media',
                    'missing_owner',
                ])->isNotEmpty())
                ->values()
                ->all(),
            'settings_identity_issues' => $this->settingsIdentityIssues($media),
            'incomplete_mutations' => $this->incompleteMutations(),
        ];

        $report['summary'] = collect($report)
            ->map(fn (array $rows): int => count($rows))
            ->all();

        return $report;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $attachments
     * @param  Collection<int, int>  $attachmentIssueIds
     * @return array<string, mixed>
     */
    private function mediaRow(
        Media $media,
        Collection $attachments,
        bool $duplicateLocation,
        Collection $attachmentIssueIds,
    ): array {
        $metadataAllowed = $this->scope->allowsForBackfill($media);
        $browseEligible = $this->scope->allows($media);
        $filesystem = $this->filesystemEvidence($media);
        $contentAllowed = false;
        $contentError = null;

        $pendingSvgSanitation = blank($media->reference_key) && $media->type === 'image/svg+xml';

        if ($pendingSvgSanitation) {
            $contentError = 'Existing SVG media is pending the separately approved sanitation runbook.';
        } elseif ($filesystem['state'] === 'present' && $metadataAllowed) {
            try {
                $this->storedMediaValidator->validate($media);
                $contentAllowed = true;
            } catch (Throwable $exception) {
                $contentError = $exception->getMessage();
            }
        }

        $allowed = $browseEligible && $contentAllowed;

        $legacyReferences = $this->references->legacyReferencesForMedia($media);
        $attachmentReferences = $attachments
            ->map(fn (array $attachment): array => [
                'id' => $attachment['id'],
                'attachable_type' => $attachment['attachable_type'],
                'attachable_id' => $attachment['attachable_id'],
                'role' => $attachment['role'],
                'position' => $attachment['position'],
            ])
            ->values()
            ->all();
        $hasAttachmentIssue = $attachments->contains(
            fn (array $attachment): bool => $attachmentIssueIds->has($attachment['id']),
        );

        return [
            'media_id' => (int) $media->getKey(),
            'reference_key' => $media->reference_key,
            'disk' => $media->disk,
            'path' => $media->path,
            'root' => $media->directory,
            'mime_type' => $media->type,
            'extension' => $media->ext,
            'file_state' => $filesystem['state'],
            'file_exists' => $filesystem['state'] === 'present',
            'sha256' => $filesystem['sha256'],
            'metadata_allowed' => $metadataAllowed,
            'content_allowed' => $contentAllowed,
            'content_error' => $contentError,
            'browse_eligible' => $allowed,
            'legacy_references' => $legacyReferences,
            'attachment_references' => $attachmentReferences,
            'allowed' => $allowed,
            'recommended_disposition' => match (true) {
                in_array($filesystem['state'], ['unsafe_path', 'wrong_disk', 'read_error'], true) => 'manual_filesystem_review',
                $filesystem['state'] === 'missing' => 'recover_or_detach_missing_file',
                $pendingSvgSanitation && $metadataAllowed => 'pending_svg_sanitation',
                ! $metadataAllowed || ! $contentAllowed => 'review_and_quarantine',
                $duplicateLocation => 'investigate_duplicate_location',
                blank($media->reference_key) => 'backfill_reference_key',
                $hasAttachmentIssue => 'repair_attachment_integrity',
                $legacyReferences !== [] && $attachmentReferences === [] => 'backfill_attachment_or_setting_key',
                $legacyReferences === [] && $attachmentReferences === [] => 'review_unattached_media',
                default => 'retain',
            },
        ];
    }

    /** @return Collection<int, array<string, mixed>> */
    private function attachmentRows(): Collection
    {
        if (! Schema::hasTable('media_attachments')) {
            return collect();
        }

        return DB::table('media_attachments')
            ->orderBy('id')
            ->get(['id', 'media_id', 'attachable_type', 'attachable_id', 'role', 'position'])
            ->map(fn (object $row): array => [
                'id' => (int) $row->id,
                'media_id' => (int) $row->media_id,
                'attachable_type' => (string) $row->attachable_type,
                'attachable_id' => (int) $row->attachable_id,
                'role' => (string) $row->role,
                'position' => (int) $row->position,
            ]);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $attachments
     * @param  Collection<int, Media>  $mediaById
     * @return array<int, array<string, mixed>>
     */
    private function attachmentIssues(Collection $attachments, Collection $mediaById): array
    {
        $ownerRows = [
            'content_group' => $this->ownerRows($attachments, 'content_group', 'content_groups'),
            'content_item' => $this->ownerRows($attachments, 'content_item', 'content_items'),
        ];
        $duplicates = $attachments
            ->groupBy(fn (array $row): string => implode(':', [
                $row['attachable_type'],
                $row['attachable_id'],
                $row['role'],
            ]))
            ->filter(fn (Collection $rows): bool => $rows->count() > 1)
            ->keys()
            ->flip();

        return $attachments
            ->map(function (array $attachment) use ($duplicates, $mediaById, $ownerRows): ?array {
                $issues = [];
                $role = MediaAttachmentRole::tryFrom($attachment['role']);
                $media = $mediaById->get($attachment['media_id']);
                $ownerExists = isset($ownerRows[$attachment['attachable_type']])
                    && array_key_exists($attachment['attachable_id'], $ownerRows[$attachment['attachable_type']]);

                if (! in_array($attachment['attachable_type'], ['content_group', 'content_item'], true)) {
                    $issues[] = 'invalid_attachable_type';
                } elseif (! $ownerExists) {
                    $issues[] = 'missing_owner';
                }

                if (! $media instanceof Media) {
                    $issues[] = 'missing_media';
                }

                if (! $role instanceof MediaAttachmentRole || $role->ownerAlias() !== $attachment['attachable_type']) {
                    $issues[] = 'invalid_role';
                } elseif ($media instanceof Media && ! $this->scope->allows($media, $role->purpose())) {
                    $issues[] = 'incompatible_media';
                }

                if ($attachment['position'] !== 0) {
                    $issues[] = 'invalid_singleton_position';
                }

                $duplicateKey = implode(':', [
                    $attachment['attachable_type'],
                    $attachment['attachable_id'],
                    $attachment['role'],
                ]);
                if ($duplicates->has($duplicateKey)) {
                    $issues[] = 'duplicate_owner_role';
                }

                if ($issues === []) {
                    return null;
                }

                return [
                    'attachment_id' => $attachment['id'],
                    'media_id' => $attachment['media_id'],
                    'attachable_type' => $attachment['attachable_type'],
                    'attachable_id' => $attachment['attachable_id'],
                    'role' => $attachment['role'],
                    'issues' => array_values(array_unique($issues)),
                    'recommended_disposition' => 'review_and_repair_attachment',
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $attachments
     * @return array<int, bool>
     */
    private function ownerRows(
        Collection $attachments,
        string $alias,
        string $table,
    ): array {
        if (! Schema::hasTable($table)) {
            return [];
        }

        $ids = $attachments
            ->where('attachable_type', $alias)
            ->pluck('attachable_id')
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return [];
        }

        return $ids
            ->chunk(500)
            ->flatMap(fn (Collection $chunk): Collection => DB::table($table)
                ->whereIn('id', $chunk->all())
                ->pluck('id'))
            ->mapWithKeys(fn (mixed $id): array => [(int) $id => true])
            ->all();
    }

    /**
     * @param  Collection<int, Media>  $media
     * @return array<int, array<string, mixed>>
     */
    private function duplicateLocations(Collection $media): array
    {
        return $media
            ->groupBy(fn (Media $record): string => $this->locationKey((string) $record->disk, (string) $record->path))
            ->filter(fn (Collection $records): bool => $records->count() > 1)
            ->map(fn (Collection $records): array => [
                'disk' => (string) $records->first()->disk,
                'path' => (string) $records->first()->path,
                'count' => $records->count(),
                'media' => $records->map(fn (Media $record): array => [
                    'media_id' => (int) $record->getKey(),
                    'reference_key' => $record->reference_key,
                ])->values()->all(),
            ])
            ->sortBy(fn (array $row): string => $this->locationKey($row['disk'], $row['path']))
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Media>  $media
     * @return array<int, array<string, mixed>>
     */
    private function settingsIdentityIssues(Collection $media): array
    {
        if (! Schema::hasTable('settings')) {
            return [];
        }

        $properties = DB::table('settings')
            ->where('group', PublicContentSettings::group())
            ->whereIn('name', ['menu_config', 'about_page', 'default_images'])
            ->pluck('payload', 'name');
        $payload = [];
        $payloadIssues = [];

        foreach ($properties as $name => $value) {
            $decoded = $this->decodePayload($value);
            $payload[(string) $name] = $decoded;

            if (! is_array($value) && is_string($value) && filled($value) && json_last_error() !== JSON_ERROR_NONE) {
                $payloadIssues[] = [
                    'setting' => (string) $name,
                    'location' => '$',
                    'purpose' => null,
                    'reference_key' => null,
                    'legacy_path' => null,
                    'issues' => ['malformed_settings_json'],
                    'recommended_disposition' => 'review_settings_identity',
                ];
            }
        }

        return collect($payloadIssues)
            ->concat(collect($this->settingsIdentityPairs($payload))
                ->map(fn (array $pair): ?array => $this->settingsIdentityIssue($pair, $media))
                ->filter())
            ->values()
            ->all();
    }

    /**
     * @param  array<string, array<string, mixed>>  $payload
     * @return array<int, array<string, mixed>>
     */
    private function settingsIdentityPairs(array $payload): array
    {
        $pairs = [];
        $logo = data_get($payload, 'menu_config.logo');

        if (is_array($logo)) {
            $pairs[] = $this->identityPair('menu_config', 'logo.light', ImageUploadPurpose::HeaderLogo, $logo['light_media_reference_key'] ?? null, $logo['light_path'] ?? null);
            $pairs[] = $this->identityPair('menu_config', 'logo.dark', ImageUploadPurpose::HeaderLogo, $logo['dark_media_reference_key'] ?? null, $logo['dark_path'] ?? null);
        }

        foreach (PublicFrontConfigRegistry::defaultImageFamilies() as $family) {
            $config = data_get($payload, "default_images.{$family}");

            if (is_array($config)) {
                $pairs[] = $this->identityPair('default_images', $family, ImageUploadPurpose::DefaultImage, $config['media_reference_key'] ?? null, $config['path'] ?? null);
            }
        }

        foreach ((array) data_get($payload, 'about_page.blocks', []) as $index => $block) {
            if (! is_array($block) || ($block['type'] ?? data_get($block, 'data.type')) !== 'image') {
                continue;
            }

            $data = is_array($block['data'] ?? null) ? $block['data'] : $block;
            $pair = $this->identityPair('about_page', "blocks.{$index}", ImageUploadPurpose::AboutImage, $data['image_media_reference_key'] ?? null, $data['image_path'] ?? null);

            if (is_array($block['data'] ?? null) && (array_key_exists('image_media_reference_key', $block) || array_key_exists('image_path', $block))) {
                $pair['structural_issue'] = 'ambiguous_builder_identity';
            }

            $pairs[] = $pair;
        }

        foreach ((array) data_get($payload, 'about_page.team_profiles', []) as $index => $profile) {
            if (is_array($profile)) {
                $pairs[] = $this->identityPair('about_page', "team_profiles.{$index}", ImageUploadPurpose::TeamImage, $profile['image_media_reference_key'] ?? null, $profile['image_path'] ?? null);
            }
        }

        return $pairs;
    }

    /** @return array<string, mixed> */
    private function identityPair(
        string $setting,
        string $location,
        ImageUploadPurpose $purpose,
        mixed $referenceKey,
        mixed $legacyPath,
    ): array {
        return compact('setting', 'location', 'purpose', 'referenceKey', 'legacyPath');
    }

    /**
     * @param  array<string, mixed>  $pair
     * @param  Collection<int, Media>  $media
     * @return array<string, mixed>|null
     */
    private function settingsIdentityIssue(array $pair, Collection $media): ?array
    {
        $issues = array_filter([$pair['structural_issue'] ?? null]);
        $rawReferenceKey = $pair['referenceKey'];
        $rawLegacyPath = $pair['legacyPath'];
        $referenceKey = is_string($rawReferenceKey) && filled($rawReferenceKey)
            ? mb_strtoupper(trim($rawReferenceKey))
            : null;
        $legacyPath = is_string($rawLegacyPath) && filled($rawLegacyPath)
            ? trim($rawLegacyPath)
            : null;

        if ($rawReferenceKey !== null && ! is_string($rawReferenceKey)) {
            $issues[] = 'invalid_reference_key_type';
        }

        if ($rawLegacyPath !== null && ! is_string($rawLegacyPath)) {
            $issues[] = 'invalid_legacy_path_type';
        }

        $referenceMatches = collect();
        if ($referenceKey !== null) {
            if (! preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/', $referenceKey)) {
                $issues[] = 'invalid_reference_key';
            } else {
                $referenceMatches = $media->filter(
                    fn (Media $record): bool => mb_strtoupper((string) $record->reference_key) === $referenceKey,
                );
                $issues = array_merge($issues, match ($referenceMatches->count()) {
                    0 => ['unresolved_reference_key'],
                    1 => $this->scope->allows($referenceMatches->first(), $pair['purpose']) ? [] : ['disallowed_reference_media'],
                    default => ['duplicate_reference_key'],
                });
            }
        }

        $pathMatches = collect();
        if ($legacyPath !== null) {
            try {
                $legacyPath = $this->policy->normalizePath($legacyPath);

                if ($this->policy->purposeForPath($legacyPath) !== $pair['purpose']) {
                    throw new InvalidArgumentException('The purpose is incompatible.');
                }

                $pathMatches = $media->where('path', $legacyPath);
                $issues = array_merge($issues, match ($pathMatches->count()) {
                    0 => ['unregistered_legacy_path'],
                    1 => $this->scope->allows($pathMatches->first(), $pair['purpose']) ? [] : ['disallowed_legacy_media'],
                    default => ['duplicate_legacy_path'],
                });
            } catch (InvalidArgumentException) {
                $issues[] = 'invalid_legacy_path';
            }
        }

        if ($referenceMatches->count() === 1 && $pathMatches->count() === 1 && ! $referenceMatches->first()->is($pathMatches->first())) {
            $issues[] = 'reference_path_mismatch';
        }

        if ($referenceKey === null && $pathMatches->count() === 1 && $this->scope->allows($pathMatches->first(), $pair['purpose'])) {
            $issues[] = blank($pathMatches->first()->reference_key)
                ? 'media_missing_reference_key'
                : 'settings_reference_key_missing';
        }

        if ($referenceMatches->count() === 1 && $legacyPath === null && $this->scope->allows($referenceMatches->first(), $pair['purpose'])) {
            $issues[] = 'settings_legacy_path_missing';
        }

        $issues = array_values(array_unique($issues));

        if ($issues === []) {
            return null;
        }

        return [
            'setting' => $pair['setting'],
            'location' => $pair['location'],
            'purpose' => $pair['purpose']->value,
            'reference_key' => $referenceKey,
            'legacy_path' => $legacyPath,
            'issues' => $issues,
            'recommended_disposition' => count($issues) === 1 && $issues[0] === 'settings_reference_key_missing'
                ? 'backfill_reference_key'
                : 'review_settings_identity',
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function incompleteMutations(): array
    {
        if (! Schema::hasTable('media_mutation_operations')) {
            return [];
        }

        return DB::table('media_mutation_operations')
            ->where('status', '!=', MediaMutationStatus::Completed->value)
            ->orderBy('id')
            ->get([
                'id',
                'operation_key',
                'media_id',
                'media_id_snapshot',
                'media_reference_key',
                'operation',
                'status',
                'source_disk',
                'source_path',
                'destination_disk',
                'destination_path',
                'quarantine_disk',
                'quarantine_path',
                'attempts',
                'lease_expires_at',
                'last_error',
            ])
            ->map(function (object $row): array {
                $result = (array) $row;
                $validOperations = array_column(MediaMutationOperationType::cases(), 'value');
                $validStatuses = array_column(MediaMutationStatus::cases(), 'value');
                $issues = [];

                if (! in_array($row->operation, $validOperations, true) || ! in_array($row->status, $validStatuses, true)) {
                    $issues[] = 'manual_review_invalid_journal';
                }

                if (filled($row->lease_expires_at)) {
                    try {
                        if (now()->lt($row->lease_expires_at)) {
                            $issues[] = 'active_lease_do_not_repair';
                        }
                    } catch (Throwable) {
                        $issues[] = 'manual_review_invalid_journal';
                    }
                }

                if ($issues === []) {
                    $issues[] = $row->status === MediaMutationStatus::Failed->value
                        ? 'review_failed_operation'
                        : 'repair_incomplete_operation';
                }

                $result['issues'] = array_values(array_unique($issues));

                return $result;
            })
            ->all();
    }

    /** @return array<string, mixed> */
    private function decodePayload(mixed $payload): array
    {
        if (is_array($payload)) {
            return $payload;
        }

        if (! is_string($payload) || blank($payload)) {
            return [];
        }

        $decoded = json_decode($payload, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function checksum(string $path): ?string
    {
        $stream = Storage::disk('public')->readStream($path);

        if (! is_resource($stream)) {
            return null;
        }

        try {
            $hash = hash_init('sha256');
            hash_update_stream($hash, $stream);

            return hash_final($hash);
        } finally {
            fclose($stream);
        }
    }

    private function locationKey(string $disk, string $path): string
    {
        return $disk."\0".$path;
    }

    private function isSafePublicPath(string $path): bool
    {
        try {
            return $this->policy->normalizePath($path) === $path;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    /** @return array{state: string, sha256: string|null} */
    private function filesystemEvidence(Media $media): array
    {
        if ($media->disk !== 'public') {
            return ['state' => 'wrong_disk', 'sha256' => null];
        }

        if (! $this->isSafePublicPath((string) $media->path)) {
            return ['state' => 'unsafe_path', 'sha256' => null];
        }

        try {
            if (! Storage::disk('public')->exists((string) $media->path)) {
                return ['state' => 'missing', 'sha256' => null];
            }

            return [
                'state' => 'present',
                'sha256' => $this->checksum((string) $media->path),
            ];
        } catch (Throwable) {
            return ['state' => 'read_error', 'sha256' => null];
        }
    }
}
