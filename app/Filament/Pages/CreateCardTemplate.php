<?php

namespace App\Filament\Pages;

use App\Settings\PublicContentSettings;
use App\Support\PublicFront\Cards\PublicFrontCardTemplateRegistry;
use App\Support\PublicFront\PublicFrontConfigValidator;
use App\Support\Settings\CardTemplates\CardTemplateAccessPolicy;
use App\Support\Settings\CardTemplates\CardTemplateDraftFactory;
use App\Support\Settings\CardTemplates\CardTemplateFocusedWriter;
use App\Support\Settings\CardTemplates\CardTemplateIdentity;
use App\Support\Settings\CardTemplates\CardTemplateWriteResult;
use App\Support\Settings\SettingsSp3aMeasurementFixture;
use Closure;

class CreateCardTemplate extends CardTemplateEditorPage
{
    protected static ?string $slug = 'settings/card-templates/create';

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->initializeMeasurementMode();
        $mode = request()->query('mode', 'blank');
        abort_unless(is_string($mode) && in_array($mode, ['blank', 'clone', 'override'], true), 404);
        $this->operationMode = $mode;

        $snapshot = $this->freshMountSnapshot();
        $templates = $snapshot['card_templates'] ?? null;
        abort_unless(is_array($templates) && array_is_list($templates), 404);
        $identity = app(CardTemplateIdentity::class);
        $policy = app(CardTemplateAccessPolicy::class);
        $draftFactory = app(CardTemplateDraftFactory::class);
        $this->capable = $policy->currentActorCanManageProtectedTemplates();

        if ($mode === 'blank') {
            $draft = $draftFactory->blank();
        } else {
            $family = request()->query('family');
            $key = request()->query('key');
            abort_unless(is_string($family) && is_string($key) && $identity->valid($family, $key), 404);
            $this->sourceFamily = $family;
            $this->sourceKey = $key;

            if ($mode === 'clone') {
                $matches = $identity->locate($templates, $family, $key);
                abort_unless(count($matches) === 1, 404);
                $source = $matches[0]['template'];
                abort_unless($this->sp3aMeasurementMode || $this->validStoredTemplate($source), 404);
                abort_if($policy->isProtected($source) && ! $this->capable, 403);
                $this->sourceFingerprint = $identity->fingerprint($source);
                $this->templateProtectedAtMount = $policy->isProtected($source);
                $draft = $draftFactory->clone($source, $templates);
            } else {
                $defaultKey = PublicFrontCardTemplateRegistry::defaultTemplateKeys()[$family] ?? null;
                abort_unless($defaultKey === $key, 404);
                abort_unless($identity->locate($templates, $family, $key) === [], 404);
                $draft = PublicFrontCardTemplateRegistry::defaultTemplateForFamily($family);
                abort_unless(($draft['key'] ?? null) === $key && ($draft['family'] ?? null) === $family, 404);
                $this->defaultIdentity = true;
                $this->templateProtectedAtMount = $policy->isProtected($draft);
            }
        }

        $this->restricted = $this->templateProtectedAtMount && ! $this->capable;
        $draft = $policy->readSafeTemplate($draft, $this->capable);
        $builderDraft = $this->cardTemplatesForBuilder([$draft])[0];
        $this->setFamilyImportLock($snapshot, (string) $builderDraft['family']);
        $this->form->fill($builderDraft);
    }

    public function getTitle(): string
    {
        return __('admin.settings_sp3c.editor.create_title');
    }

    /**
     * @param  array<string, mixed>  $draft
     */
    protected function writeDraft(
        array $draft,
        Closure $beforePersist,
        Closure $afterPersist,
    ): CardTemplateWriteResult {
        return app(CardTemplateFocusedWriter::class)->create(
            draft: $draft,
            mode: $this->operationMode,
            sourceFamily: $this->sourceFamily,
            sourceKey: $this->sourceKey,
            sourceFingerprint: $this->sourceFingerprint,
            beforePersist: $beforePersist,
            afterPersist: $afterPersist,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function freshMountSnapshot(): array
    {
        if ($this->sp3aMeasurementMode) {
            return app(SettingsSp3aMeasurementFixture::class)->payload();
        }

        $settings = app(PublicContentSettings::class);
        $settings->refresh();

        return $settings->toArray();
    }

    /**
     * @param  array<string, mixed>  $template
     */
    private function validStoredTemplate(array $template): bool
    {
        $result = app(PublicFrontConfigValidator::class)->validateGroups([
            'card_templates' => [$template],
        ], ['card_templates']);

        return ! $result->hasInvalidConfig() && count($result->group('card_templates')) === 1;
    }
}
