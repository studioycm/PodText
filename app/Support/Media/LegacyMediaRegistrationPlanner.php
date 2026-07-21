<?php

namespace App\Support\Media;

use App\Enums\ImageUploadPurpose;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\Media;
use App\Settings\PublicContentSettings;
use App\Support\SettingsLifecycle\SettingsMediaIdentityProjector;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class LegacyMediaRegistrationPlanner
{
    public function __construct(
        private readonly CuratorImageUploadPolicy $policy,
        private readonly ImageUploadValidator $validator,
        private readonly SettingsMediaIdentityProjector $settingsProjector,
    ) {}

    public function plan(string $sourcePath, ?int $expectedExistingMediaId = null): LegacyMediaRegistrationPlan
    {
        $normalized = $this->policy->normalizePath($sourcePath);

        if ($normalized !== $sourcePath) {
            throw new InvalidArgumentException('The legacy media path is not already normalized.');
        }

        $existing = Media::query()->where('disk', 'public')->where('path', $sourcePath)->orderBy('id')->get();

        if ($expectedExistingMediaId === null && $existing->isNotEmpty()) {
            throw new InvalidArgumentException('The legacy path already has a Curator media record.');
        }

        if ($expectedExistingMediaId !== null && ($existing->count() !== 1 || (int) $existing->sole()->getKey() !== $expectedExistingMediaId)) {
            throw new InvalidArgumentException('The legacy path must have exactly one Curator media record for an in-place transition.');
        }

        try {
            $this->assertCanonicalPublicSource($sourcePath);

            if (! Storage::disk('public')->exists($sourcePath)) {
                throw new InvalidArgumentException('The legacy media source is missing.');
            }

            if (Storage::disk('public')->visibility($sourcePath) !== 'public') {
                throw new InvalidArgumentException('The legacy media source is not public.');
            }

            $sourceContents = $this->readBounded($sourcePath);
        } catch (InvalidArgumentException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new InvalidArgumentException('The legacy media source could not be inspected.', previous: $exception);
        }

        $purpose = $this->policy->purposeForPath($sourcePath);
        $validated = $this->validator->validateBytes($sourceContents, basename($sourcePath), $purpose);
        $contentGroupIds = ContentGroup::query()
            ->where('cover_path', $sourcePath)
            ->orderBy('id')
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
        $contentItemIds = Schema::hasColumn('content_items', 'image_path')
            ? ContentItem::query()
                ->where('image_path', $sourcePath)
                ->orderBy('id')
                ->pluck('id')
                ->map(fn (mixed $id): int => (int) $id)
                ->all()
            : [];

        if ($contentGroupIds !== [] && $purpose !== ImageUploadPurpose::ContentGroupCover) {
            throw new InvalidArgumentException('A content-group cover references a path outside its purpose root.');
        }

        if ($contentItemIds !== [] && $purpose !== ImageUploadPurpose::ContentItemPrimaryImage) {
            throw new InvalidArgumentException('A content-item image references a path outside its purpose root.');
        }

        $settingsSnapshots = $this->settingsSnapshots($sourcePath, $purpose);
        $plan = new LegacyMediaRegistrationPlan(
            sourcePath: $sourcePath,
            purpose: $purpose,
            sourceContents: $sourceContents,
            sourceSha256: hash('sha256', $sourceContents),
            validatedImage: $validated,
            contentGroupIds: $contentGroupIds,
            contentItemIds: $contentItemIds,
            settingsSnapshots: $settingsSnapshots,
            existingMediaId: $expectedExistingMediaId,
        );

        if ($expectedExistingMediaId === null && $plan->referenceCount() < 1) {
            throw new InvalidArgumentException('The legacy media source has no app-owned owner or settings reference.');
        }

        return $plan;
    }

    /**
     * @return array<string, array{sha256: string, locations: array<int, string>, locked: bool}>
     */
    private function settingsSnapshots(string $sourcePath, ImageUploadPurpose $purpose): array
    {
        if (! Schema::hasTable('settings')) {
            return [];
        }

        $rows = DB::table('settings')
            ->where('group', PublicContentSettings::group())
            ->whereIn('name', ['menu_config', 'about_page', 'default_images'])
            ->orderBy('name')
            ->get(['name', 'payload', 'locked']);
        $payload = $rows->mapWithKeys(fn (object $row): array => [
            (string) $row->name => $this->decodePayload($row->payload, (string) $row->name),
        ])->all();
        $locations = $this->settingsProjector->legacyRegistrationLocations($payload, $sourcePath, $purpose);

        return $rows
            ->mapWithKeys(function (object $row) use ($locations): array {
                $name = (string) $row->name;
                $propertyLocations = collect($locations)
                    ->filter(fn (string $location): bool => str_starts_with($location, $name.'.'))
                    ->sort()
                    ->values()
                    ->all();

                if ($propertyLocations === []) {
                    return [];
                }

                if ((bool) $row->locked) {
                    throw new RuntimeException("The referenced public settings property [{$name}] is locked.");
                }

                return [$name => [
                    'sha256' => hash('sha256', (string) $row->payload),
                    'locations' => $propertyLocations,
                    'locked' => false,
                ]];
            })
            ->all();
    }

    /** @return array<string, mixed> */
    private function decodePayload(mixed $payload, string $name): array
    {
        if (! is_string($payload)) {
            throw new InvalidArgumentException("The public settings property [{$name}] is not encoded JSON.");
        }

        try {
            $decoded = json_decode($payload, true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable $exception) {
            throw new InvalidArgumentException("The public settings property [{$name}] contains malformed JSON.", previous: $exception);
        }

        if (! is_array($decoded)) {
            throw new InvalidArgumentException("The public settings property [{$name}] must contain an object or array.");
        }

        return $decoded;
    }

    private function readBounded(string $path): string
    {
        $stream = Storage::disk('public')->readStream($path);

        if (! is_resource($stream)) {
            throw new InvalidArgumentException('The legacy media source could not be read.');
        }

        try {
            $maximum = CuratorImageUploadPolicy::MAX_KILOBYTES * 1024;
            $contents = stream_get_contents($stream, $maximum + 1);

            if (! is_string($contents) || $contents === '' || strlen($contents) > $maximum) {
                throw new InvalidArgumentException('The legacy media source is empty or exceeds the size limit.');
            }

            return $contents;
        } finally {
            fclose($stream);
        }
    }

    /**
     * A registration candidate is an exact path on the local public disk, not
     * merely a Flysystem-readable stream.  In particular, do not follow a
     * symlink out of the fixed public root: a successful image decode is not
     * proof that the bytes are an app-owned public asset.
     */
    private function assertCanonicalPublicSource(string $path): void
    {
        try {
            $disk = Storage::disk('public');
            $root = realpath($disk->path(''));
            $candidate = $disk->path($path);
        } catch (Throwable $exception) {
            throw new InvalidArgumentException('The legacy media source cannot be proven to be on the canonical public root.', previous: $exception);
        }

        if (! is_string($root) || $root === '' || ! is_string($candidate) || $candidate === '') {
            throw new InvalidArgumentException('The legacy media source cannot be proven to be on the canonical public root.');
        }

        if (is_link($candidate)) {
            throw new InvalidArgumentException('The legacy media source may not be a symlink.');
        }

        $resolved = realpath($candidate);
        $rootPrefix = rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;

        if (! is_string($resolved) || ! str_starts_with($resolved, $rootPrefix)) {
            throw new InvalidArgumentException('The legacy media source is outside the canonical public root.');
        }
    }
}
