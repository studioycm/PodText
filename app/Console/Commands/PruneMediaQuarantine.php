<?php

namespace App\Console\Commands;

use App\Enums\MediaMutationStatus;
use App\Models\MediaMutationOperation;
use App\Support\UiTimezone;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

class PruneMediaQuarantine extends Command
{
    private const OPERATION_KEY_PATTERN = '/^[0-9A-HJKMNP-TV-Z]{26}$/';

    protected $signature = 'media:prune-quarantine
        {--apply : Delete quarantine directories for completed operations older than the retention window}
        {--days= : Override the configured retention window in days}';

    protected $description = 'Report or prune quarantine copies of completed media mutations past the retention window. Incomplete operations are never touched: their quarantine copy is a hard precondition for repair.';

    public function handle(): int
    {
        $retentionDays = $this->retentionDays();

        if ($retentionDays === null) {
            $this->components->error('The retention window must be a non-negative whole number of days.');

            return self::FAILURE;
        }

        if ($retentionDays === 0) {
            $this->components->info('Quarantine pruning is disabled: the retention window is 0 (keep forever).');

            return self::SUCCESS;
        }

        $query = $this->prunableQuery($retentionDays);

        if (! $this->option('apply')) {
            $rows = $query->get(['operation_key', 'operation', 'quarantine_disk', 'quarantine_path', 'completed_at', 'context']);

            $this->table(
                ['operation_key', 'operation', 'completed_at', 'quarantine_path', 'state'],
                $rows->map(fn (MediaMutationOperation $operation): array => [
                    $operation->operation_key,
                    (string) $operation->getRawOriginal('operation'),
                    $operation->completed_at?->timezone(UiTimezone::name())->format('d/m/Y H:i') ?? '',
                    (string) $operation->quarantine_path,
                    $this->rowState($operation),
                ])->all(),
            );
            $this->components->info("Dry run: {$rows->count()} completed quarantine director(ies) exceed the {$retentionDays}-day retention window. Re-run with --apply to prune them.");

            return self::SUCCESS;
        }

        $pruned = 0;
        $previouslyPruned = 0;
        $skipped = 0;

        $query->eachById(function (MediaMutationOperation $operation) use (&$pruned, &$previouslyPruned, &$skipped): void {
            if ($this->rowState($operation) === 'manual review: unexpected key or path shape') {
                $skipped++;
                $this->components->warn("Skipped {$operation->operation_key}: the operation key or quarantine path is not shaped like the machinery wrote it.");

                return;
            }

            $context = is_array($operation->context) ? $operation->context : [];
            $disk = (string) ($operation->quarantine_disk ?: 'local');
            $directory = 'media-quarantine/'.$operation->operation_key;

            if (isset($context['quarantine_pruned_at']) && ! Storage::disk($disk)->directoryExists($directory)) {
                $previouslyPruned++;

                return;
            }

            Storage::disk($disk)->deleteDirectory($directory);
            $operation->forceFill([
                'context' => array_merge($context, ['quarantine_pruned_at' => now()->toIso8601String()]),
            ])->saveQuietly();

            $pruned++;
        });

        $this->components->info("Pruned {$pruned} quarantine director(ies); {$previouslyPruned} previously pruned; {$skipped} skipped for manual review. Journal rows keep their quarantine path and checksum.");

        return $skipped === 0 ? self::SUCCESS : self::FAILURE;
    }

    /** @return Builder<MediaMutationOperation> */
    private function prunableQuery(int $retentionDays): Builder
    {
        return MediaMutationOperation::query()
            ->where('status', MediaMutationStatus::Completed->value)
            ->whereNotNull('completed_at')
            ->where('completed_at', '<=', now()->subDays($retentionDays))
            ->whereNotNull('quarantine_path')
            ->where('quarantine_path', '!=', '')
            ->orderBy('id');
    }

    private function rowState(MediaMutationOperation $operation): string
    {
        $operationKey = (string) $operation->operation_key;

        if (
            preg_match(self::OPERATION_KEY_PATTERN, $operationKey) !== 1
            || ! str_starts_with((string) $operation->quarantine_path, "media-quarantine/{$operationKey}/")
        ) {
            return 'manual review: unexpected key or path shape';
        }

        $context = is_array($operation->context) ? $operation->context : [];

        return isset($context['quarantine_pruned_at']) ? 'previously pruned' : 'prunable';
    }

    private function retentionDays(): ?int
    {
        $override = $this->option('days');

        if ($override !== null) {
            return ctype_digit((string) $override) ? (int) $override : null;
        }

        $configured = config('media.quarantine.retention_days', 90);

        return is_numeric($configured) && (int) $configured >= 0 ? (int) $configured : null;
    }
}
