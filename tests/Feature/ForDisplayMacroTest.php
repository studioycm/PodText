<?php

use App\Support\UiFormats;
use Carbon\Carbon;
use Carbon\CarbonImmutable;

it('renders UTC instants on Jerusalem walls, day-first', function (): void {
    expect(Carbon::parse('2026-01-15 10:00:00', 'UTC')->forDisplay())->toBe('15/01/2026 12:00'); // +02 winter
    expect(Carbon::parse('2026-07-15 10:00:00', 'UTC')->forDisplay())->toBe('15/07/2026 13:00'); // +03 summer
    expect(Carbon::parse('2026-07-15 10:00:00', 'UTC')->forDisplay(UiFormats::date()))->toBe('15/07/2026');
});

it('works on CarbonImmutable — importer support code holds those', function (): void {
    expect(CarbonImmutable::parse('2026-01-15 10:00:00', 'UTC')->forDisplay())->toBe('15/01/2026 12:00');
});

it('renders Hebrew month names with zero extra setup', function (): void {
    // nesbot/carbon's Laravel provider syncs the locale to app.locale ('he').
    expect(Carbon::parse('2026-01-15 10:00:00', 'UTC')->forDisplay('j F Y'))->toContain('ינואר');
});
