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

/**
 * Nullability corrections for the parent's docblock.
 *
 * `Awcodes\Curator\Models\Media` annotates these four as non-nullable while the
 * package's OWN migration stub creates every one of them `->nullable()` — and our
 * `create_curator_table` migration, generated from that stub, matches the stub
 * rather than the annotation (verified: `Null = YES` for all four).
 *
 * They are genuinely null in normal use: a sanitized SVG has no raster
 * dimensions, so `width` and `height` are null for a perfectly valid upload.
 *
 * Without these overrides larastan believes the parent and reports correct
 * null-handling as a defect — `expect($media->width)->toBeNull()` came back as
 * `pest.expectation.impossible` in a test that passes. That is the dangerous
 * shape: the analyser points at correct code and invites you to break it.
 *
 * Reported upstream: awcodes/filament-curator#721. Remove these when a release
 * carries the fix.
 *
 * @property string|null $directory
 * @property int|null $width
 * @property int|null $height
 * @property int|null $size
 */
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

    /**
     * Deliberately a property, not #[Fillable]: the attribute MERGES into the
     * inherited list (GuardsAttributes::initializeGuardsAttributes), so it
     * cannot narrow Curator's 17-column parent $fillable. This override is
     * that narrowing — path/disk/directory must stay out of mass assignment.
     */
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

    /** @return HasMany<MediaAttachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(MediaAttachment::class);
    }

    /** @return HasMany<MediaMutationOperation, $this> */
    public function mutationOperations(): HasMany
    {
        return $this->hasMany(MediaMutationOperation::class);
    }

    /** @return HasOne<MediaProviderBinding, $this> */
    public function providerBinding(): HasOne
    {
        return $this->hasOne(MediaProviderBinding::class, 'provider_record_key')
            ->where('provider', 'curator');
    }

    /** @return HasOneThrough<MediaAsset, MediaProviderBinding, $this> */
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
