<?php

use App\Enums\DashboardReason;
use App\Enums\FunnelStage;
use App\Enums\MediaDiagnosticReason;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

it('gives every funnel stage one definition of its label, colour and bar', function (): void {
    expect(FunnelStage::Draft)->toBeInstanceOf(HasLabel::class)
        ->and(FunnelStage::Draft)->toBeInstanceOf(HasColor::class)
        ->and(FunnelStage::Draft)->toBeInstanceOf(HasIcon::class)
        ->and(FunnelStage::values())->toBe(['draft', 'published', 'transcribed', 'visible'])
        ->and(FunnelStage::tryFrom('visible')?->getColor())->toBe('success')
        ->and(FunnelStage::Visible->barClass())->toContain('bg-success')
        ->and(FunnelStage::Draft->getLabel())->toBe(__('admin.dashboard.legend.draft'));
});

it('classifies each blocker reason into its tier and styles it once', function (): void {
    expect(DashboardReason::MissingTranscription)->toBeInstanceOf(HasColor::class)
        ->and(DashboardReason::MissingTranscription)->toBeInstanceOf(HasIcon::class)
        // Tier is the split made durable: invisible vs merely incomplete.
        ->and(DashboardReason::MissingTranscription->hidesFromPublic())->toBeTrue()
        ->and(DashboardReason::UnpublishedGroup->hidesFromPublic())->toBeTrue()
        ->and(DashboardReason::MissingMedia->hidesFromPublic())->toBeFalse()
        ->and(DashboardReason::MissingCategory->hidesFromPublic())->toBeFalse()
        ->and(DashboardReason::gap())->toBe([DashboardReason::MissingTranscription, DashboardReason::UnpublishedGroup])
        ->and(DashboardReason::attention())->toBe([DashboardReason::MissingMedia, DashboardReason::MissingCategory])
        ->and(DashboardReason::MissingCategory->getColor())->toBe('violet')
        ->and(DashboardReason::MissingTranscription->getLabel())
        ->toBe(__('admin.dashboard.reasons.missing_transcription'));
});

it('gives every media diagnostic reason a colour and an icon for board 3', function (): void {
    expect(MediaDiagnosticReason::MissingFile)->toBeInstanceOf(HasColor::class)
        ->and(MediaDiagnosticReason::MissingFile)->toBeInstanceOf(HasIcon::class);

    foreach (MediaDiagnosticReason::cases() as $reason) {
        expect($reason->getColor())->toBeString()->not->toBeEmpty()
            ->and($reason->getIcon())->not->toBeNull();
    }
});
