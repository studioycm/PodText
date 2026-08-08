<?php

namespace App\Console\Commands;

use App\Enums\MediaMutationOperationType;
use App\Enums\MediaMutationRepairResult;
use App\Enums\MediaMutationStatus;
use App\Models\MediaMutationOperation;
use App\Support\Media\MediaFilesystemMutationCoordinator;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('media:repair-mutations
        {--apply : Repair or roll back incomplete journal entries}
        {--operation-key= : Limit the report or repair to one operation key}')]
#[Description('Report or idempotently repair incomplete media filesystem mutations.')]
class RepairMediaMutations extends Command
{
    public function handle(MediaFilesystemMutationCoordinator $coordinator): int
    {
        $query = MediaMutationOperation::query()
            ->whereIn('status', [
                MediaMutationStatus::Staged->value,
                MediaMutationStatus::Copied->value,
                MediaMutationStatus::Committed->value,
                MediaMutationStatus::CleanupPending->value,
                MediaMutationStatus::Failed->value,
            ])
            ->where(function ($query): void {
                $query
                    ->whereNull('lease_expires_at')
                    ->orWhere('lease_expires_at', '<=', now());
            })
            ->when(
                filled($this->option('operation-key')),
                fn ($query) => $query->where('operation_key', $this->option('operation-key')),
            )
            ->orderBy('id');

        $count = (clone $query)->count();

        if (! $this->option('apply')) {
            $this->table(
                ['operation_key', 'operation', 'status', 'proposed_action'],
                $query->get(['operation_key', 'operation', 'status'])->map(function (MediaMutationOperation $operation): array {
                    $operationType = MediaMutationOperationType::tryFrom(
                        (string) $operation->getRawOriginal('operation'),
                    );
                    $status = MediaMutationStatus::tryFrom(
                        (string) $operation->getRawOriginal('status'),
                    );

                    return [
                        $operation->operation_key,
                        $operationType?->value ?? (string) $operation->getRawOriginal('operation'),
                        $status?->value ?? (string) $operation->getRawOriginal('status'),
                        ! $operationType instanceof MediaMutationOperationType || ! $status instanceof MediaMutationStatus
                            ? 'manual review required: invalid journal enum'
                            : (in_array($status, [MediaMutationStatus::Committed, MediaMutationStatus::CleanupPending], true)
                                ? 'verify and complete cleanup'
                                : 'verify identity and recover or roll back'),
                    ];
                })->all(),
            );
            $this->components->info("Dry run: {$count} incomplete media mutation operations need review. Re-run with --apply to repair them.");

            return self::SUCCESS;
        }

        $results = [];
        $query->eachById(function (MediaMutationOperation $operation) use ($coordinator, &$results): void {
            $result = $coordinator->repair($operation);
            $results[$result] = ($results[$result] ?? 0) + 1;
        });

        $summary = collect($results)
            ->map(fn (int $resultCount, string $result): string => "{$result}={$resultCount}")
            ->implode(', ');
        $this->components->info('Media mutation repair complete'.($summary !== '' ? ": {$summary}" : '.'));

        return collect($results)->keys()->intersect([
            MediaMutationRepairResult::CleanupPending->value,
            MediaMutationRepairResult::LeaseActive->value,
            MediaMutationRepairResult::LeaseLost->value,
            MediaMutationRepairResult::ManualReviewRequired->value,
        ])->isEmpty()
            ? self::SUCCESS
            : self::FAILURE;
    }
}
