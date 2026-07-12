<?php

namespace App\Console\Commands;

use App\Models\ContentGroup;
use App\Settings\PublicContentSettings;
use App\Support\Media\ImageFileNamer;
use Awcodes\Curator\Models\Media;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use SplFileInfo;

class RegisterExistingCuratorMedia extends Command
{
    protected $signature = 'media:register-existing-curator-assets';

    protected $description = 'Register existing PodText public image paths in the Curator media library without moving files.';

    public function handle(): int
    {
        if (! Schema::hasTable('curator')) {
            $this->components->error('The curator table does not exist. Run migrations first.');

            return self::FAILURE;
        }

        $paths = $this->candidatePaths();
        $created = 0;
        $existing = 0;
        $missing = 0;
        $skipped = 0;

        foreach ($paths as $path) {
            if (! $this->isAllowedPath($path)) {
                $skipped++;

                continue;
            }

            if (Media::query()->where('disk', 'public')->where('path', $path)->exists()) {
                $existing++;

                continue;
            }

            if (! Storage::disk('public')->exists($path)) {
                $missing++;

                continue;
            }

            Media::query()->create($this->mediaAttributes($path));
            $created++;
        }

        $this->components->info(
            "Curator media registration complete: {$created} created, {$existing} existing, {$missing} missing, {$skipped} skipped.",
        );

        return self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    private function candidatePaths(): array
    {
        return collect()
            ->merge(ContentGroup::query()->whereNotNull('cover_path')->pluck('cover_path'))
            ->merge($this->settingsPaths())
            ->filter(fn (mixed $path): bool => is_string($path) && filled($path))
            ->map(fn (string $path): string => str_replace('\\', '/', trim($path)))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function settingsPaths(): array
    {
        try {
            $settings = app(PublicContentSettings::class);
        } catch (\Throwable) {
            return [];
        }

        $paths = [
            data_get($settings->menu_config, 'logo.light_path'),
            data_get($settings->menu_config, 'logo.dark_path'),
        ];

        foreach (($settings->default_images ?? []) as $family) {
            $paths[] = is_array($family) ? ($family['path'] ?? null) : null;
        }

        foreach (Arr::wrap(data_get($settings->about_page, 'blocks', [])) as $block) {
            if (is_array($block)) {
                $paths[] = $block['image_path'] ?? data_get($block, 'data.image_path');
            }
        }

        foreach (Arr::wrap(data_get($settings->about_page, 'team_profiles', [])) as $profile) {
            if (is_array($profile)) {
                $paths[] = $profile['image_path'] ?? null;
            }
        }

        return collect($paths)
            ->filter(fn (mixed $path): bool => is_string($path) && filled($path))
            ->values()
            ->all();
    }

    private function mediaAttributes(string $path): array
    {
        $disk = Storage::disk('public');
        $fullPath = $disk->path($path);
        $dimensions = @getimagesize($fullPath) ?: [null, null];
        $info = new SplFileInfo($path);
        $extension = mb_strtolower((string) $info->getExtension());

        return [
            'disk' => 'public',
            'directory' => trim((string) $info->getPath(), '/'),
            'visibility' => 'public',
            'name' => $info->getBasename('.'.$extension),
            'path' => $path,
            'width' => $dimensions[0],
            'height' => $dimensions[1],
            'size' => $disk->size($path),
            'type' => $disk->mimeType($path) ?: $this->mimeTypeForExtension($extension),
            'ext' => $extension,
        ];
    }

    private function isAllowedPath(string $path): bool
    {
        if (str_starts_with($path, '/') || str_contains($path, '../')) {
            return false;
        }

        foreach (ImageFileNamer::appOwnedDirectories() as $directory) {
            if (str_starts_with($path, "{$directory}/")) {
                return true;
            }
        }

        return false;
    }

    private function mimeTypeForExtension(string $extension): string
    {
        return match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            default => 'application/octet-stream',
        };
    }
}
