<?php

namespace App\Http\Controllers;

use App\Support\PublicFront\Forms\PublicFormSubmitter;
use App\Support\PublicFront\Forms\PublicFormVerificationPolicy;
use App\Support\PublicFront\Maintenance\MaintenancePageRenderer;
use App\Support\PublicFront\PublicFrontConfigReader;
use Illuminate\Http\Request;
use Illuminate\Support\MessageBag;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class MaintenanceFormSubmissionController
{
    public function __invoke(
        Request $request,
        PublicFrontConfigReader $configReader,
        MaintenancePageRenderer $renderer,
        PublicFormSubmitter $submitter,
        PublicFormVerificationPolicy $verificationPolicy,
    ): Response {
        $maintenance = $configReader->group('maintenance');

        if (! (bool) ($maintenance['enabled'] ?? false)) {
            abort(404);
        }

        $definition = $renderer->definition($maintenance);

        if ($definition === null) {
            abort(404);
        }

        $formData = is_array($request->input('data')) ? $request->input('data') : [];
        $sourceUrl = $request->string('source_url')->toString() ?: url()->previous();
        $verificationToken = $request->string('verification_token')->toString()
            ?: $request->query('verification_token');

        if ($verificationPolicy->requiresEmailVerification($definition) && ! $request->hasValidSignature()) {
            return $renderer->response(
                maintenance: $maintenance,
                formData: $formData,
                formErrors: new MessageBag([
                    'verification' => __('public.forms.verification.signed_token_invalid'),
                ]),
                sourceUrl: $sourceUrl,
            );
        }

        try {
            $submitter->submit(
                definition: $definition,
                data: $formData,
                honeypot: $request->string('maintenance_honeypot')->toString(),
                sourceUrl: $sourceUrl,
                metadata: [
                    'display_mode' => 'maintenance_plain',
                    'maintenance_form_location' => $maintenance['form_location'] ?? null,
                    'maintenance_form_position' => $maintenance['form_position'] ?? null,
                ],
                verificationToken: is_string($verificationToken) ? $verificationToken : null,
                verificationCode: $request->string('verification_code')->toString(),
            );
        } catch (ValidationException $exception) {
            return $renderer->response(
                maintenance: $maintenance,
                formData: $formData,
                formErrors: new MessageBag($exception->errors()),
                formVerificationToken: is_string($verificationToken) ? $verificationToken : null,
                formActionUrl: is_string($verificationToken) && $request->hasValidSignature()
                    ? $request->fullUrl()
                    : null,
                sourceUrl: $sourceUrl,
            );
        }

        return $renderer->response(
            maintenance: $maintenance,
            formData: [],
            formSuccessMessage: (string) ($definition['success_message'] ?? __('public.forms.success')),
            sourceUrl: $sourceUrl,
        );
    }
}
