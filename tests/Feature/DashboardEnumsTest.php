<?php

use App\Enums\DashboardReason;
use App\Enums\DashboardTier;
use App\Enums\FunnelStage;
use App\Enums\MediaDiagnosticReason;
use App\Enums\SparklineTrend;
use App\Support\Dashboard\Data\BreakdownRow;
use App\Support\Dashboard\Data\SeriesRow;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Support\Facades\File;

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

it('owns the sparkline trend colour in one enum home derived from deltas', function (): void {
    expect(SparklineTrend::fromDelta(3))->toBe(SparklineTrend::Up)
        ->and(SparklineTrend::fromDelta(-1))->toBe(SparklineTrend::Down)
        ->and(SparklineTrend::fromDelta(0))->toBe(SparklineTrend::Neutral)
        ->and(SparklineTrend::Up->strokeClass())->toContain('stroke-success')
        ->and(SparklineTrend::Down->strokeClass())->toContain('stroke-danger')
        ->and(SparklineTrend::Neutral->strokeClass())->toContain('stroke-gray')
        ->and(SparklineTrend::Up->textClass())->toContain('text-success')
        ->and(SparklineTrend::Down->textClass())->toContain('text-danger')
        ->and(SparklineTrend::Neutral->textClass())->toContain('text-gray');

    // Both data objects derive the trend from their own delta, so a view never
    // re-implements the comparison.
    $rising = new SeriesRow(key: 'published', label: 'Published', value: 5.0, previous: 2.0);
    $flat = new SeriesRow(key: 'draft', label: 'Draft', value: 2.0, previous: 2.0);
    $ranked = new BreakdownRow(label: 'Dana', value: 1.0, previous: 3.0);
    $unranked = new BreakdownRow(label: 'Alpha', value: 1.0);

    expect($rising->trend())->toBe(SparklineTrend::Up)
        ->and($flat->trend())->toBe(SparklineTrend::Neutral)
        ->and($ranked->trend())->toBe(SparklineTrend::Down)
        // No previous period means no comparison, not a neutral one.
        ->and($unranked->trend())->toBeNull();
});

it('scans the enum colour homes into the admin theme', function (): void {
    // The dashboard enums return Tailwind classes, but Tailwind only emits a
    // utility whose literal appears in a scanned source. Before this glob the
    // draft funnel bar (bg-gray-400) and two reason bars (bg-danger-400,
    // bg-violet-500) compiled to nothing and rendered colourless.
    expect(File::get(resource_path('css/filament/admin/theme.css')))
        ->toContain("@source '../../../../app/Enums/**/*';");
});
