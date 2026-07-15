<?php

namespace App\Http\Controllers;

use App\Enums\FormVerificationChannel;
use App\Support\Forms\Verification\FormVerificationManager;
use App\Support\PublicFront\Forms\PublicFormVerificationPolicy;
use App\Support\PublicFront\Maintenance\MaintenancePageRenderer;
use App\Support\PublicFront\PublicFrontConfigReader;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\MessageBag;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class MaintenanceFormVerificationCodeController
{
    public function __invoke(
        Request $request,
        PublicFrontConfigReader $configReader,
        MaintenancePageRenderer $renderer,
        PublicFormVerificationPolicy $verificationPolicy,
        FormVerificationManager $verificationManager,
    ): Response {
        $maintenance = $configReader->group('maintenance');

        if (! (bool) ($maintenance['enabled'] ?? false)) {
            abort(404);
        }

        $definition = $renderer->definition($maintenance);

        if ($definition === null || ! $verificationPolicy->requiresEmailVerification($definition)) {
            abort(404);
        }

        $formData = is_array($request->input('data')) ? $request->input('data') : [];
        $sourceUrl = $request->string('source_url')->toString() ?: url()->previous();
        $field = $verificationPolicy->submitterEmailField($definition);
        $emailKey = (string) ($field['key'] ?? 'email');

        if (filled($request->string('maintenance_honeypot')->toString())) {
            return $renderer->response(
                maintenance: $maintenance,
                formData: $formData,
                formErrors: new MessageBag(['form' => __('public.forms.unavailable')]),
                sourceUrl: $sourceUrl,
            );
        }

        $validator = Validator::make(
            ['email' => $formData[$emailKey] ?? null],
            ['email' => ['required', 'string', 'email:rfc', 'max:255']],
            attributes: ['email' => (string) ($field['label'] ?? $emailKey)],
        );

        if ($validator->fails()) {
            return $renderer->response(
                maintenance: $maintenance,
                formData: $formData,
                formErrors: new MessageBag([$emailKey => $validator->errors()->first('email')]),
                sourceUrl: $sourceUrl,
            );
        }

        $verificationToken = Str::random(40);

        try {
            $verificationManager->send(
                channel: FormVerificationChannel::Email,
                address: Str::of((string) $formData[$emailKey])->trim()->lower()->toString(),
                formKey: (string) $definition['key'],
                formName: (string) $definition['name'],
                guestToken: $verificationToken,
                ipAddress: $request->ip(),
                locale: app()->getLocale(),
            );
        } catch (ValidationException $exception) {
            return $renderer->response(
                maintenance: $maintenance,
                formData: $formData,
                formErrors: new MessageBag($exception->errors()),
                sourceUrl: $sourceUrl,
            );
        }

        return $renderer->response(
            maintenance: $maintenance,
            formData: $formData,
            formVerificationToken: $verificationToken,
            formActionUrl: URL::temporarySignedRoute(
                'public.maintenance-form.submit',
                now()->addMinutes(FormVerificationManager::expiresAfterMinutes()),
                ['verification_token' => $verificationToken],
            ),
            formVerificationMessage: __('public.forms.verification.sent'),
            sourceUrl: $sourceUrl,
        );
    }
}
