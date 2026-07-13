<?php

namespace App\Livewire\Public;

use App\Enums\FormVerificationChannel;
use App\Enums\FormVerificationResult;
use App\Support\Forms\Verification\FormVerificationManager;
use App\Support\PublicFront\Forms\PublicFormSchemaFactory;
use App\Support\PublicFront\Forms\PublicFormSubmitter;
use App\Support\PublicFront\Forms\PublicFormVerificationPolicy;
use App\Support\PublicFront\PublicFrontRenderContext;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
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

    #[Locked]
    public string $verificationToken = '';

    public string $emailVerificationCode = '';

    public ?string $emailVerificationStatus = null;

    public bool $emailVerificationVerified = false;

    public function mount(string $formKey, ?string $displayMode = null, bool $showTrigger = true): void
    {
        $this->formKey = $formKey;
        $this->showTrigger = $showTrigger;

        $definition = $this->definition();
        $this->displayMode = $this->resolveDisplayMode($displayMode, $definition);
        $this->data = $this->defaultData($definition);
        $this->verificationToken = Str::random(40);
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
                verificationToken: $this->verificationToken,
            );
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $key => $messages) {
                $this->addError(in_array($key, ['form', 'verification'], true) ? $key : "data.{$key}", $messages[0] ?? __('validation.invalid'));
            }

            return;
        }

        $this->successMessage = (string) $definition['success_message'];
        $this->honeypot = '';
        $this->data = $this->defaultData($definition);
        $this->resetVerificationState();
    }

    public function sendEmailVerificationCode(
        FormVerificationManager $manager,
        PublicFormVerificationPolicy $verificationPolicy,
    ): void {
        $definition = $this->definition();

        if ($definition === null || ! $verificationPolicy->requiresEmailVerification($definition)) {
            return;
        }

        $email = $this->validatedSubmitterEmail($definition, $verificationPolicy);

        if ($email === null) {
            return;
        }

        try {
            $manager->send(
                channel: FormVerificationChannel::Email,
                address: $email,
                formKey: (string) $definition['key'],
                formName: (string) $definition['name'],
                guestToken: $this->verificationToken,
                ipAddress: request()->ip(),
                locale: app()->getLocale(),
            );
        } catch (ValidationException $exception) {
            $this->addError('verification', $exception->errors()['verification'][0] ?? __('public.forms.verification.send_failed'));

            return;
        }

        $this->emailVerificationVerified = false;
        $this->emailVerificationCode = '';
        $this->emailVerificationStatus = __('public.forms.verification.sent');
        $this->resetErrorBag('verification');
    }

    public function verifyEmailCode(
        FormVerificationManager $manager,
        PublicFormVerificationPolicy $verificationPolicy,
    ): void {
        $definition = $this->definition();

        if ($definition === null || ! $verificationPolicy->requiresEmailVerification($definition)) {
            return;
        }

        $email = $this->validatedSubmitterEmail($definition, $verificationPolicy);

        if ($email === null) {
            return;
        }

        $result = $manager->verify(
            channel: FormVerificationChannel::Email,
            address: $email,
            formKey: (string) $definition['key'],
            guestToken: $this->verificationToken,
            code: $this->emailVerificationCode,
        );

        if ($result !== FormVerificationResult::Verified) {
            $this->emailVerificationVerified = false;
            $this->addError('verification', $result->message());

            return;
        }

        $this->emailVerificationVerified = true;
        $this->emailVerificationStatus = __('public.forms.verification.verified');
        $this->resetErrorBag('verification');
    }

    public function render(PublicFormSchemaFactory $schemaFactory): View
    {
        $definition = $this->definition();

        return view('livewire.public.public-form-modal', [
            'definition' => $definition,
            'fields' => $schemaFactory->fields($definition),
            'displayMode' => $this->displayMode,
            'verificationRequired' => $definition !== null && app(PublicFormVerificationPolicy::class)->requiresEmailVerification($definition),
            'emailVerificationVerified' => $this->emailVerificationVerified,
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

    /**
     * @param  array<string, mixed>  $definition
     */
    private function validatedSubmitterEmail(array $definition, PublicFormVerificationPolicy $verificationPolicy): ?string
    {
        $field = $verificationPolicy->submitterEmailField($definition);

        if ($field === null) {
            $this->addError('verification', __('public.forms.verification.missing_email'));

            return null;
        }

        $key = (string) $field['key'];

        $validator = Validator::make(
            ['email' => $this->data[$key] ?? null],
            ['email' => ['required', 'string', 'email:rfc', 'max:255']],
            attributes: ['email' => (string) ($field['label'] ?? $key)],
        );

        if ($validator->fails()) {
            $this->addError("data.{$key}", $validator->errors()->first('email') ?: __('validation.email', ['attribute' => $key]));

            return null;
        }

        return Str::of((string) $this->data[$key])->trim()->lower()->toString();
    }

    private function resetVerificationState(): void
    {
        $this->verificationToken = Str::random(40);
        $this->emailVerificationCode = '';
        $this->emailVerificationStatus = null;
        $this->emailVerificationVerified = false;
        $this->resetErrorBag('verification');
    }
}
