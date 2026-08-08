<?php

namespace App\Models;

use App\Enums\MediaAttachmentRole;
use App\Enums\PublicationStatus;
use App\Models\Concerns\HasFoldedSearchColumns;
use App\Models\Concerns\InteractsWithPublicationDate;
use App\Models\Contracts\FoldsSearchColumns;
use App\Observers\ContentGroupObserver;
use App\Support\Slugs\HebrewSlugger;
use Carbon\CarbonInterface;
use Database\Factories\ContentGroupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

#[Fillable([
    'reference_key',
    'title',
    'slug',
    'group_type_label_singular',
    'group_type_label_plural',
    'default_item_type_label_singular',
    'default_item_type_label_plural',
    'description_markdown',
    'cover_alt_text',
    'original_language_code',
    'status',
    'published_at',
    'homepage_order',
])]
#[ObservedBy([ContentGroupObserver::class])]
class ContentGroup extends Model implements FoldsSearchColumns
{
    /** @use HasFactory<ContentGroupFactory> */
    use HasFactory;

    use HasFoldedSearchColumns;
    use InteractsWithPublicationDate;

    protected $attributes = [
        'group_type_label_singular' => 'Podcast',
        'group_type_label_plural' => 'Podcasts',
        'default_item_type_label_singular' => 'Episode',
        'default_item_type_label_plural' => 'Episodes',
        'original_language_code' => 'he',
        'status' => 'draft',
    ];

    /** @return HasMany<ContentItem, $this> */
    public function contentItems(): HasMany
    {
        return $this->hasMany(ContentItem::class);
    }

    /** @return HasMany<MediaAttachment, $this> */
    public function mediaAttachments(): HasMany
    {
        return $this->hasMany(MediaAttachment::class, 'attachable_id')
            ->where('attachable_type', 'content_group')
            ->orderBy('role')
            ->orderBy('position')
            ->orderBy('id');
    }

    /** @return HasOne<MediaAttachment, $this> */
    public function coverMediaAttachment(): HasOne
    {
        return $this->hasOne(MediaAttachment::class, 'attachable_id')
            ->where('attachable_type', 'content_group')
            ->where('role', MediaAttachmentRole::Cover->value);
    }

    /** @return BelongsToMany<Category, $this> */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class);
    }

    #[Scope]
    protected function published(Builder $query): Builder
    {
        return $query->releasedBy(now());
    }

    /**
     * The same release contract as `published()`, asked about a different
     * moment — used by the admin's scheduled/blocked verdicts, which need to
     * know whether a podcast will be out by an episode's air time.
     * A string `$moment` names a column to compare against instead of a value.
     */
    #[Scope]
    protected function releasedBy(Builder $query, CarbonInterface|string $moment): Builder
    {
        return $query
            ->where('status', PublicationStatus::Published)
            ->where(function (Builder $query) use ($moment): void {
                $query->whereNull($query->qualifyColumn('published_at'));

                is_string($moment)
                    ? $query->orWhereColumn($query->qualifyColumn('published_at'), '<=', $moment)
                    : $query->orWhere($query->qualifyColumn('published_at'), '<=', $moment);
            });
    }

    #[Scope]
    protected function orderedForHomepage(Builder $query): Builder
    {
        return $query
            ->orderByRaw('homepage_order is null')
            ->orderBy('homepage_order')
            ->orderByDesc('published_at')
            ->orderByDesc('id');
    }

    protected static function booted(): void
    {
        static::creating(function (ContentGroup $contentGroup): void {
            $contentGroup->reference_key ??= (string) Str::ulid();
            $contentGroup->slug = static::uniqueSlug($contentGroup->slug ?: $contentGroup->title);
            $contentGroup->group_type_label_singular ??= 'Podcast';
            $contentGroup->group_type_label_plural ??= 'Podcasts';
            $contentGroup->default_item_type_label_singular ??= 'Episode';
            $contentGroup->default_item_type_label_plural ??= 'Episodes';
            $contentGroup->original_language_code ??= 'he';
            $contentGroup->status ??= PublicationStatus::Draft;
        });

        static::updating(function (ContentGroup $contentGroup): void {
            if ($contentGroup->isDirty('reference_key')) {
                $contentGroup->reference_key = $contentGroup->getOriginal('reference_key');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'homepage_order' => 'integer',
            'published_at' => 'datetime',
            'status' => PublicationStatus::class,
        ];
    }

    private static function uniqueSlug(string $source): string
    {
        return HebrewSlugger::unique(
            $source,
            fn (string $slug): bool => static::query()->where('slug', $slug)->exists(),
        );
    }

    /**
     * @return array<string, string>
     */
    public static function foldedSearchColumns(): array
    {
        return [
            'title' => 'title_search',
            'description_markdown' => 'description_markdown_search',
            'group_type_label_singular' => 'group_type_label_singular_search',
            'default_item_type_label_singular' => 'default_item_type_label_singular_search',
        ];
    }
}
