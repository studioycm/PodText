<?php

namespace App\Livewire\Public;

use App\Support\PublicFront\Forms\PublicFormSchemaFactory;
use App\Support\PublicFront\Forms\PublicFormSubmitter;
use App\Support\PublicFront\PublicFrontRenderContext;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

class PublicFormModal extends Component
{
    #[Locked]
    public string $formKey = '';

    #[Locked]
    public string $displayMode = 'modal';

    public bool $showTrigger = true;

    /** @var array<string, mixed> */
    public array $data = [];

    public string $honeypot = '';

    public ?string $successMessage = null;

    public function mount(string $formKey, ?string $displayMode = null, bool $showTrigger = true): void
    {
        $this->formKey = $formKey;
        $this->showTrigger = $showTrigger;

        $definition = $this->definition();
        $this->displayMode = $this->resolveDisplayMode($displayMode, $definition);
        $this->data = $this->defaultData($definition);
    }

    public function submit(PublicFormSubmitter $submitter): void
    {
        $definition = $this->definition();

        if ($definition === null) {
            $this->addError('form', __('public.forms.unavailable'));

            return;
        }

        try {
            $submitter->submit(
                definition: $definition,
                data: $this->data,
                honeypot: $this->honeypot,
                sourceUrl: $this->sourceUrl(),
                metadata: [
                    'display_mode' => $this->displayMode,
                ],
            );
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $key => $messages) {
                $this->addError($key === 'form' ? 'form' : "data.{$key}", $messages[0] ?? __('validation.invalid'));
            }

            return;
        }

        $this->successMessage = (string) $definition['success_message'];
        $this->honeypot = '';
        $this->data = $this->defaultData($definition);
    }

    public function render(PublicFormSchemaFactory $schemaFactory): View
    {
        $definition = $this->definition();

        return view('livewire.public.public-form-modal', [
            'definition' => $definition,
            'fields' => $schemaFactory->fields($definition),
            'displayMode' => $this->displayMode,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function definition(): ?array
    {
        $definitions = $this->renderContext()->publicForms()['definitions'] ?? [];

        foreach ($definitions as $definition) {
            if (! is_array($definition)) {
                continue;
            }

            if (($definition['key'] ?? null) === $this->formKey && ($definition['enabled'] ?? false) === true) {
                return $definition;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>|null  $definition
     * @return array<string, mixed>
     */
    private function defaultData(?array $definition): array
    {
        if ($definition === null) {
            return [];
        }

        return collect($definition['fields'] ?? [])
            ->filter(fn (mixed $field): bool => is_array($field) && filled($field['key'] ?? null))
            ->mapWithKeys(function (array $field): array {
                $type = $field['type'] ?? 'text';

                return [
                    $field['key'] => match ($type) {
                        'checkbox' => filled($field['options'] ?? []) ? [] : false,
                        'toggle' => false,
                        default => '',
                    },
                ];
            })
            ->all();
    }

    /**
     * @param  array<string, mixed>|null  $definition
     */
    private function resolveDisplayMode(?string $displayMode, ?array $definition): string
    {
        if (in_array($displayMode, ['modal', 'slide_over'], true)) {
            return $displayMode;
        }

        return (string) ($definition['display_mode_default'] ?? 'modal');
    }

    private function sourceUrl(): ?string
    {
        $url = request()->headers->get('referer') ?: url()->previous();

        if (! is_string($url) || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        return $url;
    }

    private function renderContext(): PublicFrontRenderContext
    {
        return app(PublicFrontRenderContext::class);
    }
}
