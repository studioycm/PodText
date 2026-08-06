<?php

namespace App\Models;

use App\Models\Concerns\HasFoldedSearchColumns;
use App\Models\Contracts\FoldsSearchColumns;
use App\Support\Media\MediaMutationLease;
use Database\Factories\MediaFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use LogicException;

class Media extends \Awcodes\Curator\Models\Media implements FoldsSearchColumns
{
    use HasFoldedSearchColumns;

    private bool $testFixtureCreation = false;

    /**
     * Merged with the vendor model's $casts property by the base model.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'audience_made_public_at' => 'datetime',
            'trusted_at' => 'datetime',
        ];
    }

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

    public function providerBinding(): HasOne
    {
        return $this->hasOne(MediaProviderBinding::class, 'provider_record_key')
            ->where('provider', 'curator');
    }

    public function mediaAsset(): HasOneThrough
    {
        return $this->hasOneThrough(
            MediaAsset::class,
            MediaProviderBinding::class,
            'provider_record_key',
            'id',
            'id',
            'media_asset_id',
        )->where('provider', 'curator');
    }

    /**
     * The vendor accessor resolves the disk unguarded, so a record whose disk
     * is not configured (a diagnosable storage_disk defect) would crash any
     * surface that arrays the model. A defective record must stay reviewable.
     */
    public function url(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => array_key_exists((string) $this->disk, config('filesystems.disks', []))
                ? static::resolveUrl($this->disk, $this->path, $this->visibility)
                : null,
        )->shouldCache();
    }

    public function fullPath(): Attribute
    {
        return Attribute::make(
            get: fn (): string => array_key_exists((string) $this->disk, config('filesystems.disks', []))
                ? Storage::disk($this->disk)->path($this->path)
                : (string) $this->path,
        );
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
            if (
                $media->isDirty('reference_key')
                && ! (
                    $media->getOriginal('reference_key') === null
                    && is_string($media->reference_key)
                    && app(MediaMutationLease::class)->allows($media)
                    && app(MediaMutationLease::class)->allowsReferenceKeyIssuance()
                )
            ) {
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

    /**
     * @return array<string, string>
     */
    public static function foldedSearchColumns(): array
    {
        return [
            'title' => 'title_search',
            'name' => 'name_search',
        ];
    }
}
