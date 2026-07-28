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
        private readonly CuratorImageUploadPolicy $uploadPolicy,
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
    public function applySearchTerm(Builder $query, string $searchTerm, string $scope = 'all'): Builder
    {
        $scope = in_array($scope, ['all', 'title', 'owner', 'filename'], true) ? $scope : 'all';
        $title = $query->getModel()->qualifyColumn('title');
        $name = $query->getModel()->qualifyColumn('name');

        return $query->where(function (Builder $query) use ($title, $name, $searchTerm, $scope): void {
            if (in_array($scope, ['all', 'title'], true)) {
                $query->orWhere($title, 'like', "%{$searchTerm}%");
            }

            if (in_array($scope, ['all', 'filename'], true)) {
                $query->orWhere($name, 'like', "%{$searchTerm}%");
            }

            if (in_array($scope, ['all', 'owner'], true)) {
                $this->applyOwnerTitleSearch($query, $searchTerm);
            }
        });
    }

    /**
     * Matches media whose owning podcast or episode title matches the term,
     * through attachments and the legacy path columns.
     *
     * @param  Builder<Media>  $query
     */
    public function applyOwnerTitleSearch(Builder $query, string $searchTerm): Builder
    {
        $mediaTable = $query->getModel()->getTable();
        $mediaKey = $query->getModel()->getQualifiedKeyName();

        return $query
            ->orWhereExists(function ($sub) use ($mediaKey, $searchTerm): void {
                $sub->from('media_attachments')
                    ->join('content_groups', 'content_groups.id', '=', 'media_attachments.attachable_id')
                    ->whereColumn('media_attachments.media_id', $mediaKey)
                    ->whereIn('media_attachments.attachable_type', ['content_group', ContentGroup::class])
                    ->where('content_groups.title', 'like', "%{$searchTerm}%");
            })
            ->orWhereExists(function ($sub) use ($mediaKey, $searchTerm): void {
                $sub->from('media_attachments')
                    ->join('content_items', 'content_items.id', '=', 'media_attachments.attachable_id')
                    ->whereColumn('media_attachments.media_id', $mediaKey)
                    ->whereIn('media_attachments.attachable_type', ['content_item', ContentItem::class])
                    ->where('content_items.title', 'like', "%{$searchTerm}%");
            })
            ->orWhereExists(function ($sub) use ($mediaTable, $searchTerm): void {
                $sub->from('content_groups')
                    ->whereColumn('content_groups.cover_path', "{$mediaTable}.path")
                    ->where('content_groups.title', 'like', "%{$searchTerm}%");
            })
            ->orWhereExists(function ($sub) use ($mediaTable, $searchTerm): void {
                $sub->from('content_items')
                    ->whereColumn('content_items.image_path', "{$mediaTable}.path")
                    ->where('content_items.title', 'like', "%{$searchTerm}%");
            });
    }

    /** @param Builder<Media> $query */
    public function applySearch(Builder $query, string $search): Builder
    {
        foreach ($this->extractSearchTerms($search) as $searchTerm) {
            $query->where(
                fn (Builder $query): Builder => $this->applySearchTerm(
                    $query,
                    $searchTerm,
                ),
            );
        }

        return $query;
    }

    /**
     * @param  array<string, int|string|null>  $context
     */
    public function nextIssue(Media $current, array $context): ?Media
    {
        $task = is_string($context['task'] ?? null)
            ? MediaLibraryTask::tryFrom($context['task'])
            : null;
        $mime = $context['mime'] ?? null;
        $reason = $context['reason'] ?? null;
        $search = $context['search'] ?? null;
        $sort = $context['sort'] ?? null;

        if (
            ! $task instanceof MediaLibraryTask
            || ($mime !== null && (
                ! is_string($mime)
                || ! in_array($mime, $this->uploadPolicy->globalMimeTypes(), true)
            ))
            || ($reason !== null && (
                ! is_string($reason)
                || ! MediaDiagnosticReason::tryFrom($reason) instanceof MediaDiagnosticReason
            ))
            || ($search !== null && ! is_string($search))
            || ($sort !== null && ! in_array($sort, ['created_at:asc', 'created_at:desc'], true))
        ) {
            return null;
        }

        $query = $this->apply($this->scope->inventoryQuery(), $task);

        if (is_string($mime)) {
            $query->where($query->getModel()->qualifyColumn('type'), $mime);
        }

        if (is_string($reason)) {
            $this->applyReason($query, $reason);
        } elseif ($task !== MediaLibraryTask::NeedsAttention) {
            $this->diagnostics->applyReasonFilter($query);
        }

        if (is_string($search) && $search !== '') {
            $this->applySearch($query, $search);
        }

        $direction = $sort === 'created_at:asc' ? 'asc' : 'desc';
        $createdAt = $query->getModel()->qualifyColumn('created_at');
        $key = $query->getModel()->getQualifiedKeyName();
        $currentCreatedAt = $current->created_at;
        $currentKey = (int) $current->getKey();

        $query->where(function (Builder $query) use (
            $createdAt,
            $currentCreatedAt,
            $currentKey,
            $direction,
            $key,
        ): void {
            if ($currentCreatedAt === null) {
                if ($direction === 'desc') {
                    $query
                        ->whereNull($createdAt)
                        ->where($key, '<', $currentKey);

                    return;
                }

                $query
                    ->where(function (Builder $query) use ($createdAt, $currentKey, $key): void {
                        $query
                            ->whereNull($createdAt)
                            ->where($key, '>', $currentKey);
                    })
                    ->orWhereNotNull($createdAt);

                return;
            }

            $operator = $direction === 'asc' ? '>' : '<';
            $query
                ->where($createdAt, $operator, $currentCreatedAt)
                ->orWhere(function (Builder $query) use (
                    $createdAt,
                    $currentCreatedAt,
                    $currentKey,
                    $key,
                    $operator,
                ): void {
                    $query
                        ->where($createdAt, $currentCreatedAt)
                        ->where($key, $operator, $currentKey);
                });

            if ($direction === 'desc') {
                $query->orWhereNull($createdAt);
            }
        });

        $next = $query
            ->orderBy($createdAt, $direction)
            ->orderBy($key, $direction)
            ->first();

        return $next instanceof Media ? $next : null;
    }

    /** @return array<int, string> */
    private function extractSearchTerms(string $search): array
    {
        $normalizedSearch = preg_replace(
            '/(\s|\x{3164}|\x{1160})+/u',
            ' ',
            $search,
        ) ?? $search;

        return array_values(array_filter(
            str_getcsv($normalizedSearch, separator: ' ', escape: '\\'),
            fn (string $word): bool => filled($word),
        ));
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
