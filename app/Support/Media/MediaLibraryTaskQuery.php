<?php

namespace App\Support\Media;

use App\Enums\MediaDiagnosticReason;
use App\Enums\MediaLibraryTask;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\Media;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class MediaLibraryTaskQuery
{
    /** @var array{all: int, no_direct_attachment: int}|null */
    private ?array $counts = null;

    private ?CarbonImmutable $requestNow = null;

    public function __construct(
        private readonly MediaRecordScope $scope,
        private readonly MediaReferenceFinder $references,
        private readonly MediaInventoryDiagnostics $diagnostics,
    ) {}

    /** @param Builder<Media> $query */
    public function apply(Builder $query, MediaLibraryTask $task): Builder
    {
        return match ($task) {
            MediaLibraryTask::All => $query,
            MediaLibraryTask::InUse => $this->applyInUse($query),
            MediaLibraryTask::NoDirectAttachment => $query->whereDoesntHave('attachments'),
            MediaLibraryTask::NeedsAttention => $this->diagnostics->applyReasonFilter($query),
            MediaLibraryTask::Recent => $this->applyRecent($query),
        };
    }

    /**
     * @param  Builder<Media>  $query
     */
    public function applyReason(
        Builder $query,
        MediaDiagnosticReason|string|null $reason,
    ): Builder {
        if ($reason === null || $reason === '') {
            return $query;
        }

        if (is_string($reason)) {
            $reason = MediaDiagnosticReason::tryFrom($reason);

            if (! $reason instanceof MediaDiagnosticReason) {
                return $query->whereRaw('1 = 0');
            }
        }

        return $this->diagnostics->applyReasonFilter($query, $reason);
    }

    /** @return array{all: int, no_direct_attachment: int} */
    public function counts(): array
    {
        return $this->counts ??= [
            MediaLibraryTask::All->value => $this->scope->inventoryQuery()->count(),
            MediaLibraryTask::NoDirectAttachment->value => $this->scope
                ->inventoryQuery()
                ->whereDoesntHave('attachments')
                ->count(),
        ];
    }

    public function forgetCounts(): void
    {
        $this->counts = null;
    }

    /** @param Builder<Media> $query */
    private function applyInUse(Builder $query): Builder
    {
        $table = $query->getModel()->getTable();
        $settings = $this->references->settingsIdentityCandidates();

        return $query->where(function (Builder $query) use ($settings, $table): void {
            $query->whereHas('attachments');

            if ($settings['paths'] !== [] || $settings['reference_keys'] !== []) {
                $query->orWhere(function (Builder $query) use ($settings, $table): void {
                    if ($settings['paths'] !== []) {
                        $query->whereIn("{$table}.path", $settings['paths']);
                    }

                    if ($settings['reference_keys'] !== []) {
                        $method = $settings['paths'] === [] ? 'whereIn' : 'orWhereIn';
                        $query->{$method}(
                            DB::raw("LOWER({$table}.reference_key)"),
                            $settings['reference_keys'],
                        );
                    }
                });
            }

            $query->orWhere(function (Builder $query) use ($table): void {
                $query
                    ->where("{$table}.disk", 'public')
                    ->where(function (Builder $query) use ($table): void {
                        $query
                            ->whereIn(
                                "{$table}.path",
                                ContentGroup::query()
                                    ->select('cover_path')
                                    ->whereNotNull('cover_path'),
                            )
                            ->orWhereIn(
                                "{$table}.path",
                                ContentItem::query()
                                    ->select('image_path')
                                    ->whereNotNull('image_path'),
                            );
                    });
            });
        });
    }

    /** @param Builder<Media> $query */
    private function applyRecent(Builder $query): Builder
    {
        $now = $this->requestNow ??= now()->toImmutable();
        $createdAt = $query->getModel()->qualifyColumn('created_at');

        return $query->whereBetween($createdAt, [$now->subDays(30), $now]);
    }
}
