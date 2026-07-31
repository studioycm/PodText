<?php

use App\Enums\DashboardReason;
use App\Enums\DashboardTier;
use App\Enums\FunnelStage;
use App\Enums\MediaDiagnosticReason;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
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

it('owns the tier split in one place, with colour, icon and description', function (): void {
    expect(DashboardTier::Invisible)->toBeInstanceOf(HasColor::class)
        ->and(DashboardTier::Invisible)->toBeInstanceOf(HasIcon::class)
        ->and(DashboardTier::Invisible)->toBeInstanceOf(HasDescription::class)
        ->and(DashboardTier::Invisible->getColor())->toBe('danger')
        ->and(DashboardTier::Attention->getColor())->toBe('warning')
        ->and(DashboardTier::Invisible->barClass())->toContain('bg-danger')
        ->and(DashboardTier::Attention->barClass())->toContain('bg-warning')
        ->and(DashboardTier::Invisible->getDescription())->not->toBeEmpty()
        // The tier owns which reasons belong to it, so the two can never drift.
        ->and(DashboardTier::Invisible->reasons())->toBe(DashboardReason::gap())
        ->and(DashboardTier::Attention->reasons())->toBe(DashboardReason::attention());
});

it('lets every reason name its own tier and explain itself', function (): void {
    expect(DashboardReason::MissingTranscription)->toBeInstanceOf(HasDescription::class)
        ->and(DashboardReason::MissingTranscription->tier())->toBe(DashboardTier::Invisible)
        ->and(DashboardReason::UnpublishedGroup->tier())->toBe(DashboardTier::Invisible)
        ->and(DashboardReason::MissingMedia->tier())->toBe(DashboardTier::Attention)
        ->and(DashboardReason::MissingCategory->tier())->toBe(DashboardTier::Attention);

    foreach (DashboardReason::cases() as $reason) {
        expect($reason->getDescription())->toBeString()->not->toBeEmpty();
    }
});
