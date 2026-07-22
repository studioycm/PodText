<?php

use App\Models\Media;
use App\Models\MediaAsset;
use App\Models\MediaProviderBinding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Http::preventStrayRequests();
    Storage::fake('public');
    Storage::fake('local');
});

it('reports the complete library without changing database identity', function (): void {
    $present = Media::factory()->create();
    $missing = Media::factory()->create();
    Storage::disk('public')->put($present->path, 'image');
    $keysBefore = Media::query()->orderBy('id')->pluck('reference_key', 'id')->all();

    $exitCode = Artisan::call('media-assets:convert-curator', ['--json' => true]);
    $report = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect($exitCode)->toBe(0)
        ->and($report['mode'])->toBe('report')
        ->and($report['counts']['media_rows'])->toBe(2)
        ->and($report['counts']['unbound_media'])->toBe(2)
        ->and($report['counts']['missing_files'])->toBe(1)
        ->and(MediaAsset::query()->count())->toBe(0)
        ->and(MediaProviderBinding::query()->count())->toBe(0)
        ->and(Media::query()->orderBy('id')->pluck('reference_key', 'id')->all())->toBe($keysBefore)
        ->and($missing->fresh()->reference_key)->toBe($keysBefore[$missing->getKey()]);
});

it('renders the report only summary for an operator', function (): void {
    Media::factory()->create();

    $exitCode = Artisan::call('media-assets:convert-curator');
    $output = Artisan::output();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('report only; no database values changed')
        ->and($output)->toContain('media_rows')
        ->and($output)->toContain('unbound_media')
        ->and($output)->toContain('missing_files');
});

it('applies the database conversion only with the explicit apply option', function (): void {
    $media = Media::factory()->create();
    DB::table('curator')->where('id', $media->getKey())->update(['reference_key' => null]);

    $exitCode = Artisan::call('media-assets:convert-curator', [
        '--apply' => true,
        '--json' => true,
    ]);
    $report = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect($exitCode)->toBe(0)
        ->and($report['mode'])->toBe('applied')
        ->and($report['counts']['assets_created'])->toBe(1)
        ->and($report['counts']['bindings_created'])->toBe(1)
        ->and($report['counts']['keys_generated'])->toBe(1)
        ->and(MediaAsset::query()->count())->toBe(1)
        ->and(MediaProviderBinding::query()->count())->toBe(1)
        ->and($media->fresh()->reference_key)->not->toBeNull();
});
