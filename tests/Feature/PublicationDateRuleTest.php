<?php

use App\Enums\PublicationStatus;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\Transcription;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

/**
 * @return array<string, callable(): Model>
 */
dataset('publication date models', [
    'content item' => [fn (array $attributes = []) => ContentItem::factory()->create($attributes), ContentItem::class],
    'content group' => [fn (array $attributes = []) => ContentGroup::factory()->create($attributes), ContentGroup::class],
    'transcription' => [fn (array $attributes = []) => Transcription::factory()->create($attributes), Transcription::class],
]);

it('stamps a null publish date when a record is created as published', function (callable $create): void {
    Carbon::setTestNow('2026-08-05 10:00:00');

    $record = $create(['status' => PublicationStatus::Published, 'published_at' => null]);

    expect($record->refresh()->published_at?->toDateTimeString())->toBe('2026-08-05 10:00:00');
})->with('publication date models');

it('never overwrites an explicitly provided publish date', function (callable $create): void {
    $record = $create([
        'status' => PublicationStatus::Published,
        'published_at' => '2026-01-15 08:30:00',
    ]);

    expect($record->refresh()->published_at->toDateTimeString())->toBe('2026-01-15 08:30:00');

    $record->touch();

    expect($record->refresh()->published_at->toDateTimeString())->toBe('2026-01-15 08:30:00');
})->with('publication date models');

it('does not stamp drafts', function (callable $create): void {
    $record = $create(['status' => PublicationStatus::Draft, 'published_at' => null]);

    expect($record->refresh()->published_at)->toBeNull();
})->with('publication date models');

it('stamps when a draft flips to published without a date', function (callable $create): void {
    $record = $create(['status' => PublicationStatus::Draft, 'published_at' => null]);

    Carbon::setTestNow('2026-08-05 12:34:00');
    $record->update(['status' => PublicationStatus::Published]);

    expect($record->refresh()->published_at->toDateTimeString())->toBe('2026-08-05 12:34:00');
})->with('publication date models');

it('keeps the date when a published record is unpublished', function (callable $create): void {
    $record = $create([
        'status' => PublicationStatus::Published,
        'published_at' => '2026-02-01 09:00:00',
    ]);

    $record->update(['status' => PublicationStatus::Draft]);

    expect($record->refresh()->published_at->toDateTimeString())->toBe('2026-02-01 09:00:00');
})->with('publication date models');

it('resolves the effective publish date with a created_at fallback for legacy published rows', function (callable $create): void {
    $published = $create([
        'status' => PublicationStatus::Published,
        'published_at' => '2026-03-01 07:00:00',
    ]);
    $draft = $create(['status' => PublicationStatus::Draft, 'published_at' => null]);

    $legacy = $create(['status' => PublicationStatus::Draft, 'published_at' => null]);
    $legacy->forceFill(['status' => PublicationStatus::Published])->saveQuietly();
    $legacy->refresh();

    expect($published->effective_published_at->toDateTimeString())->toBe('2026-03-01 07:00:00')
        ->and($draft->effective_published_at)->toBeNull()
        ->and($legacy->published_at)->toBeNull()
        ->and($legacy->effective_published_at->toDateTimeString())
        ->toBe($legacy->created_at->toDateTimeString());
})->with('publication date models');

it('orders by the effective publish date with the fallback folded in', function (callable $create, string $model): void {
    Carbon::setTestNow('2026-08-01 00:00:00');
    $dated = $create([
        'status' => PublicationStatus::Published,
        'published_at' => '2026-07-05 08:00:00',
    ]);

    Carbon::setTestNow('2026-07-10 00:00:00');
    $legacy = $create(['status' => PublicationStatus::Draft, 'published_at' => null]);
    $legacy->forceFill(['status' => PublicationStatus::Published])->saveQuietly();

    Carbon::setTestNow('2026-08-01 00:00:00');
    $draft = $create(['status' => PublicationStatus::Draft, 'published_at' => null]);

    $desc = $model::query()
        ->whereKey([$dated->getKey(), $legacy->getKey(), $draft->getKey()])
        ->orderByEffectivePublishedAt()
        ->pluck('id')
        ->all();

    expect($desc)->toBe([$legacy->getKey(), $dated->getKey(), $draft->getKey()]);
})->with('publication date models');
