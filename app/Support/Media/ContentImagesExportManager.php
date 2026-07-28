<?php

namespace App\Support\Media;

use App\Enums\ImageUploadPurpose;
use App\Enums\MediaAttachmentRole;
use App\Enums\MediaNamingStrategy;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\Media;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use JsonException;
use RuntimeException;
use Throwable;
use ZipArchive;

class ContentImagesExportManager
{
    private const ROOT = 'content-images-exports';

    public function __construct(
        private readonly MediaAttachmentIdentityResolver $attachmentIdentityResolver,
        private readonly MediaRecordScope $mediaRecordScope,
        private readonly ImageUploadValidator $validator,
    ) {}

    /**
     * @return array{token: string, path: string, included: int, skipped: array<int, string>}
     */
    public function build(int $userId, ?int $contentGroupId, MediaNamingStrategy $strategy): array
    {
        $this->pruneForUser($userId);

        $token = (string) Str::ulid();
        $path = $this->pathFor($userId, $token);
        $temporaryPath = tempnam(sys_get_temp_dir(), 'podtext-content-images-');

        if ($temporaryPath === false) {
            throw new RuntimeException('Unable to create a temporary content images zip.');
        }

        $included = 0;
        $skipped = [];
        $manifest = [];
        $zip = new ZipArchive;

        try {
            if ($zip->open($temporaryPath, ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Unable to open the temporary content images zip.');
            }

            $groups = ContentGroup::query()
                ->with([
                    'coverMediaAttachment.media',
                    'contentItems' => fn ($query): mixed => $query
                        ->with('primaryImageMediaAttachment.media')
                        ->orderBy('published_at')
                        ->orderBy('id'),
                ])
                ->when($contentGroupId, fn ($query): mixed => $query->whereKey($contentGroupId))
                ->orderBy('title')
                ->orderBy('id')
                ->lazy(100);

            foreach ($groups as $group) {
                $podcastStem = ImageFileNamer::storageStem($group->slug, $group->reference_key, $strategy);
                $usedEpisodeEntries = [];
                $coverMedia = $group->coverMediaAttachment === null
                    ? null
                    : $this->attachmentIdentityResolver->resolve($group, MediaAttachmentRole::Cover)['media'];

                if ($coverMedia instanceof Media) {
                    $this->addMedia(
                        $zip,
                        $coverMedia,
                        ImageUploadPurpose::ContentGroupCover,
                        (string) $group->reference_key,
                        MediaAttachmentRole::Cover,
                        "podcasts/{$podcastStem}/cover.{$coverMedia->ext}",
                        $included,
                        $skipped,
                        $manifest,
                    );
                } elseif (filled($group->cover_path)) {
                    $skipped[] = "content_group:{$group->reference_key}:cover:legacy-only";
                }

                foreach ($group->contentItems as $item) {
                    if (! $item instanceof ContentItem) {
                        continue;
                    }

                    $media = $item->primaryImageMediaAttachment === null
                        ? null
                        : $this->attachmentIdentityResolver->resolve($item, MediaAttachmentRole::PrimaryImage)['media'];

                    if (! $media instanceof Media) {
                        if (filled($item->image_path)) {
                            $skipped[] = "content_item:{$item->reference_key}:primary_image:legacy-only";
                        }

                        continue;
                    }

                    $episodeFileName = $this->uniqueEntryName(
                        ImageFileNamer::exportFileName(
                            $item->slug,
                            $item->reference_key,
                            (string) $media->type,
                            $strategy,
                            is_string($media->title) ? $media->title : null,
                        ),
                        $usedEpisodeEntries,
                    );

                    $this->addMedia(
                        $zip,
                        $media,
                        ImageUploadPurpose::ContentItemPrimaryImage,
                        (string) $item->reference_key,
                        MediaAttachmentRole::PrimaryImage,
                        "podcasts/{$podcastStem}/episodes/{$episodeFileName}",
                        $included,
                        $skipped,
                        $manifest,
                    );
                }
            }

            $zip->addFromString('manifest.json', $this->manifestJson($manifest));

            if (! $zip->close()) {
                throw new RuntimeException('Unable to finalize the content images zip.');
            }

            Storage::disk('local')->makeDirectory(dirname($path));
            $archive = fopen($temporaryPath, 'rb');

            if (! is_resource($archive)) {
                throw new RuntimeException('Unable to persist the content images zip.');
            }

            try {
                if (! Storage::disk('local')->writeStream($path, $archive)) {
                    throw new RuntimeException('Unable to persist the content images zip.');
                }
            } finally {
                fclose($archive);
            }
        } finally {
            if (is_file($temporaryPath)) {
                @unlink($temporaryPath);
            }
        }

        return [
            'token' => $token,
            'path' => $path,
            'included' => $included,
            'skipped' => $skipped,
        ];
    }

    /**
     * @param  array<string, true>  $used
     */
    private function uniqueEntryName(string $fileName, array &$used): string
    {
        $extension = pathinfo($fileName, PATHINFO_EXTENSION);
        $stem = pathinfo($fileName, PATHINFO_FILENAME);
        $candidate = $fileName;

        for ($suffix = 2; array_key_exists($candidate, $used); $suffix++) {
            $candidate = "{$stem}-{$suffix}.{$extension}";
        }

        $used[$candidate] = true;

        return $candidate;
    }

    public function pruneForUser(int $userId): void
    {
        Storage::disk('local')->deleteDirectory($this->userDirectory($userId));
    }

    public function pathFor(int $userId, string $token): string
    {
        $token = preg_replace('/[^A-Za-z0-9]/', '', $token) ?: 'invalid';

        return $this->userDirectory($userId)."/{$token}.zip";
    }

    public function downloadResponse(int $userId, string $token)
    {
        $path = $this->pathFor($userId, $token);

        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download(
            $path,
            "content-images-{$token}.zip",
            ['Content-Type' => 'application/zip'],
        );
    }

    private function userDirectory(int $userId): string
    {
        return self::ROOT."/user-{$userId}";
    }

    /**
     * @param  array<int, string>  $skipped
     * @param  array<int, array<string, string>>  $manifest
     */
    private function addMedia(
        ZipArchive $zip,
        Media $media,
        ImageUploadPurpose $purpose,
        string $ownerReferenceKey,
        MediaAttachmentRole $role,
        string $entryName,
        int &$included,
        array &$skipped,
        array &$manifest,
    ): void {
        $skipKey = "{$role->ownerAlias()}:{$ownerReferenceKey}:{$role->value}";

        if (
            blank($media->reference_key)
            || blank($ownerReferenceKey)
            || ! $this->mediaRecordScope->allows($media, $purpose)
            || ! Storage::disk('public')->exists((string) $media->path)
        ) {
            $skipped[] = $skipKey;

            return;
        }

        $contents = Storage::disk('public')->get((string) $media->path);

        if (! is_string($contents) || $contents === '') {
            $skipped[] = $skipKey;

            return;
        }

        try {
            $validated = $this->validator->validateBytes(
                $contents,
                basename((string) $media->path),
                $purpose,
            );
        } catch (Throwable) {
            $skipped[] = $skipKey;

            return;
        }

        if ($validated->mimeType !== $media->type || $validated->extension !== $media->ext) {
            $skipped[] = $skipKey;

            return;
        }

        if (! $zip->addFromString($entryName, $contents)) {
            $skipped[] = $skipKey;

            return;
        }

        $manifest[] = [
            'media_reference_key' => (string) $media->reference_key,
            'owner_reference_key' => $ownerReferenceKey,
            'role' => $role->value,
            'archive_filename' => $entryName,
            'validated_type' => $validated->mimeType,
            'extension' => $validated->extension,
            'sha256' => hash('sha256', $contents),
        ];
        $included++;
    }

    /**
     * @param  array<int, array<string, string>>  $manifest
     *
     * @throws JsonException
     */
    private function manifestJson(array $manifest): string
    {
        return json_encode([
            'schema_version' => 1,
            'media' => $manifest,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
