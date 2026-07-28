<?php

namespace App\Support\Media;

use App\Enums\MediaAttachmentRole;
use App\Enums\MediaDiagnosticReason;
use App\Filament\Resources\ContentGroups\ContentGroupResource;
use App\Filament\Resources\ContentItems\ContentItemResource;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\Media;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Number;

class MediaIssueReviewPresenter
{
    public function __construct(
        private readonly MediaInventoryDiagnostics $diagnostics,
        private readonly PublicMediaDelivery $publicDelivery,
        private readonly MediaReferenceFinder $references,
        private readonly CuratorImageUploadPolicy $uploadPolicy,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function details(Media $media): array
    {
        $reasons = $this->diagnostics->reasons($media);
        $originalFilename = data_get($media->exif, 'original_filename');
        $originalFilename = is_string($originalFilename) && filled($originalFilename)
            ? $originalFilename
            : null;
        $width = is_numeric($media->width) && (int) $media->width > 0
            ? (int) $media->width
            : null;
        $height = is_numeric($media->height) && (int) $media->height > 0
            ? (int) $media->height
            : null;
        $diskIsConfigured = array_key_exists(
            (string) $media->disk,
            config('filesystems.disks', []),
        );
        $fileExists = $diskIsConfigured && $this->publicDelivery->fileExists($media);
        $previewUrl = Gate::allows('view', $media)
            ? $this->diagnostics->previewUrl($media)
            : null;

        return [
            'id' => (int) $media->getKey(),
            'identity' => $this->displayIdentity($media, $originalFilename),
            'original_filename' => $originalFilename,
            'stored_filename' => filled($media->path) ? basename((string) $media->path) : null,
            'reference_key' => filled($media->reference_key) ? (string) $media->reference_key : null,
            'disk' => filled($media->disk) ? (string) $media->disk : null,
            'directory' => filled($media->directory) ? (string) $media->directory : null,
            'visibility' => filled($media->visibility) ? (string) $media->visibility : null,
            'path' => filled($media->path) ? (string) $media->path : null,
            'mime' => filled($media->type) ? (string) $media->type : null,
            'extension' => filled($media->ext) ? (string) $media->ext : null,
            'dimensions' => $width !== null && $height !== null
                ? "{$width} × {$height}"
                : null,
            'file_size' => is_numeric($media->size) && (int) $media->size >= 0
                ? Number::fileSize((int) $media->size)
                : null,
            'preview_url' => $previewUrl,
            'view_url' => $previewUrl,
            'download_url' => $fileExists && Gate::allows('download', $media)
                ? route('admin.media-files.download', ['media' => $media->getKey()])
                : null,
            'reasons' => $reasons,
            'primary_issue' => isset($reasons[0])
                ? __("admin.media_library.repair_{$reasons[0]}")
                : null,
            'additional_issue_count' => max(count($reasons) - 1, 0),
            'needs_attention' => $reasons !== [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function review(Media $media): array
    {
        $details = $this->details($media);
        [$owners, $unresolvedAttachmentCount] = $this->owners(
            $media,
            $details['reasons'],
        );

        $issues = collect($details['reasons'])
            ->map(fn (string $reason): array => [
                'value' => $reason,
                'label' => __("admin.media_library.repair_{$reason}"),
                'cause' => __("admin.media_issue_review.reasons.{$reason}.cause"),
                'consequence' => __("admin.media_issue_review.reasons.{$reason}.consequence"),
                'evidence_limit' => __("admin.media_issue_review.reasons.{$reason}.evidence_limit"),
                'facts' => $this->factsForReason($reason, $details),
                'resolution' => $this->resolutionForReason($reason, $media),
            ])
            ->all();

        return [
            ...$details,
            'issues' => $issues,
            'owners' => $owners,
            'unresolved_attachment_count' => $unresolvedAttachmentCount,
            'non_attachment_references' => $this->references
                ->nonAttachmentReferencesForMedia($media),
            'has_current_media_repair_authority' => collect($issues)->contains(
                fn (array $issue): bool => $issue['resolution']['kind'] === 'action',
            ),
            'issues_have_resolution_content' => collect($issues)->contains(
                fn (array $issue): bool => $issue['resolution']['kind'] !== 'none',
            ),
        ];
    }

    /**
     * The truthful per-reason resolution state: an available action, a
     * reasoned block, a separate-phase fact, or nothing yet.
     *
     * @return array{kind: string, label?: string, description?: string, reason?: string}
     */
    private function resolutionForReason(string $reason, Media $media): array
    {
        if ($reason === MediaDiagnosticReason::UnsanitizedSvg->value) {
            $response = Gate::inspect('repair', $media);

            if ($response->allowed()) {
                return [
                    'kind' => 'action',
                    'label' => __('admin.media_issue_review.sanitize.action'),
                    'description' => __('admin.media_issue_review.sanitize.consequence'),
                ];
            }

            return filled($response->message())
                ? ['kind' => 'blocked', 'reason' => (string) $response->message()]
                : ['kind' => 'none'];
        }

        if ($reason === MediaDiagnosticReason::Metadata->value && ! $this->pathIsPurposeManaged($media)) {
            return [
                'kind' => 'separate_phase',
                'description' => __('admin.media_issue_review.resolution.metadata_root'),
            ];
        }

        return ['kind' => 'none'];
    }

    private function pathIsPurposeManaged(Media $media): bool
    {
        try {
            $this->uploadPolicy->purposeForPath((string) $media->path);
        } catch (\InvalidArgumentException) {
            return false;
        }

        return true;
    }

    private function displayIdentity(Media $media, ?string $originalFilename): string
    {
        foreach ([
            $media->title,
            $originalFilename,
            filled($media->path) ? basename((string) $media->path) : null,
            $media->name,
        ] as $candidate) {
            if (is_string($candidate) && filled($candidate)) {
                return $candidate;
            }
        }

        return __('admin.media_issue_review.details.fallback_identity', [
            'id' => $media->getKey(),
        ]);
    }

    /**
     * @param  array<int, string>  $reasons
     * @return array{array<int, array<string, int|string|null>>, int}
     */
    private function owners(Media $media, array $reasons): array
    {
        $attachments = DB::table('media_attachments')
            ->where('media_id', $media->getKey())
            ->orderBy('id')
            ->get(['attachable_type', 'attachable_id', 'role']);
        $supported = $attachments->filter(
            fn (object $attachment): bool => $this->supportedOwnerType($attachment) !== null,
        );
        $groups = $this->ownersOfType(
            $supported,
            'content_group',
            ContentGroup::query(),
        );
        $items = $this->ownersOfType(
            $supported,
            'content_item',
            ContentItem::query(),
        );
        $canOfferOwnerRoute = in_array(
            MediaDiagnosticReason::MissingFile->value,
            $reasons,
            true,
        );
        $owners = [];
        $unresolved = 0;

        foreach ($attachments as $attachment) {
            $type = $this->supportedOwnerType($attachment);

            if ($type === null) {
                $unresolved++;

                continue;
            }

            $owner = $type === 'content_group'
                ? $groups->get((int) $attachment->attachable_id)
                : $items->get((int) $attachment->attachable_id);

            if (! $owner instanceof ContentGroup && ! $owner instanceof ContentItem) {
                $unresolved++;

                continue;
            }

            $actionUrl = null;

            if ($canOfferOwnerRoute) {
                if ($owner instanceof ContentGroup && ContentGroupResource::canEdit($owner)) {
                    $actionUrl = ContentGroupResource::getUrl('edit', ['record' => $owner]);
                }

                if ($owner instanceof ContentItem && ContentItemResource::canEdit($owner)) {
                    $actionUrl = ContentItemResource::getUrl('workspace', ['record' => $owner]);
                }
            }

            $owners[] = [
                'type' => $type,
                'role' => (string) $attachment->role,
                'id' => (int) $owner->getKey(),
                'title' => (string) $owner->title,
                'label' => __("admin.media_issue_review.owners.{$type}", [
                    'title' => $owner->title,
                ]),
                'action_label' => __("admin.media_issue_review.owners.open_{$type}"),
                'action_url' => $actionUrl,
            ];
        }

        return [$owners, $unresolved];
    }

    /**
     * @param  Collection<int, object>  $attachments
     * @param  Builder<ContentGroup|ContentItem>  $query
     * @return Collection<int, ContentGroup|ContentItem>
     */
    private function ownersOfType(
        Collection $attachments,
        string $type,
        Builder $query,
    ): Collection {
        $ids = $attachments
            ->filter(fn (object $attachment): bool => $attachment->attachable_type === $type)
            ->pluck('attachable_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            return collect();
        }

        return $query
            ->whereKey($ids)
            ->get(['id', 'title'])
            ->keyBy(fn (ContentGroup|ContentItem $owner): int => (int) $owner->getKey());
    }

    private function supportedOwnerType(object $attachment): ?string
    {
        return match ([
            (string) $attachment->attachable_type,
            (string) $attachment->role,
        ]) {
            ['content_group', MediaAttachmentRole::Cover->value] => 'content_group',
            ['content_item', MediaAttachmentRole::PrimaryImage->value] => 'content_item',
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $details
     * @return array<int, array{label: string, value: string}>
     */
    private function factsForReason(string $reason, array $details): array
    {
        $keys = match ($reason) {
            MediaDiagnosticReason::PortableIdentity->value => ['reference_key'],
            MediaDiagnosticReason::StorageDisk->value => ['disk', 'path'],
            MediaDiagnosticReason::MissingFile->value => ['disk', 'path'],
            MediaDiagnosticReason::AudienceDenied->value => ['disk', 'visibility'],
            MediaDiagnosticReason::UnsanitizedSvg->value => ['mime', 'extension', 'path'],
            MediaDiagnosticReason::Metadata->value => [
                'stored_filename',
                'mime',
                'extension',
                'dimensions',
                'file_size',
                'path',
            ],
            default => ['path'],
        };

        return collect($keys)
            ->map(fn (string $key): array => [
                'label' => __("admin.media_issue_review.facts.{$key}"),
                'value' => filled($details[$key] ?? null)
                    ? (string) $details[$key]
                    : __('admin.media_issue_review.facts.unavailable'),
            ])
            ->all();
    }
}
