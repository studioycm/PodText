<?php

namespace App\Models;

use App\Support\Media\MediaMutationLease;
use Database\Factories\MediaFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LogicException;

class Media extends \Awcodes\Curator\Models\Media
{
    private bool $testFixtureCreation = false;

    protected $fillable = [
        'alt',
        'title',
        'description',
        'caption',
    ];

    /**
     * Curator's inherited observer performs destructive move/delete operations.
     * All filesystem mutations are owned by MediaFilesystemMutationCoordinator.
     *
     * @return array<int, class-string>
     */
    public static function resolveObserveAttributes(): array
    {
        return [];
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(MediaAttachment::class);
    }

    public function mutationOperations(): HasMany
    {
        return $this->hasMany(MediaMutationOperation::class);
    }

    protected static function booted(): void
    {
        static::creating(function (Media $media): void {
            if (
                ! app(MediaMutationLease::class)->allowsCreation()
                && ! $media->testFixtureCreation
            ) {
                throw new LogicException('Media records may only be created by the validated mutation coordinator.');
            }

            $media->reference_key ??= (string) Str::ulid();
        });

        static::updating(function (Media $media): void {
            if ($media->isDirty('reference_key')) {
                throw new LogicException('A media reference key is immutable after creation.');
            }
        });
    }

    public function permitTestFixtureCreation(): self
    {
        if (! app()->runningUnitTests()) {
            throw new LogicException('The media fixture creation bypass is available only during tests.');
        }

        $this->testFixtureCreation = true;

        return $this;
    }

    protected static function newFactory(): Factory
    {
        return MediaFactory::new();
    }
}
