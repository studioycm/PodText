<?php

namespace App\Models;

use Database\Factories\AuthorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

#[Fillable(['reference_key', 'name', 'slug', 'bio_markdown'])]
class Author extends Model
{
    /** @use HasFactory<AuthorFactory> */
    use HasFactory;

    public function contentItems(): BelongsToMany
    {
        return $this->belongsToMany(ContentItem::class);
    }

    protected static function booted(): void
    {
        static::creating(function (Author $author): void {
            $author->reference_key ??= (string) Str::ulid();
            $author->slug = static::uniqueSlug($author->slug ?: $author->name);
        });

        static::updating(function (Author $author): void {
            if ($author->isDirty('reference_key')) {
                $author->reference_key = $author->getOriginal('reference_key');
            }
        });
    }

    private static function uniqueSlug(string $source): string
    {
        $baseSlug = Str::slug($source) ?: Str::lower((string) Str::ulid());
        $slug = $baseSlug;
        $suffix = 2;

        while (static::query()->where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
