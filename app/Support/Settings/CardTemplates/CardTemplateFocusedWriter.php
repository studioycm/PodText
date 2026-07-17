<?php

namespace App\Support\Settings\CardTemplates;

use App\Settings\PublicContentSettings;
use App\Support\PublicFront\Cards\PublicFrontCardTemplateRegistry;
use Closure;

class CardTemplateFocusedWriter
{
    public function __construct(
        private readonly PublicContentSettings $settings,
        private readonly CardTemplateDraftNormalizer $normalizer,
        private readonly CardTemplateAccessPolicy $accessPolicy,
        private readonly CardTemplateReferenceScanner $referenceScanner,
        private readonly CardTemplateIdentity $identity,
    ) {}

    /**
     * @param  array<string, mixed>  $draft
     */
    public function edit(
        array $draft,
        string $originalFamily,
        string $originalKey,
        string $targetFingerprint,
        ?Closure $beforePersist = null,
        ?Closure $afterPersist = null,
    ): CardTemplateWriteResult {
        $this->authorizeEditor();
        $this->assertIdentity($originalFamily, $originalKey);
        [$snapshot, $templates] = $this->freshSnapshot();
        $located = $this->locateExactlyOnce($templates, $originalFamily, $originalKey);
        $freshTarget = $located['template'];

        if (! hash_equals($targetFingerprint, $this->identity->fingerprint($freshTarget))) {
            throw CardTemplateWriteException::named('stale');
        }

        $capable = $this->accessPolicy->currentActorCanManageProtectedTemplates();
        $candidate = $this->guardEditCandidate($this->normalizer->candidate($draft), $freshTarget, $capable);
        $newFamily = $candidate['family'] ?? null;
        $newKey = $candidate['key'] ?? null;

        if (! is_string($newFamily) || ! is_string($newKey)) {
            throw CardTemplateWriteException::named('validation');
        }

        $this->assertIdentity($newFamily, $newKey);
        $identityChanged = $newFamily !== $originalFamily || $newKey !== $originalKey;

        if ($identityChanged) {
            if ($this->isDefaultIdentity($originalFamily, $originalKey)
                || $this->isDefaultIdentity($newFamily, $newKey)) {
                throw CardTemplateWriteException::named('default_identity');
            }

            $references = $this->referenceScanner->scan($snapshot);

            if ($references->blocksMutation($originalFamily, $originalKey)) {
                throw CardTemplateWriteException::named(
                    'referenced',
                    $references->blockerLabels($originalFamily, $originalKey),
                );
            }

            if ($this->identity->locate($templates, $newFamily, $newKey) !== []) {
                throw CardTemplateWriteException::named('collision');
            }
        }

        $candidate['family'] = $newFamily;
        $candidate['key'] = $newKey;
        $normalized = $this->normalizer->normalizeCandidate($candidate);
        $this->guardNormalizedCandidate($normalized, $freshTarget, $capable);
        $templates[$located['index']] = $normalized;
        $beforePersist?->__invoke();
        $this->persist($snapshot, $templates);
        $afterPersist?->__invoke();

        return new CardTemplateWriteResult(
            family: $newFamily,
            key: $newKey,
            fingerprint: $this->identity->fingerprint($normalized),
        );
    }

    /**
     * @param  array<string, mixed>  $draft
     */
    public function create(
        array $draft,
        string $mode,
        ?string $sourceFamily = null,
        ?string $sourceKey = null,
        ?string $sourceFingerprint = null,
        ?Closure $beforePersist = null,
        ?Closure $afterPersist = null,
    ): CardTemplateWriteResult {
        $this->authorizeEditor();

        if (! in_array($mode, ['blank', 'clone', 'override'], true)) {
            throw CardTemplateWriteException::named('invalid_mode');
        }

        [$snapshot, $templates] = $this->freshSnapshot();
        $capable = $this->accessPolicy->currentActorCanManageProtectedTemplates();

        if ($mode === 'clone') {
            if (! is_string($sourceFamily) || ! is_string($sourceKey) || ! is_string($sourceFingerprint)) {
                throw CardTemplateWriteException::named('invalid_source');
            }

            $source = $this->locateExactlyOnce($templates, $sourceFamily, $sourceKey)['template'];

            if (! hash_equals($sourceFingerprint, $this->identity->fingerprint($source))) {
                throw CardTemplateWriteException::named('stale_clone');
            }

            if ($this->accessPolicy->isProtected($source) && ! $capable) {
                throw CardTemplateWriteException::named('protected');
            }
        }

        $draft = $this->normalizer->candidate($draft);
        $family = $draft['family'] ?? null;
        $key = $draft['key'] ?? null;

        if (! is_string($family) || ! is_string($key)) {
            throw CardTemplateWriteException::named('validation');
        }

        $this->assertIdentity($family, $key);

        if ($mode === 'override' && ($family !== $sourceFamily || $key !== $sourceKey)) {
            throw CardTemplateWriteException::named('invalid_override');
        }

        if ($mode === 'override'
            && (! is_string($sourceFamily)
                || ! is_string($sourceKey)
                || ! $this->isDefaultIdentity($sourceFamily, $sourceKey))) {
            throw CardTemplateWriteException::named('invalid_override');
        }

        if ($mode !== 'override' && $this->isDefaultIdentity($family, $key)) {
            throw CardTemplateWriteException::named('default_identity');
        }

        if ($this->identity->locate($templates, $family, $key) !== []) {
            throw CardTemplateWriteException::named('collision');
        }

        if ($this->accessPolicy->isProtected($draft) && ! $capable) {
            throw CardTemplateWriteException::named('protected');
        }

        $normalized = $this->normalizer->normalizeCandidate($draft);
        $this->guardNormalizedCandidate($normalized, null, $capable);
        $templates[] = $normalized;
        $beforePersist?->__invoke();
        $this->persist($snapshot, $templates);
        $afterPersist?->__invoke();

        return new CardTemplateWriteResult(
            family: $family,
            key: $key,
            fingerprint: $this->identity->fingerprint($normalized),
        );
    }

    public function delete(
        string $family,
        string $key,
        string $targetFingerprint,
        ?Closure $beforePersist = null,
        ?Closure $afterPersist = null,
    ): void {
        $this->authorizeEditor();
        $this->assertIdentity($family, $key);
        [$snapshot, $templates] = $this->freshSnapshot();
        $located = $this->locateExactlyOnce($templates, $family, $key);
        $freshTarget = $located['template'];

        if (! hash_equals($targetFingerprint, $this->identity->fingerprint($freshTarget))) {
            throw CardTemplateWriteException::named('stale');
        }

        if ($this->accessPolicy->isProtected($freshTarget)
            && ! $this->accessPolicy->currentActorCanManageProtectedTemplates()) {
            throw CardTemplateWriteException::named('protected');
        }

        if ($this->isDefaultIdentity($family, $key)) {
            throw CardTemplateWriteException::named('default_identity');
        }

        $references = $this->referenceScanner->scan($snapshot);

        if ($references->blocksMutation($family, $key)) {
            throw CardTemplateWriteException::named(
                'referenced',
                $references->blockerLabels($family, $key),
            );
        }

        unset($templates[$located['index']]);
        $beforePersist?->__invoke();
        $this->persist($snapshot, array_values($templates));
        $afterPersist?->__invoke();
    }

    private function authorizeEditor(): void
    {
        if (! $this->accessPolicy->currentActorCanAccessEditor()) {
            throw CardTemplateWriteException::named('unauthorized');
        }
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<int, mixed>}
     */
    private function freshSnapshot(): array
    {
        $this->settings->refresh();
        $snapshot = $this->settings->toArray();
        $templates = $snapshot['card_templates'] ?? null;

        if (! is_array($templates) || ! array_is_list($templates)) {
            throw CardTemplateWriteException::named('corrupt_root');
        }

        return [$snapshot, $templates];
    }

    /**
     * @param  array<int, mixed>  $templates
     * @return array{index: int, template: array<string, mixed>}
     */
    private function locateExactlyOnce(array $templates, string $family, string $key): array
    {
        $matches = $this->identity->locate($templates, $family, $key);

        if (count($matches) !== 1) {
            throw CardTemplateWriteException::named(count($matches) === 0 ? 'missing' : 'duplicate');
        }

        return $matches[0];
    }

    /**
     * @param  array<string, mixed>  $draft
     * @param  array<string, mixed>  $freshTarget
     * @return array<string, mixed>
     */
    private function guardEditCandidate(array $draft, array $freshTarget, bool $capable): array
    {
        $targetProtected = $this->accessPolicy->isProtected($freshTarget);

        if ($targetProtected && ! $capable) {
            if (array_key_exists('parts', $draft)) {
                throw CardTemplateWriteException::named('protected');
            }

            $draft['parts'] = $freshTarget['parts'];

            return $draft;
        }

        if (! $capable && $this->accessPolicy->isProtected($draft)) {
            throw CardTemplateWriteException::named('protected');
        }

        return $draft;
    }

    /**
     * @param  array<string, mixed>  $normalized
     * @param  array<string, mixed>|null  $freshTarget
     */
    private function guardNormalizedCandidate(array $normalized, ?array $freshTarget, bool $capable): void
    {
        if (! $capable && $this->accessPolicy->isProtected($normalized)) {
            if ($freshTarget === null || ! $this->accessPolicy->isProtected($freshTarget)) {
                throw CardTemplateWriteException::named('protected');
            }

            if ($this->identity->canonicalJson($normalized['parts'] ?? null)
                !== $this->identity->canonicalJson($freshTarget['parts'] ?? null)) {
                throw CardTemplateWriteException::named('protected');
            }
        }
    }

    private function assertIdentity(string $family, string $key): void
    {
        if (! $this->identity->valid($family, $key)) {
            throw CardTemplateWriteException::named('invalid_identity');
        }
    }

    private function isDefaultIdentity(string $family, string $key): bool
    {
        return (PublicFrontCardTemplateRegistry::defaultTemplateKeys()[$family] ?? null) === $key;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  array<int, mixed>  $templates
     */
    private function persist(array $snapshot, array $templates): void
    {
        $snapshot['card_templates'] = $templates;
        $this->settings->fill($snapshot);
        $this->settings->save();
    }
}
