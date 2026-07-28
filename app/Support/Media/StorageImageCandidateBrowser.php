<?php

namespace App\Support\Media;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use JsonException;
use Throwable;

class StorageImageCandidateBrowser
{
    public function __construct(
        private readonly CuratorImageUploadPolicy $policy,
    ) {}

    public function hasConfiguredSources(): bool
    {
        return $this->sources() !== [];
    }

    /** @return array<int, array{token: string, filename: string, source: string}> */
    public function browse(string $search = ''): array
    {
        $search = mb_strtolower(mb_substr(trim($search), 0, 100));
        $limit = min(max((int) config('media.acquisition.storage_candidate_limit', 50), 1), 100);
        $traversalLimit = min(max((int) config('media.acquisition.storage_traversal_limit', 2000), 1), 5000);
        $directoryLimit = min(max((int) config('media.acquisition.storage_directory_limit', 200), 1), 500);
        $candidates = [];
        $examined = 0;
        $directoriesVisited = 0;

        foreach ($this->sources() as $sourceId => $source) {
            $disk = Storage::disk($source['disk']);
            $pendingDirectories = [$source['root']];
            $queuedDirectories = [$source['root'] => true];

            while ($pendingDirectories !== []) {
                if ($directoriesVisited >= $directoryLimit || $examined >= $traversalLimit) {
                    return $candidates;
                }

                $directory = array_shift($pendingDirectories);
                $directoriesVisited++;

                $files = $disk->files($directory, false);
                sort($files, SORT_NATURAL | SORT_FLAG_CASE);

                foreach ($files as $path) {
                    if ($examined >= $traversalLimit) {
                        return $candidates;
                    }

                    $examined++;
                    $path = $this->normalizedPath($path);

                    if ($path === null || ! $this->isWithinRoot($path, $source['root'])) {
                        continue;
                    }

                    $filename = basename($path);
                    $haystack = mb_strtolower($filename.' '.$path.' '.$source['label']);

                    if (
                        ! $this->hasSupportedExtension($filename)
                        || ($search !== '' && ! str_contains($haystack, $search))
                    ) {
                        continue;
                    }

                    $row = $this->candidate($sourceId, $source, $path)->publicData();
                    $row['ext'] = mb_strtoupper(pathinfo($filename, PATHINFO_EXTENSION));

                    try {
                        $row['size'] = $disk->size($path);
                    } catch (Throwable) {
                        $row['size'] = null;
                    }

                    $candidates[] = $row;

                    if (count($candidates) >= $limit) {
                        return $candidates;
                    }
                }

                $directories = $disk->directories($directory, false);
                sort($directories, SORT_NATURAL | SORT_FLAG_CASE);

                foreach ($directories as $childDirectory) {
                    if ($examined >= $traversalLimit) {
                        return $candidates;
                    }

                    $examined++;
                    $childDirectory = $this->normalizedPath($childDirectory);

                    if (
                        $childDirectory === null
                        || ! $this->isWithinRoot($childDirectory, $source['root'])
                        || array_key_exists($childDirectory, $queuedDirectories)
                    ) {
                        continue;
                    }

                    $queuedDirectories[$childDirectory] = true;
                    $pendingDirectories[] = $childDirectory;
                }
            }
        }

        return $candidates;
    }

    public function resolve(string $token): StorageImageCandidate
    {
        try {
            $payload = json_decode(Crypt::decryptString($token), true, flags: JSON_THROW_ON_ERROR);
        } catch (DecryptException|JsonException) {
            throw new InvalidArgumentException('The Storage candidate identity is invalid.');
        }

        $sourceId = is_array($payload) ? ($payload['source'] ?? null) : null;
        $path = is_array($payload) ? ($payload['path'] ?? null) : null;

        if (! is_string($sourceId) || ! is_string($path)) {
            throw new InvalidArgumentException('The Storage candidate identity is incomplete.');
        }

        $source = $this->sources()[$sourceId] ?? null;

        $path = $this->normalizedPath($path);

        if (! is_array($source) || $path === null || ! $this->isWithinRoot($path, $source['root'])) {
            throw new InvalidArgumentException('The Storage candidate is outside its configured root.');
        }

        if (! $this->hasSupportedExtension($path) || ! Storage::disk($source['disk'])->exists($path)) {
            throw new InvalidArgumentException('The Storage candidate is unavailable.');
        }

        return $this->candidate($sourceId, $source, $path, $token);
    }

    /**
     * @return array<string, array{disk: string, root: string, mode: string, label: string}>
     */
    private function sources(): array
    {
        $configured = config('media.acquisition.storage_sources', []);

        if (! is_array($configured)) {
            return [];
        }

        $sources = [];

        foreach ($configured as $sourceId => $source) {
            if (! is_string($sourceId) || ! is_array($source)) {
                continue;
            }

            $disk = $source['disk'] ?? null;
            $root = $source['root'] ?? null;
            $mode = $source['mode'] ?? null;

            if (
                ! is_string($disk)
                || ! array_key_exists($disk, config('filesystems.disks', []))
                || ! is_string($root)
                || ! $this->isNormalizedRoot($root)
                || ! in_array($mode, ['register', 'copy'], true)
            ) {
                continue;
            }

            $labels = is_array($source['label'] ?? null) ? $source['label'] : [];
            $label = $labels[app()->getLocale()] ?? $labels['en'] ?? $sourceId;

            $sources[$sourceId] = [
                'disk' => $disk,
                'root' => $root,
                'mode' => $mode,
                'label' => is_string($label) ? $label : $sourceId,
            ];
        }

        return $sources;
    }

    /**
     * @param  array{disk: string, root: string, mode: string, label: string}  $source
     */
    private function candidate(
        string $sourceId,
        array $source,
        string $path,
        ?string $token = null,
    ): StorageImageCandidate {
        $token ??= Crypt::encryptString(json_encode([
            'source' => $sourceId,
            'path' => $path,
        ], JSON_THROW_ON_ERROR));

        return new StorageImageCandidate(
            sourceId: $sourceId,
            sourceLabel: $source['label'],
            disk: $source['disk'],
            path: $path,
            filename: basename($path),
            mode: $source['mode'],
            token: $token,
        );
    }

    private function hasSupportedExtension(string $path): bool
    {
        return in_array(mb_strtolower(pathinfo($path, PATHINFO_EXTENSION)), $this->policy->clientExtensions(), true);
    }

    private function isNormalizedRoot(string $root): bool
    {
        if ($root === '') {
            return true;
        }

        return trim($root) === $root
            && ! str_starts_with($root, '/')
            && ! str_ends_with($root, '/')
            && ! str_contains($root, '\\')
            && ! str_contains($root, "\0")
            && collect(explode('/', $root))->doesntContain(
                fn (string $segment): bool => in_array($segment, ['', '.', '..'], true),
            );
    }

    private function normalizedPath(string $path): ?string
    {
        if (
            trim($path) !== $path
            || $path === ''
            || str_starts_with($path, '/')
            || str_ends_with($path, '/')
            || str_contains($path, '\\')
            || str_contains($path, "\0")
            || collect(explode('/', $path))->contains(
                fn (string $segment): bool => in_array($segment, ['', '.', '..'], true),
            )
        ) {
            return null;
        }

        return $path;
    }

    private function isWithinRoot(string $path, string $root): bool
    {
        return $root === '' || str_starts_with($path, $root.'/');
    }
}
