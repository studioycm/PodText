<?php

namespace App\Filament\Support\Concerns;

use App\Enums\MediaAttachmentRole;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\User;
use App\Support\Media\MediaAttachmentFormState;
use App\Support\Media\OwnerImageChangedException;
use App\Support\Media\OwnerImageChoicePresentation;
use App\Support\Media\OwnerImagePresenter;
use Livewire\Attributes\Locked;

trait InteractsWithOwnerImageFormLifecycle
{
    #[Locked]
    public ?int $ownerImageExpectedMediaId = null;

    private ?string $pendingOwnerImageReferenceKey = null;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function hydrateOwnerImageForm(
        array $data,
        ContentGroup|ContentItem $owner,
        MediaAttachmentRole $role,
        string $field,
    ): array {
        $presenter = app(OwnerImagePresenter::class);
        $this->captureOwnerImageBaseline($presenter, $owner, $role);
        $data[$field] = $presenter->pickerIdentity($owner, $role);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepareOwnerImageForm(
        array $data,
        ContentGroup|ContentItem|null $owner,
        MediaAttachmentRole $role,
        string $field,
    ): array {
        unset(
            $data['relation_owner_image_baseline_token'],
            $data['relation_owner_image_pending_identity'],
        );

        [$data, $this->pendingOwnerImageReferenceKey] = app(MediaAttachmentFormState::class)->prepare(
            $data,
            $field,
            $owner,
            $role,
        );

        return $data;
    }

    protected function persistOwnerImageForm(
        ContentGroup|ContentItem $owner,
        MediaAttachmentRole $role,
        string $field,
        User $actor,
        bool $enforceExpectedIdentity = false,
    ): void {
        try {
            app(MediaAttachmentFormState::class)->persist(
                $owner,
                $this->pendingOwnerImageReferenceKey,
                $role,
                $actor,
                $field,
                $this->ownerImageExpectedMediaId,
                enforceExpectedIdentity: $enforceExpectedIdentity,
            );
        } catch (OwnerImageChangedException) {
            $presenter = app(OwnerImagePresenter::class);
            $presenter->refresh($owner, $role);
            $presentation = $this->captureOwnerImageBaseline($presenter, $owner, $role);
            $currentEvidence = collect([
                $presentation->directMedia['label'] ?? null,
                $presentation->directMedia['reference_key'] ?? null,
            ])->filter()->implode(' · ');
            $this->addError(
                "data.{$field}",
                implode(' ', array_filter([
                    __('admin.validation.owner_image_changed'),
                    $currentEvidence,
                ])),
            );
            $this->halt(true);
        }

        if ($enforceExpectedIdentity) {
            $presenter = app(OwnerImagePresenter::class);
            $presenter->refresh($owner, $role);
            $this->captureOwnerImageBaseline($presenter, $owner, $role);
        }
    }

    private function captureOwnerImageBaseline(
        OwnerImagePresenter $presenter,
        ContentGroup|ContentItem $owner,
        MediaAttachmentRole $role,
    ): OwnerImageChoicePresentation {
        $presentation = $presenter->choice(
            $owner,
            $role,
            $presenter->pickerIdentity($owner, $role),
            [
                'commit' => __('admin.actions.save'),
                'cancel' => __('admin.actions.cancel'),
            ],
        );

        $this->ownerImageExpectedMediaId = $presentation->expectedMediaId;

        return $presentation;
    }
}
