<?php

namespace App\Support\Media;

use App\Enums\MediaAttachmentRole;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\Media;
use App\Models\MediaAsset;
use App\Models\MediaAttachment;
use App\Models\MediaProviderBinding;
use App\Settings\PublicContentSettings;
use App\Support\SettingsLifecycle\PublicContentSettingsWriteCoordinator;
use App\Support\SettingsLifecycle\SettingsMediaIdentityProjector;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\LaravelSettings\Models\SettingsProperty;
use Throwable;

class CuratorMediaAssetConverter
{
    public function __construct(
        private SettingsMediaIdentityProjector $settingsProjector,
        private PublicContentSettingsWriteCoordinator $writeCoordinator,
    ) {}

    public function convert(): CuratorMediaAssetConversionReport
    {
        return $this->writeCoordinator->transaction(function (): CuratorMediaAssetConversionReport {
            $counts = $this->emptyCounts();
            $details = $this->emptyDetails();
            $mediaRows = Media::query()
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $counts['media_rows'] = $mediaRows->count();
            $counts['bound_media'] = MediaProviderBinding::query()
                ->where('provider', 'curator')
                ->whereIn('provider_record_key', $mediaRows->modelKeys())
                ->count();
            $counts['unbound_media'] = $counts['media_rows'] - $counts['bound_media'];
            $mediaByPath = $mediaRows
                ->filter(fn (Media $media): bool => is_string($media->path) && filled($media->path))
                ->groupBy(fn (Media $media): string => $media->path);
            $normalizedKeys = $mediaRows->mapWithKeys(
                fn (Media $media): array => [$media->getKey() => $this->validReferenceKey($media->reference_key)],
            );
            $keyCounts = $normalizedKeys->filter()->countBy();
            $assetsByMediaId = [];

            foreach ($mediaByPath as $path => $matches) {
                if ($matches->count() < 2) {
                    continue;
                }

                $counts['duplicate_paths']++;
                $details['duplicate_paths'][] = [
                    'path' => $path,
                    'media_ids' => $matches->modelKeys(),
                ];
            }

            foreach ($mediaRows as $media) {
                [$asset, $createdAsset, $createdBinding, $keyWasGenerated] = $this->assetFor(
                    $media,
                    $normalizedKeys->get($media->getKey()),
                    $keyCounts,
                );
                $assetsByMediaId[(int) $media->getKey()] = $asset;
                $counts['assets_created'] += (int) $createdAsset;
                $counts['bindings_created'] += (int) $createdBinding;

                if ($createdBinding) {
                    $counts[$keyWasGenerated ? 'keys_generated' : 'keys_reused']++;
                }

                if (! $this->fileExists($media)) {
                    $counts['missing_files']++;
                    $details['missing_files'][] = [
                        'media_id' => (int) $media->getKey(),
                        'disk' => $media->disk,
                        'path' => $media->path,
                    ];
                }
            }

            $existingOwnerKeys = $this->bridgeExistingAttachments(
                $assetsByMediaId,
                $counts,
            );
            $this->bridgeOwnersByUniquePath(
                ContentGroup::query()->whereNotNull('cover_path')->lockForUpdate()->get(),
                'content_group',
                'cover_path',
                MediaAttachmentRole::Cover,
                $mediaByPath,
                $assetsByMediaId,
                $existingOwnerKeys,
                $counts,
                $details,
            );
            $this->bridgeOwnersByUniquePath(
                ContentItem::query()->whereNotNull('image_path')->lockForUpdate()->get(),
                'content_item',
                'image_path',
                MediaAttachmentRole::PrimaryImage,
                $mediaByPath,
                $assetsByMediaId,
                $existingOwnerKeys,
                $counts,
                $details,
            );

            $uniqueKeysByPath = $mediaByPath
                ->filter(fn (Collection $matches): bool => $matches->count() === 1)
                ->map(fn (Collection $matches): string => (string) $assetsByMediaId[
                    (int) $matches->firstOrFail()->getKey()
                ]->reference_key)
                ->all();
            $knownReferenceKeys = collect($assetsByMediaId)
                ->map(fn (MediaAsset $asset): string => $asset->reference_key)
                ->values()
                ->all();
            $this->reconcileSettings(
                $uniqueKeysByPath,
                $knownReferenceKeys,
                $counts,
                $details,
            );

            return new CuratorMediaAssetConversionReport($counts, $details);
        });
    }

    public function inspect(): CuratorMediaAssetConversionReport
    {
        $counts = $this->emptyCounts();
        $details = $this->emptyDetails();
        $mediaRows = Media::query()->orderBy('id')->get();
        $mediaByPath = $mediaRows
            ->filter(fn (Media $media): bool => is_string($media->path) && filled($media->path))
            ->groupBy(fn (Media $media): string => $media->path);
        $boundMediaIds = MediaProviderBinding::query()
            ->where('provider', 'curator')
            ->whereIn('provider_record_key', $mediaRows->modelKeys())
            ->pluck('provider_record_key')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
        $counts['media_rows'] = $mediaRows->count();
        $counts['bound_media'] = count($boundMediaIds);
        $counts['unbound_media'] = $counts['media_rows'] - $counts['bound_media'];
        $counts['attachments_bridged'] = MediaAttachment::query()
            ->whereNull('media_asset_id')
            ->count();

        foreach ($mediaByPath as $path => $matches) {
            if ($matches->count() < 2) {
                continue;
            }

            $counts['duplicate_paths']++;
            $details['duplicate_paths'][] = [
                'path' => $path,
                'media_ids' => $matches->modelKeys(),
            ];
        }

        foreach ($mediaRows as $media) {
            if ($this->fileExists($media)) {
                continue;
            }

            $counts['missing_files']++;
            $details['missing_files'][] = [
                'media_id' => (int) $media->getKey(),
                'disk' => $media->disk,
                'path' => $media->path,
            ];
        }

        $this->inspectUnattachedOwners(
            ContentGroup::query()->whereNotNull('cover_path')->get(),
            'content_group',
            'cover_path',
            MediaAttachmentRole::Cover,
            $mediaByPath,
            $counts,
            $details,
        );
        $this->inspectUnattachedOwners(
            ContentItem::query()->whereNotNull('image_path')->get(),
            'content_item',
            'image_path',
            MediaAttachmentRole::PrimaryImage,
            $mediaByPath,
            $counts,
            $details,
        );

        return new CuratorMediaAssetConversionReport($counts, $details);
    }

    /**
     * @param  array<string, int>  $keyCounts
     * @return array{MediaAsset, bool, bool, bool}
     */
    private function assetFor(
        Media $media,
        ?string $validReferenceKey,
        $keyCounts,
    ): array {
        $binding = MediaProviderBinding::query()
            ->where('provider', 'curator')
            ->where('provider_record_key', (string) $media->getKey())
            ->lockForUpdate()
            ->first();

        if ($binding instanceof MediaProviderBinding) {
            $asset = $binding->mediaAsset()->lockForUpdate()->firstOrFail();
            $this->mirrorReferenceKey($media, $asset->reference_key);

            return [$asset, false, false, false];
        }

        $referenceKey = $validReferenceKey;
        $generated = $referenceKey === null || $keyCounts->get($referenceKey, 0) !== 1;

        if (! $generated) {
            $existingAsset = MediaAsset::query()
                ->where('reference_key', $referenceKey)
                ->lockForUpdate()
                ->first();

            if (
                $existingAsset instanceof MediaAsset
                && $existingAsset->providerBindings()
                    ->where('provider', 'curator')
                    ->exists()
            ) {
                $generated = true;
            }
        }

        if ($generated) {
            $referenceKey = $this->newReferenceKey();
        }

        $asset = MediaAsset::query()->firstOrCreate([
            'reference_key' => $referenceKey,
        ]);
        $createdAsset = $asset->wasRecentlyCreated;

        MediaProviderBinding::query()->create([
            'media_asset_id' => $asset->getKey(),
            'provider' => 'curator',
            'provider_record_key' => (string) $media->getKey(),
        ]);
        $this->mirrorReferenceKey($media, $asset->reference_key);

        return [$asset, $createdAsset, true, $generated];
    }

    private function validReferenceKey(mixed $referenceKey): ?string
    {
        if (! is_string($referenceKey)) {
            return null;
        }

        return Str::isUlid($referenceKey) ? $referenceKey : null;
    }

    private function newReferenceKey(): string
    {
        do {
            $referenceKey = (string) Str::ulid();
        } while (
            MediaAsset::query()->where('reference_key', $referenceKey)->exists()
            || Media::query()->where('reference_key', $referenceKey)->exists()
        );

        return $referenceKey;
    }

    private function mirrorReferenceKey(Media $media, string $referenceKey): void
    {
        if ($media->reference_key === $referenceKey) {
            return;
        }

        DB::table($media->getTable())
            ->where('id', $media->getKey())
            ->update(['reference_key' => $referenceKey]);

        $media->setAttribute('reference_key', $referenceKey);
        $media->syncOriginalAttribute('reference_key');
    }

    private function fileExists(Media $media): bool
    {
        if (! is_string($media->disk) || blank($media->disk)) {
            return false;
        }

        if (! is_string($media->path) || blank($media->path)) {
            return false;
        }

        try {
            return Storage::disk($media->disk)->exists($media->path);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @param  array<int, MediaAsset>  $assetsByMediaId
     * @param  array<string, int>  $counts
     * @return array<string, true>
     */
    private function bridgeExistingAttachments(
        array $assetsByMediaId,
        array &$counts,
    ): array {
        $ownerKeys = [];
        $attachments = MediaAttachment::query()
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($attachments as $attachment) {
            $asset = $assetsByMediaId[(int) $attachment->media_id];
            $ownerKey = $this->ownerKey(
                $attachment->attachable_type,
                (int) $attachment->attachable_id,
                $attachment->getRawOriginal('role'),
            );
            $ownerKeys[$ownerKey] = true;

            if ((int) $attachment->media_asset_id !== (int) $asset->getKey()) {
                $attachment->forceFill(['media_asset_id' => $asset->getKey()])->save();
                $counts['attachments_bridged']++;
            }

            $media = Media::query()->findOrFail($attachment->media_id);

            if ($this->repairOwnerPath($attachment, $media)) {
                $counts['owner_paths_repaired']++;
            }
        }

        return $ownerKeys;
    }

    private function repairOwnerPath(MediaAttachment $attachment, Media $media): bool
    {
        [$ownerClass, $legacyColumn] = match ([
            $attachment->attachable_type,
            $attachment->getRawOriginal('role'),
        ]) {
            ['content_group', MediaAttachmentRole::Cover->value] => [ContentGroup::class, 'cover_path'],
            ['content_item', MediaAttachmentRole::PrimaryImage->value] => [ContentItem::class, 'image_path'],
            default => [null, null],
        };

        if ($ownerClass === null || $legacyColumn === null) {
            return false;
        }

        $owner = $ownerClass::query()->lockForUpdate()->find($attachment->attachable_id);

        if (! $owner instanceof Model || $owner->getAttribute($legacyColumn) === $media->path) {
            return false;
        }

        $owner->forceFill([$legacyColumn => $media->path])->saveQuietly();

        return true;
    }

    /**
     * @param  Collection<int, Model>  $owners
     * @param  \Illuminate\Support\Collection<string, Collection<int, Media>>  $mediaByPath
     * @param  array<int, MediaAsset>  $assetsByMediaId
     * @param  array<string, true>  $existingOwnerKeys
     * @param  array<string, int>  $counts
     * @param  array<string, array<int, mixed>>  $details
     */
    private function bridgeOwnersByUniquePath(
        Collection $owners,
        string $attachableType,
        string $legacyColumn,
        MediaAttachmentRole $role,
        $mediaByPath,
        array $assetsByMediaId,
        array &$existingOwnerKeys,
        array &$counts,
        array &$details,
    ): void {
        foreach ($owners as $owner) {
            $ownerKey = $this->ownerKey(
                $attachableType,
                (int) $owner->getKey(),
                $role->value,
            );

            if (isset($existingOwnerKeys[$ownerKey])) {
                continue;
            }

            $path = $owner->getAttribute($legacyColumn);

            if (! is_string($path) || blank($path)) {
                continue;
            }

            $matches = $mediaByPath->get($path);

            if (! $matches instanceof Collection || $matches->count() !== 1) {
                $counts['unresolved_owners']++;
                $details['unresolved_owners'][] = [
                    'attachable_type' => $attachableType,
                    'attachable_id' => (int) $owner->getKey(),
                    'role' => $role->value,
                    'path' => $path,
                ];

                continue;
            }

            $media = $matches->firstOrFail();
            $asset = $assetsByMediaId[(int) $media->getKey()];
            MediaAttachment::query()->create([
                'media_id' => $media->getKey(),
                'media_asset_id' => $asset->getKey(),
                'attachable_type' => $attachableType,
                'attachable_id' => $owner->getKey(),
                'role' => $role,
                'position' => 0,
            ]);
            $existingOwnerKeys[$ownerKey] = true;
            $counts['attachments_created']++;
        }
    }

    private function ownerKey(string $type, int $id, mixed $role): string
    {
        $role = $role instanceof MediaAttachmentRole ? $role->value : (string) $role;

        return "{$type}:{$id}:{$role}";
    }

    /**
     * @param  Collection<int, Model>  $owners
     * @param  \Illuminate\Support\Collection<string, Collection<int, Media>>  $mediaByPath
     * @param  array<string, int>  $counts
     * @param  array<string, array<int, mixed>>  $details
     */
    private function inspectUnattachedOwners(
        Collection $owners,
        string $attachableType,
        string $legacyColumn,
        MediaAttachmentRole $role,
        $mediaByPath,
        array &$counts,
        array &$details,
    ): void {
        $attachedOwnerIds = MediaAttachment::query()
            ->where('attachable_type', $attachableType)
            ->where('role', $role->value)
            ->pluck('attachable_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        foreach ($owners as $owner) {
            if (in_array((int) $owner->getKey(), $attachedOwnerIds, true)) {
                continue;
            }

            $path = $owner->getAttribute($legacyColumn);
            $matches = is_string($path) ? $mediaByPath->get($path) : null;

            if ($matches instanceof Collection && $matches->count() === 1) {
                continue;
            }

            $counts['unresolved_owners']++;
            $details['unresolved_owners'][] = [
                'attachable_type' => $attachableType,
                'attachable_id' => (int) $owner->getKey(),
                'role' => $role->value,
                'path' => $path,
            ];
        }
    }

    /**
     * @param  array<string, string>  $referenceKeysByPath
     * @param  array<int, string>  $knownReferenceKeys
     * @param  array<string, int>  $counts
     * @param  array<string, array<int, mixed>>  $details
     */
    private function reconcileSettings(
        array $referenceKeysByPath,
        array $knownReferenceKeys,
        array &$counts,
        array &$details,
    ): void {
        $names = ['menu_config', 'about_page', 'default_images'];
        $properties = SettingsProperty::query()
            ->where('group', PublicContentSettings::group())
            ->whereIn('name', $names)
            ->lockForUpdate()
            ->get()
            ->keyBy('name');
        $payload = $properties
            ->mapWithKeys(fn (SettingsProperty $property): array => [
                $property->name => json_decode(
                    (string) $property->payload,
                    true,
                    flags: JSON_THROW_ON_ERROR,
                ),
            ])
            ->all();
        $result = $this->settingsProjector->backfillFromUniquePaths(
            $payload,
            $referenceKeysByPath,
            $knownReferenceKeys,
        );

        foreach ($properties as $name => $property) {
            if (($payload[$name] ?? null) === ($result['payload'][$name] ?? null)) {
                continue;
            }

            $property->forceFill([
                'payload' => json_encode(
                    $result['payload'][$name],
                    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
                ),
            ])->save();
        }

        $counts['settings_keys_filled'] += count($result['filled']);
        $counts['unresolved_settings'] += count($result['unresolved']);
        $details['settings_keys_filled'] = [
            ...$details['settings_keys_filled'],
            ...$result['filled'],
        ];
        $details['unresolved_settings'] = [
            ...$details['unresolved_settings'],
            ...$result['unresolved'],
        ];
    }

    /**
     * @return array<string, int>
     */
    private function emptyCounts(): array
    {
        return [
            'media_rows' => 0,
            'bound_media' => 0,
            'unbound_media' => 0,
            'assets_created' => 0,
            'bindings_created' => 0,
            'keys_reused' => 0,
            'keys_generated' => 0,
            'attachments_bridged' => 0,
            'attachments_created' => 0,
            'owner_paths_repaired' => 0,
            'settings_keys_filled' => 0,
            'missing_files' => 0,
            'duplicate_paths' => 0,
            'unresolved_owners' => 0,
            'unresolved_settings' => 0,
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function emptyDetails(): array
    {
        return [
            'missing_files' => [],
            'duplicate_paths' => [],
            'unresolved_owners' => [],
            'settings_keys_filled' => [],
            'unresolved_settings' => [],
        ];
    }
}
