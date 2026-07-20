<?php

namespace App\Console\Commands;

use App\Enums\MediaMutationOperationType;
use App\Enums\MediaMutationStatus;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\Media;
use App\Models\MediaMutationOperation;
use App\Models\User;
use App\Settings\PublicContentSettings;
use App\Support\Media\LegacyMediaRegistrationPlanner;
use App\Support\Media\MediaFilesystemMutationCoordinator;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

class RegisterExistingCuratorMedia extends Command
{
    protected $signature = 'media:register-existing-curator-assets
        {--apply : Register one explicitly reviewed legacy path}
        {--actor= : Admin-or-higher user ID required for apply}
        {--path=* : Exact normalized legacy path; apply requires exactly one}';

    protected $description = 'Report or journal-register referenced legacy public images through the app-owned media boundary.';

    public function handle(
        LegacyMediaRegistrationPlanner $planner,
        MediaFilesystemMutationCoordinator $coordinator,
    ): int {
        if (
            ! Schema::hasTable('curator')
            || ! Schema::hasColumn('curator', 'reference_key')
            || ! Schema::hasTable('media_attachments')
            || ! Schema::hasTable('media_mutation_operations')
        ) {
            $this->components->error('The app-owned media schema is incomplete. Run all Curator G1 migrations first.');

            return self::FAILURE;
        }

        $requestedPaths = collect((array) $this->option('path'))
            ->filter(fn (mixed $path): bool => is_string($path) && filled($path))
            ->values()
            ->all();

        if ($this->option('apply')) {
            return $this->applyRegistration($requestedPaths, $planner, $coordinator);
        }

        $paths = $requestedPaths !== [] ? $requestedPaths : $this->candidatePaths();
        $eligible = 0;
        $existing = 0;
        $registered = 0;
        $missing = 0;
        $skipped = 0;
        $rows = [];

        foreach ($paths as $path) {
            if (Media::query()->where('disk', 'public')->where('path', $path)->exists()) {
                $existing++;
                $rows[] = [$path, '-', '-', '-', 'existing_media'];

                continue;
            }

            $completed = $this->completedRegistration($path);

            if (
                $completed instanceof MediaMutationOperation
                && $this->registrationIsCurrent($completed, $path)
            ) {
                $registered++;
                $rows[] = [
                    $path,
                    (string) $completed->purpose,
                    (string) $completed->source_sha256,
                    '-',
                    $completed->status === MediaMutationStatus::Completed
                        ? 'already_registered'
                        : 'registration_cleanup_pending',
                ];

                continue;
            }

            if (! Storage::disk('public')->exists($path)) {
                $missing++;
                $rows[] = [$path, '-', '-', '-', 'missing'];

                continue;
            }

            try {
                $plan = $planner->plan($path);
                $eligible++;
                $rows[] = [
                    $path,
                    $plan->purpose->value,
                    $plan->sourceSha256,
                    (string) $plan->referenceCount(),
                    'eligible',
                ];
            } catch (Throwable $exception) {
                $skipped++;
                $rows[] = [$path, '-', '-', '-', 'disallowed: '.$exception->getMessage()];
            }
        }

        $this->table(['path', 'purpose', 'sha256', 'references', 'disposition'], $rows);
        $this->components->info(
            "Dry run: {$eligible} eligible, {$existing} existing media, {$registered} already registered, {$missing} missing, {$skipped} disallowed. No rows or files were changed.",
        );

        return self::SUCCESS;
    }

    /**
     * @param  array<int, string>  $paths
     */
    private function applyRegistration(
        array $paths,
        LegacyMediaRegistrationPlanner $planner,
        MediaFilesystemMutationCoordinator $coordinator,
    ): int {
        if (count($paths) !== 1) {
            $this->components->error('Apply requires exactly one --path value reviewed in the dry-run report.');

            return self::FAILURE;
        }

        $actorId = $this->option('actor');

        if (! is_string($actorId) || ! ctype_digit($actorId) || (int) $actorId < 1) {
            $this->components->error('Apply requires a valid --actor Admin-or-higher user ID.');

            return self::FAILURE;
        }

        $actor = User::query()->find((int) $actorId);

        if (! $actor instanceof User) {
            $this->components->error('The registration actor does not exist.');

            return self::FAILURE;
        }

        $path = $paths[0];
        $completed = $this->completedRegistration($path);

        if (
            $completed instanceof MediaMutationOperation
            && $this->registrationIsCurrent($completed, $path)
        ) {
            if ($completed->status !== MediaMutationStatus::Completed) {
                $this->components->error("Media {$completed->media_id} is committed, but operation {$completed->operation_key} still requires media:repair-mutations.");

                return self::FAILURE;
            }

            $this->components->info("Already registered as media {$completed->media_id} ({$completed->media_reference_key}).");

            return self::SUCCESS;
        }

        try {
            $media = $coordinator->registerExistingPublicAsset($planner->plan($path), $actor);
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info("Registered media {$media->getKey()} ({$media->reference_key}) at {$media->path}.");

        return self::SUCCESS;
    }

    private function completedRegistration(string $path): ?MediaMutationOperation
    {
        return MediaMutationOperation::query()
            ->with('media')
            ->where('operation', MediaMutationOperationType::Registration->value)
            ->where('source_disk', 'public')
            ->where('source_path', $path)
            ->whereIn('status', [
                MediaMutationStatus::Committed->value,
                MediaMutationStatus::CleanupPending->value,
                MediaMutationStatus::Completed->value,
            ])
            ->latest('id')
            ->first();
    }

    private function registrationIsCurrent(MediaMutationOperation $operation, string $path): bool
    {
        if (! $operation->media instanceof Media || $this->hasLegacyReferences($path)) {
            return false;
        }

        return $operation->status === MediaMutationStatus::Completed
            ? ! Storage::disk('public')->exists($path)
            : in_array($operation->status, [
                MediaMutationStatus::Committed,
                MediaMutationStatus::CleanupPending,
            ], true);
    }

    private function hasLegacyReferences(string $path): bool
    {
        if (ContentGroup::query()->where('cover_path', $path)->exists()) {
            return true;
        }

        if (
            Schema::hasColumn('content_items', 'image_path')
            && ContentItem::query()->where('image_path', $path)->exists()
        ) {
            return true;
        }

        return in_array($path, $this->settingsPaths(), true);
    }

    /**
     * @return array<int, string>
     */
    private function candidatePaths(): array
    {
        return collect()
            ->merge(ContentGroup::query()->whereNotNull('cover_path')->pluck('cover_path'))
            ->merge(Schema::hasColumn('content_items', 'image_path')
                ? ContentItem::query()->whereNotNull('image_path')->pluck('image_path')
                : [])
            ->merge($this->settingsPaths())
            ->filter(fn (mixed $path): bool => is_string($path) && filled($path))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function settingsPaths(): array
    {
        if (! Schema::hasTable('settings')) {
            return [];
        }

        $payloads = DB::table('settings')
            ->where('group', PublicContentSettings::group())
            ->whereIn('name', ['menu_config', 'about_page', 'default_images'])
            ->pluck('payload', 'name')
            ->map(function (mixed $payload): array {
                if (! is_string($payload)) {
                    return [];
                }

                try {
                    $decoded = json_decode($payload, true, flags: JSON_THROW_ON_ERROR);
                } catch (Throwable) {
                    return [];
                }

                return is_array($decoded) ? $decoded : [];
            });
        $menuConfig = $payloads->get('menu_config', []);
        $defaultImages = $payloads->get('default_images', []);
        $aboutPage = $payloads->get('about_page', []);

        $paths = [
            data_get($menuConfig, 'logo.light_path'),
            data_get($menuConfig, 'logo.dark_path'),
        ];

        foreach ($defaultImages as $family) {
            $paths[] = is_array($family) ? ($family['path'] ?? null) : null;
        }

        foreach (Arr::wrap(data_get($aboutPage, 'blocks', [])) as $block) {
            if (is_array($block)) {
                $paths[] = $block['image_path'] ?? data_get($block, 'data.image_path');
            }
        }

        foreach (Arr::wrap(data_get($aboutPage, 'team_profiles', [])) as $profile) {
            if (is_array($profile)) {
                $paths[] = $profile['image_path'] ?? null;
            }
        }

        return collect($paths)
            ->filter(fn (mixed $path): bool => is_string($path) && filled($path))
            ->values()
            ->all();
    }
}
