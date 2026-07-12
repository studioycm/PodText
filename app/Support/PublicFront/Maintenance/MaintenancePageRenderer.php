<?php

namespace App\Support\PublicFront\Maintenance;

use App\Support\PublicFront\Forms\PublicFormSchemaFactory;
use App\Support\PublicFront\PublicFrontConfigReader;
use Illuminate\Support\MessageBag;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class MaintenancePageRenderer
{
    public function __construct(
        private readonly PublicFrontConfigReader $configReader,
        private readonly PublicFormSchemaFactory $schemaFactory,
    ) {}

    /**
     * @param  array<string, mixed>  $maintenance
     * @param  array<string, mixed>  $formData
     */
    public function response(
        array $maintenance,
        int $status = 503,
        ?int $retryAfter = null,
        array $formData = [],
        ?MessageBag $formErrors = null,
        ?string $formSuccessMessage = null,
        ?string $sourceUrl = null,
    ): Response {
        $retryAfter ??= max(1, (int) ($maintenance['retry_after_hours'] ?? 24)) * 3600;
        $definition = $this->definition($maintenance);
        $formHtml = $definition === null
            ? null
            : $this->formHtml($definition, $formData, $formErrors ?? new MessageBag, $formSuccessMessage, $sourceUrl);

        return response()
            ->view('public.maintenance', [
                'maintenance' => $maintenance,
                'retryAfter' => $retryAfter,
                'maintenanceFormHtml' => $formHtml,
                'maintenanceFormLocation' => $maintenance['form_location'] ?? MaintenanceForm::LOCATION_RENDERED_PAGE,
                'maintenanceFormPosition' => $maintenance['form_position'] ?? MaintenanceForm::POSITION_AFTER_CONTENT,
                'maintenanceFormMarker' => MaintenanceForm::MARKER,
            ], $status)
            ->header('Retry-After', (string) $retryAfter);
    }

    /**
     * @param  array<string, mixed>  $maintenance
     * @return array<string, mixed>|null
     */
    public function definition(array $maintenance): ?array
    {
        $formKey = $maintenance['form_key'] ?? null;

        if (blank($formKey)) {
            return null;
        }

        foreach ($this->configReader->group('public_forms')['definitions'] ?? [] as $definition) {
            if (! is_array($definition)) {
                continue;
            }

            if (($definition['key'] ?? null) === $formKey && ($definition['enabled'] ?? false) === true) {
                return $definition;
            }
        }

        return null;
    }

    public function rawHtmlWithForm(string $rawHtml, ?string $formHtml): string
    {
        if (blank($formHtml)) {
            return $rawHtml;
        }

        if (str_contains($rawHtml, MaintenanceForm::MARKER)) {
            return Str::replaceFirst(MaintenanceForm::MARKER, $formHtml, $rawHtml);
        }

        return $rawHtml.$this->missingMarkerFallbackHtml().$formHtml;
    }

    private function missingMarkerFallbackHtml(): string
    {
        return '<div data-podtext-maintenance-form-marker-missing hidden>'
            .e(__('public.maintenance_form.marker_missing'))
            .'</div>';
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  array<string, mixed>  $formData
     */
    private function formHtml(
        array $definition,
        array $formData,
        MessageBag $formErrors,
        ?string $formSuccessMessage,
        ?string $sourceUrl,
    ): string {
        return view('public.partials.maintenance-form', [
            'definition' => $definition,
            'fields' => $this->schemaFactory->fields($definition),
            'formData' => $formData,
            'formErrors' => $formErrors,
            'formSuccessMessage' => $formSuccessMessage,
            'sourceUrl' => $sourceUrl ?? url()->full(),
            'actionUrl' => route('public.maintenance-form.submit'),
        ])->render();
    }
}
