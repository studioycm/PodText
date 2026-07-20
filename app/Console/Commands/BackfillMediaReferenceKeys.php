<?php

namespace App\Console\Commands;

use App\Enums\MediaMutationOperationType;
use App\Enums\MediaMutationStatus;
use App\Models\Media;
use App\Models\MediaMutationOperation;
use App\Support\Media\StoredMediaValidator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class BackfillMediaReferenceKeys extends Command
{
    protected $signature = 'media:backfill-reference-keys {--apply : Persist generated reference keys}';

    protected $description = 'Report or idempotently populate missing Curator media reference keys.';

    public function handle(StoredMediaValidator $validator): int
    {
        $counts = [
            'eligible' => 0,
            'updated' => 0,
            'disallowed' => 0,
            'pending_svg_sanitation' => 0,
        ];

        Media::query()
            ->whereNull('reference_key')
            ->select('id')
            ->chunkById(100, function ($rows) use ($validator, &$counts): void {
                foreach ($rows as $row) {
                    DB::transaction(function () use ($row, $validator, &$counts): void {
                        $media = Media::query()
                            ->whereKey($row->getKey())
                            ->whereNull('reference_key')
                            ->lockForUpdate()
                            ->first();

                        if (! $media instanceof Media) {
                            return;
                        }

                        if ($media->type === 'image/svg+xml') {
                            $counts['pending_svg_sanitation']++;

                            return;
                        }

                        try {
                            $validated = $validator->validateForReferenceKeyBackfill($media);
                        } catch (Throwable) {
                            $counts['disallowed']++;

                            return;
                        }

                        $counts['eligible']++;

                        if (! $this->option('apply')) {
                            return;
                        }

                        $referenceKey = (string) Str::ulid();
                        $updated = Media::query()
                            ->whereKey($media->getKey())
                            ->whereNull('reference_key')
                            ->update(['reference_key' => $referenceKey]);

                        if ($updated !== 1) {
                            return;
                        }

                        $now = now();
                        MediaMutationOperation::query()->create([
                            'operation_key' => (string) Str::ulid(),
                            'media_id' => $media->getKey(),
                            'media_id_snapshot' => $media->getKey(),
                            'media_reference_key' => $referenceKey,
                            'operation' => MediaMutationOperationType::ReferenceKeyBackfill,
                            'status' => MediaMutationStatus::Completed,
                            'purpose' => $validated->purpose->value,
                            'destination_disk' => 'public',
                            'destination_path' => $media->path,
                            'destination_sha256' => $validated->sha256,
                            'context' => ['proof' => 'exact_normalized_bytes'],
                            'attempts' => 1,
                            'started_at' => $now,
                            'committed_at' => $now,
                            'cleanup_completed_at' => $now,
                            'completed_at' => $now,
                        ]);
                        $counts['updated']++;
                    });
                }
            });

        if (! $this->option('apply')) {
            $this->components->info("Dry run: eligible={$counts['eligible']}, disallowed={$counts['disallowed']}, pending_svg_sanitation={$counts['pending_svg_sanitation']}. Re-run with --apply to persist content-proven keys.");

            return self::SUCCESS;
        }

        $this->components->info("Media reference-key backfill complete: updated={$counts['updated']}, disallowed={$counts['disallowed']}, pending_svg_sanitation={$counts['pending_svg_sanitation']}.");

        return self::SUCCESS;
    }
}
