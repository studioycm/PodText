<?php

namespace App\Support\Media;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use JsonException;

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
        $search = mb_strtolower(trim($search));
        $limit = min(max((int) config('media.acquisition.storage_candidate_limit', 50), 1), 100);
        $candidates = [];

        foreach ($this->sources() as $sourceId => $source) {
            foreach (Storage::disk($source['disk'])->files($source['root'], false) as $path) {
                $filename = basename($path);

                if (
                    ! $this->hasSupportedExtension($filename)
                    || ($search !== '' && ! str_contains(mb_strtolower($filename), $search))
                ) {
                    continue;
                }

                $candidates[] = $this->candidate($sourceId, $source, $path)->publicData();

                if (count($candidates) >= $limit) {
                    return $candidates;
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

        if (! is_array($source) || ! $this->isDirectChild($path, $source['root'])) {
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
        return trim($root) === $root
            && $root !== ''
            && ! str_starts_with($root, '/')
            && ! str_ends_with($root, '/')
            && ! str_contains($root, '\\')
            && ! str_contains($root, "\0")
            && collect(explode('/', $root))->doesntContain(
                fn (string $segment): bool => in_array($segment, ['', '.', '..'], true),
            );
    }

    private function isDirectChild(string $path, string $root): bool
    {
        if (! str_starts_with($path, $root.'/')) {
            return false;
        }

        $relative = substr($path, strlen($root) + 1);

        return $relative !== ''
            && ! str_contains($relative, '/')
            && ! str_contains($relative, '\\')
            && ! str_contains($relative, "\0")
            && ! in_array($relative, ['.', '..'], true);
    }
}
