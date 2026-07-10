<?php

namespace App\Models;

use App\Support\Slugs\HebrewSlugger;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

#[Fillable([
    'parent_id',
    'name',
    'slug',
    'description_markdown',
    'is_visible',
    'sort_order',
])]
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    protected $attributes = [
        'is_visible' => true,
        'sort_order' => 0,
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function contentGroups(): BelongsToMany
    {
        return $this->belongsToMany(ContentGroup::class);
    }

    public function contentItems(): BelongsToMany
    {
        return $this->belongsToMany(ContentItem::class);
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_visible', true);
    }

    public function descendantIds(bool $includeSelf = true): Collection
    {
        $ids = $includeSelf ? collect([$this->getKey()]) : collect();
        $children = static::query()
            ->where('parent_id', $this->getKey())
            ->get(['id', 'parent_id']);

        foreach ($children as $child) {
            $ids = $ids->merge($child->descendantIds());
        }

        return $ids->unique()->values();
    }

    protected static function booted(): void
    {
        static::creating(function (Category $category): void {
            $category->slug = static::uniqueSlug($category->slug ?: $category->name);
            $category->is_visible ??= true;
            $category->sort_order ??= 0;
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_visible' => 'boolean',
            'parent_id' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    private static function uniqueSlug(string $source): string
    {
        return HebrewSlugger::unique(
            $source,
            fn (string $slug): bool => static::query()->where('slug', $slug)->exists(),
        );
    }
}
