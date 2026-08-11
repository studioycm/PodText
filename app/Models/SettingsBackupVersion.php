<?php

namespace App\Models;

use App\Enums\SettingsBackupSource;
use App\Models\Concerns\HasFoldedSearchColumns;
use App\Models\Contracts\FoldsSearchColumns;
use App\Support\SettingsLifecycle\PublicSettingsPackage;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'scope',
    'label',
    'payload_json',
    'checksum',
    'payload_hash',
    'source',
    'created_by_user_id',
    'import_report',
    'full_snapshot_source_backup_id',
])]
class SettingsBackupVersion extends Model implements FoldsSearchColumns
{
    use HasFactory;
    use HasFoldedSearchColumns;

    protected function casts(): array
    {
        return [
            'import_report' => 'array',
            'source' => SettingsBackupSource::class,
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** @return HasMany<SettingsBackupSnapshot, $this> */
    public function snapshots(): HasMany
    {
        return $this->hasMany(SettingsBackupSnapshot::class, 'backup_id');
    }

    /** @return BelongsTo<SettingsBackupVersion, $this> */
    public function fullSnapshotSourceBackup(): BelongsTo
    {
        return $this->belongsTo(SettingsBackupVersion::class, 'full_snapshot_source_backup_id');
    }

    /**
     * The rows the gallery and zip present: this backup's own rows, plus the
     * source backup's full set when this backup borrowed one (full-set dedup)
     * and owns no full rows itself.
     *
     * @return Collection<int, SettingsBackupSnapshot>
     */
    public function effectiveSnapshots(): Collection
    {
        $own = $this->snapshots()->get();
        $borrowed = new Collection;

        if ($this->full_snapshot_source_backup_id !== null
            && $own->where('kind', SettingsBackupSnapshot::KIND_FULL)->isEmpty()) {
            $borrowed = $this->fullSnapshotSourceBackup?->snapshots()
                ->where('kind', SettingsBackupSnapshot::KIND_FULL)
                ->get() ?? new Collection;
        }

        return $own->merge($borrowed)
            ->sortBy([
                ['screen_key', 'asc'],
                ['theme', 'asc'],
                ['kind', 'asc'],
                ['format', 'asc'],
            ])
            ->values();
    }

    public function homeThumbnailSnapshot(): ?SettingsBackupSnapshot
    {
        $matchesHomeThumbnail = fn (SettingsBackupSnapshot $snapshot): bool => $snapshot->screen_key === 'home'
            && $snapshot->kind === SettingsBackupSnapshot::KIND_THUMBNAIL
            && $snapshot->format === SettingsBackupSnapshot::FORMAT_PNG
            && $snapshot->status === SettingsBackupSnapshot::STATUS_DONE;

        if ($this->relationLoaded('snapshots')) {
            return $this->snapshots
                ->first($matchesHomeThumbnail);
        }

        return $this->snapshots()
            ->where('screen_key', 'home')
            ->where('kind', SettingsBackupSnapshot::KIND_THUMBNAIL)
            ->where('format', SettingsBackupSnapshot::FORMAT_PNG)
            ->where('status', SettingsBackupSnapshot::STATUS_DONE)
            ->latest('id')
            ->first();
    }

    public function package(): PublicSettingsPackage
    {
        return PublicSettingsPackage::fromArray(json_decode($this->payload_json, true, flags: JSON_THROW_ON_ERROR));
    }

    public function shortPayloadHash(): string
    {
        return Str::substr($this->payload_hash, 0, 12);
    }

    public function packageSize(): int
    {
        return strlen($this->payload_json);
    }

    public function downloadFilename(): string
    {
        $timestamp = $this->created_at?->forDisplay('Ymd-His') ?? (string) $this->getKey();

        return "public-content-settings-backup-{$timestamp}.json";
    }

    /**
     * @return array<string, string>
     */
    public static function foldedSearchColumns(): array
    {
        return [
            'label' => 'label_search',
        ];
    }
}
