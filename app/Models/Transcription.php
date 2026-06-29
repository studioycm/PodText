<?php

namespace App\Models;

use App\Enums\PublicationStatus;
use Database\Factories\TranscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable([
    'reference_key',
    'content_item_id',
    'author_id',
    'title',
    'language_code',
    'transcript_markdown',
    'status',
    'published_at',
    'word_count',
    'speakers',
    'parsed_segments',
])]
class Transcription extends Model
{
    /** @use HasFactory<TranscriptionFactory> */
    use HasFactory;

    protected $attributes = [
        'language_code' => 'he',
        'status' => 'draft',
    ];

    public function contentItem(): BelongsTo
    {
        return $this->belongsTo(ContentItem::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(Author::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('status', PublicationStatus::Published)
            ->whereNotNull('transcript_markdown')
            ->where('transcript_markdown', '!=', '')
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('published_at')
                    ->orWhere('published_at', '<=', now());
            });
    }

    public function isPublished(): bool
    {
        return $this->status === PublicationStatus::Published
            && filled($this->transcript_markdown)
            && ($this->published_at === null || $this->published_at->lte(now()));
    }

    protected static function booted(): void
    {
        static::creating(function (Transcription $transcription): void {
            $transcription->reference_key ??= (string) Str::ulid();
            $transcription->language_code ??= 'he';
            $transcription->status ??= PublicationStatus::Draft;
        });

        static::updating(function (Transcription $transcription): void {
            if ($transcription->isDirty('reference_key')) {
                $transcription->reference_key = $transcription->getOriginal('reference_key');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'parsed_segments' => 'array',
            'published_at' => 'datetime',
            'speakers' => 'array',
            'status' => PublicationStatus::class,
            'word_count' => 'integer',
        ];
    }
}
