<?php

namespace App\Models;

use App\Models\Concerns\HasFoldedSearchColumns;
use App\Models\Contracts\FoldsSearchColumns;
use App\Support\Search\HebrewSearchFold;
use App\Support\Slugs\HebrewSlugger;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Tags\Tag;

#[Fillable([
    'name',
    'slug',
    'type',
    'order_column',
    'is_enabled',
    'enabled_at',
    'enabled_by_id',
    'created_by_id',
    'moderation_state',
])]
class ContentTag extends Tag implements FoldsSearchColumns
{
    use HasFoldedSearchColumns;

    protected $table = 'tags';

    protected $attributes = [
        'is_enabled' => false,
    ];

    /** @return BelongsTo<User, $this> */
    public function enabledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enabled_by_id');
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function scopeContent(Builder $query): Builder
    {
        return $query->where('type', 'content');
    }

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('is_enabled', true);
    }

    public function enable(?User $user = null): static
    {
        $this->forceFill([
            'is_enabled' => true,
            'enabled_at' => now(),
            'enabled_by_id' => $user?->getKey(),
        ])->save();

        return $this;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'enabled_at' => 'datetime',
            'enabled_by_id' => 'integer',
            'created_by_id' => 'integer',
            'is_enabled' => 'boolean',
        ];
    }

    protected function generateSlug(string $locale): string
    {
        return HebrewSlugger::unique(
            $this->getTranslation('name', $locale, false),
            fn (string $slug): bool => static::query()
                ->where('type', $this->type)
                ->where("slug->{$locale}", $slug)
                ->when($this->exists, fn (Builder $query): Builder => $query->whereKeyNot($this))
                ->exists(),
        );
    }

    /**
     * @return array<string, string>
     */
    public static function foldedSearchColumns(): array
    {
        return [
            'name' => 'name_search',
        ];
    }

    /**
     * `name` is Spatie translatable JSON. The shadow flattens every locale into
     * one newline-joined string rather than mirroring the JSON, because MySQL
     * normalizes a JSON column on insert while SQLite keeps PHP's bytes — a
     * JSON shadow would have to be matched against two encodings, a flat one
     * against neither. Newline is the joiner because no search term carries it.
     */
    protected function foldAttributeForSearch(mixed $stored): ?string
    {
        if ($stored === null) {
            return null;
        }

        $translations = json_decode((string) $stored, true);

        if (! is_array($translations)) {
            return HebrewSearchFold::fold((string) $stored);
        }

        return collect($translations)
            ->map(fn (mixed $translation): string => HebrewSearchFold::fold(
                is_scalar($translation) ? (string) $translation : '',
            ))
            ->filter()
            ->unique()
            ->implode("\n");
    }
}
