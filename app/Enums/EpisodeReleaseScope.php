<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Whether an episode's own switch has actually taken effect.
 *
 * This exists because «פורסם» meant two different things in the product: the
 * status control means "the switch is on", future-dated or not, while the
 * dashboard funnel meant "the switch is on AND the date has passed". Same
 * word, different sets — the funnel stage is now «יצא לאוויר», and this is
 * the filter that answers it, so a number and its door share one vocabulary.
 *
 * Deliberately NOT «מתוזמנים»: the scheduled TAB means something narrower —
 * future-dated with its podcast and transcript due by the air date. A
 * future-dated episode whose podcast will still be unpublished is not
 * scheduled, it is blocked. This asks only about the episode's own clock.
 */
enum EpisodeReleaseScope: string implements HasLabel
{
    case All = 'all';
    case Aired = 'aired';
    case Upcoming = 'upcoming';

    public static function fromFilter(mixed $value): self
    {
        return self::tryFrom(is_string($value) ? $value : '') ?? self::All;
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $scope): array => [$scope->value => $scope->getLabel()])
            ->all();
    }

    public function getLabel(): string
    {
        return __("admin.episode_release_scopes.{$this->value}");
    }

    public function indicator(): ?string
    {
        return $this === self::All
            ? null
            : __('admin.filters.release_indicator', ['scope' => $this->getLabel()]);
    }
}
