<?php

namespace App\Support\PublicFront\Forms;

use App\Models\PublicFormSubmission;
use Illuminate\Support\Str;

class PublicFormSubmissionPresenter
{
    public function summary(PublicFormSubmission $submission): string
    {
        return Str::limit($this->plainTextPayload($submission), 120);
    }

    public function plainTextPayload(PublicFormSubmission $submission): string
    {
        $payload = $submission->payload ?? [];

        if ($payload === []) {
            return __('admin.labels.none');
        }

        return collect($payload)
            ->map(fn (mixed $value, string $key): string => "{$key}: {$this->stringValue($value)}")
            ->join(PHP_EOL);
    }

    private function stringValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? __('public.labels.yes') : __('public.labels.no');
        }

        if (is_array($value)) {
            return collect($value)
                ->map(fn (mixed $item): string => $this->stringValue($item))
                ->join(', ');
        }

        return trim((string) $value);
    }
}
