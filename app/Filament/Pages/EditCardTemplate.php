<?php

namespace App\Filament\Pages;

use App\Settings\PublicContentSettings;
use App\Support\PublicFront\PublicFrontConfigValidator;
use App\Support\Settings\CardTemplates\CardTemplateAccessPolicy;
use App\Support\Settings\CardTemplates\CardTemplateFocusedWriter;
use App\Support\Settings\CardTemplates\CardTemplateIdentity;
use App\Support\Settings\CardTemplates\CardTemplateWriteException;
use App\Support\Settings\CardTemplates\CardTemplateWriteResult;
use App\Support\Settings\SettingsPageProfiler;
use App\Support\Settings\SettingsSp3aMeasurementFixture;
use Closure;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\PageConfiguration;
use Filament\Panel;
use Filament\Support\Facades\FilamentView;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Route;
use Throwable;

class EditCardTemplate extends CardTemplateEditorPage
{
    protected static ?string $slug = 'settings/card-templates/edit/{family}/{key}';

    public static function routes(Panel $panel, ?PageConfiguration $configuration = null): void
    {
        $middleware = static::getRouteMiddleware($panel);

        if ($configuration) {
            $middleware = [
                ...$middleware,
                "page-configuration:{$configuration->getKey()}",
            ];
        }

        Route::get(static::getRoutePath($panel), static::class)
            ->middleware($middleware)
            ->withoutMiddleware(static::getWithoutRouteMiddleware($panel))
            ->name(static::getRelativeRouteName($panel))
            ->where([
                'family' => 'content_item|content_group|contributor',
                'key' => '[a-z][a-z0-9_-]{0,79}',
            ]);
    }

    public function mount(?string $family = null, ?string $key = null): void
    {
        abort_unless(static::canAccess(), 403);
        $this->initializeMeasurementMode();
        $family ??= request()->route('family');
        $key ??= request()->route('key');
        abort_unless(is_string($family) && is_string($key), 404);
        $identity = app(CardTemplateIdentity::class);
        abort_unless($identity->valid($family, $key), 404);

        if ($this->sp3aMeasurementMode) {
            abort_unless("{$family}:{$key}" === $this->measurementFixtureIdentity, 404);
            $snapshot = app(SettingsSp3aMeasurementFixture::class)->payload();
        } else {
            $settings = app(PublicContentSettings::class);
            $settings->refresh();
            $snapshot = $settings->toArray();
        }

        $templates = $snapshot['card_templates'] ?? null;
        abort_unless(is_array($templates) && array_is_list($templates), 404);
        $matches = $identity->locate($templates, $family, $key);
        abort_unless(count($matches) === 1, 404);
        $template = $matches[0]['template'];
        abort_unless($this->sp3aMeasurementMode || $this->validStoredTemplate($template), 404);

        $policy = app(CardTemplateAccessPolicy::class);
        $this->capable = $policy->currentActorCanManageProtectedTemplates();
        $this->templateProtectedAtMount = $policy->isProtected($template);
        $this->restricted = $this->templateProtectedAtMount && ! $this->capable;
        $this->originalFamily = $family;
        $this->originalKey = $key;
        $this->targetFingerprint = $identity->fingerprint($template);
        $this->defaultIdentity = $this->isDefaultIdentity($family, $key);
        $this->setFamilyImportLock($snapshot, $family);
        $safeTemplate = $policy->readSafeTemplate($template, $this->capable);
        $this->form->fill($this->cardTemplatesForBuilder([$safeTemplate])[0]);
        $this->initializePreview();
    }

    public function getTitle(): string
    {
        return __('admin.settings_sp3c.editor.edit_title');
    }

    public function deleteTemplate(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->restoreProfilingConfiguration();
        $this->enforceCurrentCapability();

        if ($this->refuseMutationBeforeDehydration()) {
            return;
        }

        abort_if($this->defaultIdentity, 403);

        if ($this->templateProtectedAtMount && ! $this->capable) {
            abort(403);
        }

        $profiler = app(SettingsPageProfiler::class);
        $profiler->withSubject('card-template-editor', function () use ($profiler): void {
            $profiler->withRequestKind(SettingsPageProfiler::REQUEST_SAVE, function () use ($profiler): void {
                try {
                    $this->beginDatabaseTransaction();
                    $profiler->measure('save.settings_persist', function (): void {
                        if (! is_string($this->originalFamily)
                            || ! is_string($this->originalKey)
                            || ! is_string($this->targetFingerprint)) {
                            throw CardTemplateWriteException::named('invalid_identity');
                        }

                        app(CardTemplateFocusedWriter::class)->delete(
                            $this->originalFamily,
                            $this->originalKey,
                            $this->targetFingerprint,
                            beforePersist: fn () => $this->callHook('beforeSave'),
                            afterPersist: fn () => $this->callHook('afterSave'),
                        );
                    }, SettingsPageProfiler::REQUEST_SAVE);
                } catch (CardTemplateWriteException $exception) {
                    $this->rollBackDatabaseTransaction();
                    $this->reportWriteFailure($exception);

                    return;
                } catch (Throwable $exception) {
                    $this->rollBackDatabaseTransaction();

                    throw $exception;
                }

                $this->commitDatabaseTransaction();
                Notification::make()
                    ->success()
                    ->title(__('admin.settings_sp3c.notifications.deleted'))
                    ->send();
                $redirectUrl = CardTemplateSettings::getUrl();
                $this->redirect($redirectUrl, navigate: FilamentView::hasSpaMode($redirectUrl));
            });
        });
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            ...parent::getHeaderActions(),
            Action::make('deleteTemplate')
                ->label(__('admin.actions.delete'))
                ->icon(Heroicon::OutlinedTrash)
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn (): bool => ! $this->defaultIdentity
                    && (! $this->templateProtectedAtMount
                        || app(CardTemplateAccessPolicy::class)->currentActorCanManageProtectedTemplates()))
                ->action(fn (): mixed => $this->deleteTemplate()),
        ];
    }

    /**
     * @param  array<string, mixed>  $draft
     */
    protected function writeDraft(
        array $draft,
        Closure $beforePersist,
        Closure $afterPersist,
    ): CardTemplateWriteResult {
        if (! is_string($this->originalFamily)
            || ! is_string($this->originalKey)
            || ! is_string($this->targetFingerprint)) {
            throw CardTemplateWriteException::named('invalid_identity');
        }

        return app(CardTemplateFocusedWriter::class)->edit(
            draft: $draft,
            originalFamily: $this->originalFamily,
            originalKey: $this->originalKey,
            targetFingerprint: $this->targetFingerprint,
            beforePersist: $beforePersist,
            afterPersist: $afterPersist,
        );
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
