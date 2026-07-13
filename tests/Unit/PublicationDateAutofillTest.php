<?php

use App\Enums\PublicationStatus;
use App\Support\Publication\PublicationDateAutofill;
use Carbon\CarbonImmutable;

it('fills published at only when a published status has no existing date', function (): void {
    $now = CarbonImmutable::parse('2026-07-13 12:00:00', 'Asia/Jerusalem');
    $existing = CarbonImmutable::parse('2026-07-10 09:00:00', 'Asia/Jerusalem');

    expect(PublicationDateAutofill::valueFor(PublicationStatus::Published, null, $now))->toBe($now)
        ->and(PublicationDateAutofill::valueFor(PublicationStatus::Published->value, '', $now))->toBe($now)
        ->and(PublicationDateAutofill::valueFor(PublicationStatus::Published, $existing, $now))->toBe($existing)
        ->and(PublicationDateAutofill::valueFor(PublicationStatus::Draft, null, $now))->toBeNull();
});
