<?php

namespace App\Support\Media;

use App\Enums\MediaNamingStrategy;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

class ContentImagesExportManager
{
    private const ROOT = 'content-images-exports';

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

        $zip = new ZipArchive;

        if ($zip->open($temporaryPath, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to open the temporary content images zip.');
        }

        $included = 0;
        $skipped = [];

        $groups = ContentGroup::query()
            ->with(['contentItems' => fn ($query): mixed => $query
                ->orderBy('published_at')
                ->orderBy('id')])
            ->when($contentGroupId, fn ($query): mixed => $query->whereKey($contentGroupId))
            ->orderBy('title')
            ->orderBy('id')
            ->get();

        foreach ($groups as $group) {
            $podcastStem = ImageFileNamer::storageStem($group->slug, $group->reference_key, $strategy);

            if (filled($group->cover_path)) {
                $entryName = "podcasts/{$podcastStem}/cover.".$this->extensionForPath((string) $group->cover_path);

                $this->addPublicFile($zip, (string) $group->cover_path, $entryName, $included, $skipped);
            }

            foreach ($group->contentItems as $item) {
                if (! $item instanceof ContentItem || blank($item->image_path)) {
                    continue;
                }

                $episodeFileName = ImageFileNamer::exportFileName(
                    $item->slug,
                    $item->reference_key,
                    $this->mimeTypeForPath((string) $item->image_path),
                    $strategy,
                );

                $entryName = "podcasts/{$podcastStem}/episodes/{$episodeFileName}";

                $this->addPublicFile($zip, (string) $item->image_path, $entryName, $included, $skipped);
            }
        }

        $zip->close();

        Storage::disk('local')->makeDirectory(dirname($path));
        Storage::disk('local')->put($path, file_get_contents($temporaryPath) ?: '');

        @unlink($temporaryPath);

        return [
            'token' => $token,
            'path' => $path,
            'included' => $included,
            'skipped' => $skipped,
        ];
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
     */
    private function addPublicFile(ZipArchive $zip, string $path, string $entryName, int &$included, array &$skipped): void
    {
        $path = $this->normalize($path);

        if ($path === null || ! Storage::disk('public')->exists($path)) {
            $skipped[] = $path ?? __('admin.labels.none');

            return;
        }

        $contents = Storage::disk('public')->get($path);

        if (! is_string($contents) || $contents === '') {
            $skipped[] = $path;

            return;
        }

        $zip->addFromString($entryName, $contents);
        $included++;
    }

    private function mimeTypeForPath(string $path): string
    {
        $path = $this->normalize($path);

        if ($path === null) {
            return 'image/jpeg';
        }

        $mimeType = Storage::disk('public')->mimeType($path);

        if (is_string($mimeType)) {
            try {
                ImageFileNamer::extensionForMimeType($mimeType);

                return $mimeType;
            } catch (\InvalidArgumentException) {
                // Fall back to the stored extension when the adapter reports a generic MIME type.
            }
        }

        return $this->mimeTypeForExtension($this->extensionForPath($path));
    }

    private function extensionForPath(string $path): string
    {
        $extension = mb_strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'jpeg' => 'jpg',
            'jpg', 'png', 'webp', 'svg' => $extension,
            default => 'jpg',
        };
    }

    private function mimeTypeForExtension(string $extension): string
    {
        return match ($extension) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            default => 'image/jpeg',
        };
    }

    private function normalize(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        $path = str_replace('\\', '/', trim((string) $path));
        $path = preg_replace('#/+#', '/', $path) ?: '';

        if (str_contains($path, '../') || str_starts_with($path, '/')) {
            return null;
        }

        return $path;
    }
}
