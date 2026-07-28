<?php

use App\Filament\Resources\Media\MediaResource;
use App\Models\ContentItem;
use App\Models\Media;
use App\Support\Media\MediaRecordScope;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\LazyLoadingViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

test('collection-retrieved models throw on lazy relation loads outside production', function (): void {
    ContentItem::factory()->count(2)->create();

    $fresh = ContentItem::query()->get()->firstOrFail();

    expect(Model::preventsLazyLoading())->toBeTrue()
        ->and(fn (): mixed => $fresh->transcriptions)
        ->toThrow(LazyLoadingViolationException::class);
});

test('lazy loading violations log a warning and still load in production', function (): void {
    ContentItem::factory()->count(2)->create();
    $fresh = ContentItem::query()->get()->firstOrFail();

    Log::spy();
    app()['env'] = 'production';

    try {
        expect($fresh->transcriptions)->toBeInstanceOf(EloquentCollection::class);
    } finally {
        app()['env'] = 'testing';
    }

    Log::shouldHaveReceived('warning')
        ->once()
        ->with('Lazy loading violation detected.', [
            'model' => ContentItem::class,
            'relation' => 'transcriptions',
        ]);
});

test('lazy loads on models deleted after retrieval stay silent like stock laravel', function (): void {
    ContentItem::factory()->count(2)->create();
    $fresh = ContentItem::query()->get()->firstOrFail();
    $fresh->delete();

    expect($fresh->transcriptions)->toBeInstanceOf(EloquentCollection::class);
});

test('hasUniqueStorageIdentity trusts a selected storage identity count', function (): void {
    $original = Media::factory()->create();
    Media::factory()->create([
        'disk' => $original->disk,
        'directory' => $original->directory,
        'name' => $original->name,
        'path' => $original->path,
    ]);

    $projected = MediaResource::getEloquentQuery()->findOrFail($original->getKey());

    expect($projected->getAttributes())->toHaveKey('storage_identity_count')
        ->and(app(MediaRecordScope::class)->hasUniqueStorageIdentity($projected))->toBeFalse();
});

test('hasUniqueStorageIdentity falls back to a peer query under the missing-attribute guard', function (): void {
    $media = Media::factory()->create();
    $plain = Media::query()->findOrFail($media->getKey());

    Model::preventAccessingMissingAttributes();

    try {
        expect($plain->getAttributes())->not->toHaveKey('storage_identity_count')
            ->and(app(MediaRecordScope::class)->hasUniqueStorageIdentity($plain))->toBeTrue();

        Media::factory()->create([
            'disk' => $media->disk,
            'directory' => $media->directory,
            'name' => $media->name,
            'path' => $media->path,
        ]);

        expect(app(MediaRecordScope::class)->hasUniqueStorageIdentity($plain))->toBeFalse();
    } finally {
        Model::preventAccessingMissingAttributes(false);
    }
});
