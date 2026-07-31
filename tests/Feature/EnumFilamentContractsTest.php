<?php

use App\Enums\ImageUploadPurpose;
use App\Enums\MediaAcquisitionFilenameStrategy;
use App\Enums\MediaNamingStrategy;
use Filament\Support\Contracts\HasLabel;

/**
 * Any enum rendered as a Filament option list should implement HasLabel, so the
 * class can be passed straight to ->options() instead of each call site
 * hand-rolling the same label mapping — which is how MediaNamingStrategy's
 * mapping came to be duplicated across two files.
 */
it('gives every filament-rendered enum a label of its own', function (string $enum, string $sample): void {
    expect($enum)->toImplement(HasLabel::class);

    foreach ($enum::cases() as $case) {
        expect($case->getLabel())->toBeString()->not->toBeEmpty();
    }

    expect($enum::from($sample)->getLabel())->not->toBe($sample);
})->with([
    'image upload purpose' => [ImageUploadPurpose::class, ImageUploadPurpose::cases()[0]->value],
    'media naming strategy' => [MediaNamingStrategy::class, 'slug'],
    'acquisition filename strategy' => [MediaAcquisitionFilenameStrategy::class, 'app_generated'],
]);
