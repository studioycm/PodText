<?php

use App\Rules\ExistsInTimezone;
use Filament\Forms\Components\DateTimePicker;
use Illuminate\Support\Facades\Validator;

it('rejects the spring-forward gap and accepts its edges', function (string $value, bool $valid): void {
    $validator = Validator::make(['at' => $value], ['at' => new ExistsInTimezone('Asia/Jerusalem', 'Y-m-d H:i:s')]);

    expect($validator->passes())->toBe($valid);
})->with([
    'inside the gap' => ['2026-03-27 02:30:00', false],
    'last valid before' => ['2026-03-27 01:59:00', true],
    'first valid after' => ['2026-03-27 03:00:00', true],
    'the fold hour (accept-later policy)' => ['2026-10-25 01:30:00', true],
    'ordinary time' => ['2026-08-08 12:00:00', true],
]);

it('wires the DST rule through the global picker hook using the raw internal format, not the display-affected getFormat()', function (): void {
    // ->seconds(false) is the live counter-example: getFormat() drops the
    // seconds token here, but getInternalFormat() (what non-native pickers
    // actually sync) never does. If the hook used getFormat(), parsing the
    // real raw state against it would throw "Trailing data", get swallowed
    // by the rule's catch-all, and the rule would silently never fire —
    // exactly the AppServiceProvider.php:changePublishedAt action field.
    $picker = DateTimePicker::make('probe')->seconds(false);

    expect($picker->getFormat())->toBe('Y-m-d H:i')
        ->and($picker->getInternalFormat())->toBe('Y-m-d H:i:s');

    $rule = collect($picker->getValidationRules())
        ->first(fn (mixed $rule): bool => $rule instanceof ExistsInTimezone);

    expect($rule)->not->toBeNull();
    expect(Validator::make(['probe' => '2026-03-27 02:30:00'], ['probe' => $rule])->passes())->toBeFalse();
    expect(Validator::make(['probe' => '2026-03-27 01:59:00'], ['probe' => $rule])->passes())->toBeTrue();
});

it('does not gate date-only pickers with the DST rule', function (): void {
    $picker = DateTimePicker::make('probe')->time(false);

    $rule = collect($picker->getValidationRules())
        ->first(fn (mixed $rule): bool => $rule instanceof ExistsInTimezone);

    expect($rule)->toBeNull();
});
