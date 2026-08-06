<?php

use App\Support\Search\HebrewSearchFold;
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

/*
 * Slug columns deliberately get no `*_search` shadow: the slugger already folds
 * its input, so a slug is its own fold and a folded search term can be compared
 * against the stored column directly. That is an invariant, not a coincidence,
 * so it is asserted rather than assumed.
 */
it('emits slugs that are already folded, so slug columns need no shadow', function (string $source): void {
    $slug = HebrewSlugger::slug($source);

    expect(HebrewSearchFold::fold($slug))->toBe($slug);
})->with([
    'pointed hebrew' => ['מַשֶּׁה הַגָּדוֹל'],
    'mixed script' => ['Shalom שָׁלוֹם 2024'],
    'punctuation' => ['ג׳ירוזלם — בית־ספר "חדש"!'],
]);

it('caps slugs and keeps uniqueness suffixes within the cap', function (): void {
    $source = str_repeat('אבגדה', 40);

    $slug = HebrewSlugger::slug($source);
    $unique = HebrewSlugger::unique($source, fn (string $candidate): bool => $candidate === $slug);

    expect(mb_strlen($slug))->toBe(HebrewSlugger::MaxLength)
        ->and(mb_strlen($unique))->toBe(HebrewSlugger::MaxLength)
        ->and($unique)->toEndWith('-2');
});
