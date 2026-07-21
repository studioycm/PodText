<?php

namespace App\Support\Media;

use App\Enums\ImageUploadPurpose;
use App\Enums\LegacyMediaTransitionDisposition;
use App\Models\Media;
use App\Settings\PublicContentSettings;
use App\Support\SettingsLifecycle\SettingsMediaIdentityProjector;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

class LegacyMediaTransitionPlanner
{
    public function __construct(
        private readonly CuratorImageUploadPolicy $policy,
        private readonly ImageUploadValidator $validator,
        private readonly MediaReferenceFinder $references,
    ) {}

    public function manifest(): LegacyMediaTransitionManifest
    {
        $schema = $this->schemaFacts();

        if (! ($schema['curator']['table'] ?? false)) {
            return new LegacyMediaTransitionManifest($schema, [], now()->toIso8601String());
        }

        $columns = $schema['curator']['columns'];
        $required = ['id', 'disk', 'path', 'directory', 'name', 'visibility', 'type', 'ext', 'size'];

        if (array_diff($required, $columns) !== []) {
            return new LegacyMediaTransitionManifest($schema, [[
                'media_id' => 0,
                'path' => '',
                'disposition' => LegacyMediaTransitionDisposition::Blocked->value,
                'reason' => 'curator_schema_incomplete',
                'active_references' => [],
            ]], now()->toIso8601String());
        }

        $select = collect(['id', 'disk', 'path', 'directory', 'name', 'visibility', 'type', 'ext', 'size', 'width', 'height', 'reference_key'])
            ->intersect($columns)
            ->values()
            ->all();
        $rows = DB::table('curator')->select($select)->orderBy('id')->get();
        $entries = $rows->map(fn (object $row): array => $this->entry($row, $columns))->all();
        $knownPaths = collect($entries)->pluck('path')->filter()->all();
        $candidates = collect();
        if (Schema::hasTable('content_groups') && Schema::hasColumn('content_groups', 'cover_path')) {
            $candidates = $candidates->merge(DB::table('content_groups')->whereNotNull('cover_path')->whereNotIn('cover_path', $knownPaths)->orderBy('cover_path')->pluck('cover_path'));
        }
        if (Schema::hasTable('content_items') && Schema::hasColumn('content_items', 'image_path')) {
            $candidates = $candidates->merge(DB::table('content_items')->whereNotNull('image_path')->whereNotIn('image_path', $knownPaths)->orderBy('image_path')->pluck('image_path'));
        }
        // Settings are an owner too. Only ask the projector about paths that
        // occur in its explicit media slots; never regex arbitrary payload text.
        if (Schema::hasTable('settings') && collect(['group', 'name', 'payload'])->every(fn (string $column): bool => Schema::hasColumn('settings', $column))) {
            $payload = DB::table('settings')->where('group', PublicContentSettings::group())->whereIn('name', ['menu_config', 'about_page', 'default_images'])->orderBy('name')->pluck('payload', 'name')->all();
            foreach ($this->settingsPaths($payload) as $path) {
                if (! in_array($path, $knownPaths, true)) {
                    $candidates->push($path);
                }
            }
        }
        $entries = array_merge($entries, $candidates->filter()->unique()->sort()->map(function (string $path): array {
            $references = $this->referenceTokensForPath($path);
            try {
                $plan = app(LegacyMediaRegistrationPlanner::class)->plan($path);

                return $this->fingerprinted([
                    'media_id' => 0, 'path' => $path, 'reference_key' => null,
                    'active_references' => $references,
                    'disposition' => LegacyMediaTransitionDisposition::ImportExactPath->value,
                    'reason' => 'rowless_exact_fixed_root_path',
                    'purpose' => $plan->purpose->value,
                    'source_sha256' => $plan->sourceSha256,
                    'normalized_sha256' => $plan->validatedImage->sha256,
                    'metadata' => ['disk' => 'public', 'visibility' => 'public', 'type' => $plan->validatedImage->mimeType, 'ext' => $plan->validatedImage->extension, 'size' => $plan->validatedImage->size, 'width' => $plan->validatedImage->width, 'height' => $plan->validatedImage->height],
                    'review_state' => ['settings' => $plan->settingsSnapshots, 'owners' => $references],
                ]);
            } catch (Throwable $exception) {
                $reason = str_contains($exception->getMessage(), 'missing') ? 'rowless_source_missing' : 'rowless_path_or_source_invalid';

                return $this->fingerprinted(['media_id' => 0, 'path' => $path, 'reference_key' => null, 'active_references' => $references, 'disposition' => $references === [] ? LegacyMediaTransitionDisposition::Blocked->value : LegacyMediaTransitionDisposition::DetachToDefault->value, 'reason' => $reason]);
            }
        })->all());

        return new LegacyMediaTransitionManifest($schema, $entries, now()->toIso8601String());
    }

    public function assertClosed(LegacyMediaTransitionManifest $manifest): void
    {
        foreach ($manifest->entries as $entry) {
            if (($entry['media_id'] ?? 0) === 0 && ($entry['reason'] ?? null) === 'curator_schema_incomplete') {
                throw new \RuntimeException('The legacy transition schema is incomplete.');
            }

            if (($entry['active_references'] ?? []) !== [] && ! in_array($entry['disposition'] ?? null, array_column(LegacyMediaTransitionDisposition::cases(), 'value'), true)) {
                throw new \RuntimeException('An active legacy media reference has no reviewed transition disposition.');
            }

            if (($entry['disposition'] ?? null) === LegacyMediaTransitionDisposition::Blocked->value && blank($entry['reason'] ?? null)) {
                throw new \RuntimeException('A blocked legacy media row lacks an explicit reason.');
            }
        }
    }

    /** @param array<string, mixed> $reviewedEntry */
    public function assertEntryCurrent(array $reviewedEntry, bool $ignoreOwnOpenJournal = false): void
    {
        $mediaId = (int) ($reviewedEntry['media_id'] ?? 0);
        $columns = $this->schemaFacts()['curator']['columns'] ?? [];
        $row = $mediaId > 0 ? DB::table('curator')->where('id', $mediaId)->first() : null;
        $current = is_object($row) ? $this->entry($row, $columns) : null;
        if ($ignoreOwnOpenJournal && is_array($current)) {
            data_set($reviewedEntry, 'review_state.open_journal', false);
            data_set($current, 'review_state.open_journal', false);
            $current['disposition'] = $reviewedEntry['disposition'] ?? null;
            $current['reason'] = $reviewedEntry['reason'] ?? null;
            $reviewedEntry = $this->fingerprinted($reviewedEntry);
            $current = $this->fingerprinted($current);
        }
        if (! is_array($current) || ! hash_equals((string) ($reviewedEntry['fingerprint'] ?? ''), (string) ($current['fingerprint'] ?? ''))) {
            throw new \RuntimeException('The reviewed legacy transition entry changed before commit.');
        }
    }

    /** @return array<string, mixed> */
    private function entry(object $row, array $columns): array
    {
        $media = new Media;
        $media->setRawAttributes((array) $row, true);
        $path = (string) ($row->path ?? '');
        $references = $this->referenceTokens($media);
        $base = [
            'media_id' => (int) $row->id,
            'path' => $path,
            'reference_key' => $row->reference_key ?? null,
            'active_references' => $references,
            'metadata' => collect(['disk', 'directory', 'name', 'visibility', 'type', 'ext', 'size', 'width', 'height'])
                ->filter(fn (string $column): bool => in_array($column, $columns, true))
                ->mapWithKeys(fn (string $column): array => [$column => $row->{$column} ?? null])
                ->all(),
        ];

        try {
            $purpose = $this->policy->purposeForPath($path);
        } catch (Throwable) {
            return $this->fingerprinted($base + ['disposition' => LegacyMediaTransitionDisposition::Blocked->value, 'reason' => 'path_outside_fixed_roots']);
        }

        $base['purpose'] = $purpose->value;

        $base['review_state'] = $this->reviewState((int) $row->id, $path, $purpose);

        if ((string) ($row->directory ?? '') !== dirname($path) || (string) ($row->name ?? '') !== pathinfo($path, PATHINFO_FILENAME)) {
            return $this->fingerprinted($base + ['disposition' => $references === [] ? LegacyMediaTransitionDisposition::Blocked->value : LegacyMediaTransitionDisposition::DetachToDefault->value, 'reason' => 'stored_path_identity_mismatch']);
        }

        if (DB::table('curator')->where('id', '!=', $row->id)->where(function ($query) use ($row): void {
            $query->where('path', $row->path)->orWhere(fn ($query) => $query->where('disk', $row->disk)->where('directory', $row->directory)->where('name', $row->name));
        })->exists()) {
            return $this->fingerprinted($base + ['disposition' => LegacyMediaTransitionDisposition::Blocked->value, 'reason' => 'duplicate_storage_identity']);
        }

        if (($row->type ?? null) === 'image/svg+xml') {
            // SVG is never executable merely because its MIME claims SVG: it
            // must still have a real bounded public source and coherent row.
            try {
                $public = Storage::disk('public');
                $visible = $public->exists($path) && $public->visibility($path) === 'public';
            } catch (Throwable) {
                $visible = false;
            }
            if (($row->disk ?? null) !== 'public' || ($row->visibility ?? null) !== 'public' || ! $visible) {
                return $this->fingerprinted($base + ['disposition' => $references === [] ? LegacyMediaTransitionDisposition::Blocked->value : LegacyMediaTransitionDisposition::DetachToDefault->value, 'reason' => 'svg_source_missing_or_nonpublic']);
            }
            try {
                $source = app(LegacyMediaRegistrationPlanner::class)->plan($path, expectedExistingMediaId: (int) $row->id);
                $base['source_sha256'] = $source->sourceSha256;
                $base['sanitized_sha256'] = $source->validatedImage->sha256;
                $base['normalized_sha256'] = $source->validatedImage->sha256;
                if (($row->ext ?? null) !== 'svg' || ($row->type ?? null) !== $source->validatedImage->mimeType || (int) ($row->size ?? 0) !== strlen($source->sourceContents) || $row->width !== null || $row->height !== null) {
                    throw new \InvalidArgumentException('stored SVG metadata is incoherent');
                }
            } catch (Throwable) {
                return $this->fingerprinted($base + ['disposition' => $references === [] ? LegacyMediaTransitionDisposition::Blocked->value : LegacyMediaTransitionDisposition::DetachToDefault->value, 'reason' => 'svg_source_invalid']);
            }

            return $this->fingerprinted($base + ['disposition' => LegacyMediaTransitionDisposition::SanitizeSvg->value, 'reason' => 'svg_requires_staged_sanitation']);
        }

        try {
            if (($row->disk ?? null) !== 'public' || ($row->visibility ?? null) !== 'public' || ! Storage::disk('public')->exists($path)) {
                throw new \InvalidArgumentException('missing_or_nonpublic_source');
            }
            if (Storage::disk('public')->visibility($path) !== 'public') {
                throw new \InvalidArgumentException('source is not public');
            }
            $plan = app(LegacyMediaRegistrationPlanner::class)->plan($path, expectedExistingMediaId: (int) $row->id);
            $source = $plan->sourceContents;
            $validated = $plan->validatedImage;
            $base['source_sha256'] = $plan->sourceSha256;
            $base['normalized_sha256'] = $validated->sha256;
            if (($row->type ?? null) !== $validated->mimeType || ($row->ext ?? null) !== $validated->extension || (int) ($row->size ?? 0) !== strlen($source) || ((int) ($row->width ?? 0) !== $validated->width) || ((int) ($row->height ?? 0) !== $validated->height)) {
                return $this->fingerprinted($base + ['disposition' => $references === [] ? LegacyMediaTransitionDisposition::Blocked->value : LegacyMediaTransitionDisposition::DetachToDefault->value, 'reason' => 'stored_metadata_mismatch']);
            }
        } catch (Throwable) {
            return $this->fingerprinted($base + [
                'disposition' => $references === [] ? LegacyMediaTransitionDisposition::Blocked->value : LegacyMediaTransitionDisposition::DetachToDefault->value,
                'reason' => 'source_missing_or_invalid',
            ]);
        }

        if (($base['review_state']['open_journal'] ?? false) === true) {
            return $this->fingerprinted($base + ['disposition' => LegacyMediaTransitionDisposition::Blocked->value, 'reason' => 'incomplete_mutation_operation']);
        }

        if (filled($row->reference_key)) {
            return $this->fingerprinted($base + ['disposition' => LegacyMediaTransitionDisposition::Blocked->value, 'reason' => 'already_keyed_or_metadata_review_required']);
        }

        return $this->fingerprinted($base + [
            'disposition' => hash_equals($base['source_sha256'], $base['normalized_sha256'])
                ? LegacyMediaTransitionDisposition::KeyOnly->value
                : LegacyMediaTransitionDisposition::NormalizeExisting->value,
            'reason' => hash_equals($base['source_sha256'], $base['normalized_sha256']) ? 'exact_normalized_bytes' : 'raster_requires_normalization',
        ]);
    }

    /** @return array<string, mixed> */
    private function schemaFacts(): array
    {
        $tables = ['curator', 'content_groups', 'content_items', 'settings', 'media_attachments', 'media_mutation_operations'];

        $migrationNames = [
            'settings/2026_07_20_000000_add_public_media_reference_keys.php',
            '2026_07_20_000001_add_reference_key_to_curator_table.php',
            '2026_07_20_000002_create_media_attachments_table.php',
            '2026_07_20_000003_create_media_mutation_operations_table.php',
        ];
        $migrationHashes = collect($migrationNames)
            ->map(fn (string $name): string => str_starts_with($name, 'settings/') ? database_path($name) : database_path("migrations/{$name}"))
            ->filter(fn (string $path): bool => is_file($path))
            ->mapWithKeys(fn (string $path): array => [basename($path) => hash_file('sha256', $path)])
            ->sort()
            ->all();

        return collect($tables)->mapWithKeys(function (string $table): array {
            $exists = Schema::hasTable($table);

            return [$table => [
                'table' => $exists,
                'columns' => $exists ? collect(Schema::getColumnListing($table))->sort()->values()->all() : [],
            ]];
        })->all() + ['migration_file_sha256' => $migrationHashes];
    }

    /** @return array<int, string> */
    private function referenceTokens(Media $media): array
    {
        $path = (string) $media->path;
        $tokens = $this->referenceTokensForPath($path);
        if (Schema::hasTable('media_attachments') && collect(['media_id', 'attachable_type', 'attachable_id', 'role', 'position'])->every(fn (string $column): bool => Schema::hasColumn('media_attachments', $column))) {
            $tokens = array_merge($tokens, DB::table('media_attachments')->where('media_id', $media->getKey())->orderBy('id')->get(['attachable_type', 'attachable_id', 'role'])->map(fn (object $row): string => "attachment:{$row->attachable_type}:{$row->attachable_id}:{$row->role}")->all());
        }

        return collect($tokens)->sort()->values()->all();
    }

    /** @return array<int, string> */
    private function referenceTokensForPath(string $path): array
    {
        $tokens = [];
        if (Schema::hasTable('content_groups') && Schema::hasColumn('content_groups', 'cover_path')) {
            $tokens = array_merge($tokens, DB::table('content_groups')->where('cover_path', $path)->orderBy('id')->pluck('id')->map(fn (mixed $id): string => "content_group:{$id}:cover")->all());
        }
        if (Schema::hasTable('content_items') && Schema::hasColumn('content_items', 'image_path')) {
            $tokens = array_merge($tokens, DB::table('content_items')->where('image_path', $path)->orderBy('id')->pluck('id')->map(fn (mixed $id): string => "content_item:{$id}:primary_image")->all());
        }
        if (Schema::hasTable('settings') && collect(['group', 'name', 'payload'])->every(fn (string $column): bool => Schema::hasColumn('settings', $column))) {
            try {
                $purpose = $this->policy->purposeForPath($path);
            } catch (Throwable) {
                return collect($tokens)->sort()->values()->all();
            }
            $tokens = array_merge($tokens, DB::table('settings')
                ->where('group', PublicContentSettings::group())
                ->orderBy('name')
                ->get(['name', 'payload'])
                ->flatMap(function (object $row) use ($path, $purpose): array {
                    $payload = json_decode((string) $row->payload, true);
                    if (! is_array($payload)) {
                        return [];
                    }
                    try {
                        $locations = app(SettingsMediaIdentityProjector::class)->legacyRegistrationLocations([(string) $row->name => $payload], $path, $purpose);
                    } catch (Throwable) {
                        // The integrity report owns the explicit settings
                        // identity error; preflight must remain total.
                        return [];
                    }

                    return collect($locations)->map(fn (string $location): string => "settings:{$row->name}:{$location}")->all();
                })
                ->all());
        }

        return collect($tokens)->sort()->values()->all();
    }

    /** @param array<string, mixed> $entry @return array<string, mixed> */
    private function fingerprinted(array $entry): array
    {
        $review = $entry;
        unset($review['fingerprint']);
        $entry['fingerprint'] = hash('sha256', json_encode(Arr::sortRecursive($review), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        return $entry;
    }

    /** @return array<string, mixed> */
    private function reviewState(int $mediaId, string $path, ImageUploadPurpose $purpose): array
    {
        $attachments = Schema::hasTable('media_attachments') && collect(['media_id', 'attachable_type', 'attachable_id', 'role', 'position'])->every(fn (string $column): bool => Schema::hasColumn('media_attachments', $column))
            ? DB::table('media_attachments')->where('media_id', $mediaId)->orderBy('id')->get(['media_id', 'attachable_type', 'attachable_id', 'role', 'position'])->map(fn (object $row): array => (array) $row)->all()
            : [];
        $settings = [];
        if (Schema::hasTable('settings') && collect(['group', 'name', 'payload', 'locked'])->every(fn (string $column): bool => Schema::hasColumn('settings', $column))) {
            $settings = DB::table('settings')->where('group', PublicContentSettings::group())->orderBy('name')->get(['name', 'payload', 'locked'])->map(function (object $row) use ($path, $purpose): array {
                $payload = json_decode((string) $row->payload, true);
                try {
                    $locations = is_array($payload)
                        ? app(SettingsMediaIdentityProjector::class)->legacyRegistrationLocations([(string) $row->name => $payload], $path, $purpose)
                        : [];
                } catch (Throwable) {
                    $locations = [];
                }

                return ['name' => $row->name, 'payload_sha256' => hash('sha256', (string) $row->payload), 'locked' => (bool) $row->locked, 'locations' => $locations];
            })->filter(fn (array $row): bool => $row['locations'] !== [])->values()->all();
        }

        $openJournal = Schema::hasTable('media_mutation_operations') && collect(['media_id', 'status'])->every(fn (string $column): bool => Schema::hasColumn('media_mutation_operations', $column))
            ? DB::table('media_mutation_operations')->where('media_id', $mediaId)->whereIn('status', ['staged', 'copied', 'committed', 'cleanup_pending'])->exists()
            : false;

        return ['attachments' => $attachments, 'settings' => $settings, 'open_journal' => $openJournal];
    }

    /** @param array<string, mixed> $payload @return array<int, string> */
    private function settingsPaths(array $payload): array
    {
        $decoded = collect($payload)->mapWithKeys(function (mixed $json, mixed $name): array {
            try {
                $value = is_string($json) ? json_decode($json, true, flags: JSON_THROW_ON_ERROR) : null;
            } catch (Throwable) {
                $value = null;
            }

            return is_array($value) ? [(string) $name => $value] : [];
        })->all();
        $paths = [];
        $walk = function (mixed $value, string $key = '') use (&$walk, &$paths): void {
            if (! is_array($value)) {
                return;
            }
            foreach ($value as $name => $item) {
                if (is_string($item) && in_array((string) $name, ['light_path', 'dark_path', 'image_path', 'path'], true)) {
                    $paths[] = $item;
                }
                $walk($item, (string) $name);
            }
        };
        $walk($decoded);

        return collect($paths)->filter(fn (string $path): bool => filled($path))->unique()->sort()->values()->all();
    }
}
