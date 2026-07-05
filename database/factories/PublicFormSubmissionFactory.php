<?php

namespace Database\Factories;

use App\Enums\PublicFormSubmissionStatus;
use App\Models\PublicFormSubmission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PublicFormSubmission>
 */
class PublicFormSubmissionFactory extends Factory
{
    protected $model = PublicFormSubmission::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'form_key' => 'request-transcription',
            'form_name_snapshot' => 'Request transcription',
            'payload' => [
                'name' => 'Test Submitter',
                'email' => 'submitter@example.com',
            ],
            'status' => PublicFormSubmissionStatus::New,
            'submitted_at' => now(),
            'source_url' => 'https://podtext.test/search',
            'submitter_ip_hash' => hash('sha256', '127.0.0.1'),
            'user_agent_hash' => hash('sha256', 'Pest'),
            'metadata' => [
                'display_mode' => 'modal',
            ],
        ];
    }

    public function reviewed(): self
    {
        return $this->state([
            'status' => PublicFormSubmissionStatus::Reviewed,
        ]);
    }

    public function archived(): self
    {
        return $this->state([
            'status' => PublicFormSubmissionStatus::Archived,
        ]);
    }
}
