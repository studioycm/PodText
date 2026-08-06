<?php

use App\Support\Search\HebrewSearchFold;

it('strips niqqud so pointed and unpointed spellings fold together', function (): void {
    expect(HebrewSearchFold::fold('שָׁלוֹם'))->toBe('שלום')
        ->and(HebrewSearchFold::fold('שָׁלוֹם'))->toBe(HebrewSearchFold::fold('שלום'));
});

it('strips the rafe that hides the live defect on content item 56', function (): void {
    // Stored bytes are D796 D794 20 D79C D79E D794 D6BF 21 — a U+05BF RAFE
    // between the final ה and the exclamation mark, invisible when rendered.
    $stored = "זה למה\u{05BF}!";

    expect($stored)->not->toBe('זה למה!')
        ->and(HebrewSearchFold::fold($stored))->toBe('זה למה!');
});

it('strips cantillation marks and the shin and sin dots', function (): void {
    expect(HebrewSearchFold::fold("בְּ\u{0591}רֵאשִׁית"))->toBe('בראשית')
        ->and(HebrewSearchFold::fold('שׁ'))->toBe('ש')
        ->and(HebrewSearchFold::fold('שׂ'))->toBe('ש');
});

it('keeps final letter forms distinct', function (): void {
    expect(HebrewSearchFold::fold('שלום'))->toBe('שלום')
        ->and(HebrewSearchFold::fold('שלום'))->not->toBe('שלומ')
        ->and(HebrewSearchFold::fold('ךםןףץ'))->toBe('ךםןףץ');
});

it('keeps geresh gershayim and maqaf, which are spacing punctuation not marks', function (): void {
    expect(HebrewSearchFold::fold('ג׳ירוזלם'))->toBe('ג׳ירוזלם')
        ->and(HebrewSearchFold::fold('צה״ל'))->toBe('צה״ל')
        ->and(HebrewSearchFold::fold('בית־ספר'))->toBe('בית־ספר');
});

it('lowercases latin while leaving hebrew untouched', function (): void {
    expect(HebrewSearchFold::fold('Shalom שָׁלוֹם WORLD'))->toBe('shalom שלום world');
});

it('preserves digits emoji and other non hebrew content', function (): void {
    expect(HebrewSearchFold::fold('פרק 12 🎧 — נקודה'))->toBe('פרק 12 🎧 — נקודה');
});

it('folds to empty string for null and blank input', function (): void {
    expect(HebrewSearchFold::fold(null))->toBe('')
        ->and(HebrewSearchFold::fold(''))->toBe('');
});

it('is idempotent, so backfilling twice cannot drift', function (): void {
    $once = HebrewSearchFold::fold('בְּרֵאשִׁית Bara 12');

    expect(HebrewSearchFold::fold($once))->toBe($once);
});

it('folds a term the same way whichever side of the comparison it arrives on', function (): void {
    $stored = HebrewSearchFold::fold('הַתּוֹרָה שֶׁבִּכְתָב');
    $typedUnpointed = HebrewSearchFold::fold('התורה שבכתב');
    $typedPointed = HebrewSearchFold::fold('הַתּוֹרָה שֶׁבִּכְתָב');

    expect($typedUnpointed)->toBe($stored)
        ->and($typedPointed)->toBe($stored);
});
