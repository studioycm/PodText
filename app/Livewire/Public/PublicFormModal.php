<?php

namespace App\Livewire\Public;

use App\Models\PublicFormSubmission;
use App\Support\PublicFront\Forms\PublicFormPayloadValidator;
use App\Support\PublicFront\Forms\PublicFormSchemaFactory;
use App\Support\PublicFront\PublicFrontConfigReader;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\RateLimiter;
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

    public function submit(PublicFormPayloadValidator $validator): void
    {
        $definition = $this->definition();

        if ($definition === null) {
            $this->addError('form', __('public.forms.unavailable'));

            return;
        }

        if (filled($this->honeypot)) {
            $this->addError('form', __('public.forms.unavailable'));

            return;
        }

        $rateLimitKey = $this->rateLimitKey($definition);
        $maxAttempts = (int) ($definition['settings']['rate_limit_attempts'] ?? 5);

        if (RateLimiter::tooManyAttempts($rateLimitKey, $maxAttempts)) {
            $this->addError('form', __('public.forms.rate_limited', [
                'seconds' => RateLimiter::availableIn($rateLimitKey),
            ]));

            return;
        }

        RateLimiter::hit($rateLimitKey, (int) ($definition['settings']['rate_limit_decay_seconds'] ?? 600));

        try {
            $payload = $validator->validate($definition, $this->data);
        } catch (ValidationException $exception) {
            foreach ($exception->validator->errors()->messages() as $key => $messages) {
                $this->addError("data.{$key}", $messages[0] ?? __('validation.invalid'));
            }

            return;
        }

        PublicFormSubmission::query()->create([
            'form_key' => $definition['key'],
            'form_name_snapshot' => $definition['name'],
            'payload' => $payload,
            'source_url' => $this->sourceUrl(),
            'submitter_ip_hash' => $this->hashValue(request()->ip()),
            'user_agent_hash' => $this->hashValue(request()->userAgent()),
            'metadata' => [
                'display_mode' => $this->displayMode,
            ],
        ]);

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
        $definitions = app(PublicFrontConfigReader::class)
            ->read()
            ->group('public_forms')['definitions'] ?? [];

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

    /**
     * @param  array<string, mixed>  $definition
     */
    private function rateLimitKey(array $definition): string
    {
        return 'public-form:'.$definition['key'].':'.($this->hashValue(request()->ip().'|'.request()->userAgent()) ?? 'unknown');
    }

    private function hashValue(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        return hash_hmac('sha256', $value, config('app.key') ?: config('app.name'));
    }

    private function sourceUrl(): ?string
    {
        $url = request()->headers->get('referer') ?: url()->previous();

        if (! is_string($url) || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        return $url;
    }
}
