<?php

use App\Support\Slugs\HebrewSlugger;

it('keeps hebrew latin and digits while normalizing separators', function (): void {
    expect(HebrewSlugger::slug(' שלום_עולם ABC 123 "בדיקה" '))
        ->toBe('שלום-עולם-abc-123-בדיקה');
});

it('strips hebrew marks quotes and punctuation', function (): void {
    expect(HebrewSlugger::slug('מַשֶּׁה ג׳רוזלם "חדש"!'))
        ->toBe('משה-גרוזלם-חדש');
});

it('falls back to lowercase ulids for empty slugs', function (): void {
    $slug = HebrewSlugger::slug('!!!');

    expect($slug)
        ->toHaveLength(26)
        ->and($slug)->toBe(strtolower($slug))
        ->and(HebrewSlugger::isUlidLike($slug))->toBeTrue();
});

it('caps slugs and keeps uniqueness suffixes within the cap', function (): void {
    $source = str_repeat('אבגדה', 40);

    $slug = HebrewSlugger::slug($source);
    $unique = HebrewSlugger::unique($source, fn (string $candidate): bool => $candidate === $slug);

    expect(mb_strlen($slug))->toBe(HebrewSlugger::MaxLength)
        ->and(mb_strlen($unique))->toBe(HebrewSlugger::MaxLength)
        ->and($unique)->toEndWith('-2');
});
