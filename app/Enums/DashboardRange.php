<?php

namespace App\Enums;

use App\Support\UiTimezone;
use Carbon\CarbonImmutable;
use Filament\Support\Contracts\HasLabel;

enum DashboardRange: string implements HasLabel
{
    case Last7Days = 'last_7_days';
    case Last30Days = 'last_30_days';
    case Last60Days = 'last_60_days';

    public static function fromFilter(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::Last30Days;
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $range): array => [$range->value => $range->getLabel()])
            ->all();
    }

    public function days(): int
    {
        return match ($this) {
            self::Last7Days => 7,
            self::Last30Days => 30,
            self::Last60Days => 60,
        };
    }

    public function getLabel(): string
    {
        return __("admin.dashboard.ranges.{$this->value}", ['days' => $this->days()]);
    }

    /**
     * Editorial "today" is the Jerusalem day; boundaries are computed on
     * Jerusalem walls and returned as UTC instants for querying.
     *
     * @return array{CarbonImmutable, CarbonImmutable}
     */
    public function currentPeriod(): array
    {
        $today = CarbonImmutable::now(UiTimezone::name());

        return [
            $today->subDays($this->days() - 1)->startOfDay()->utc(),
            $today->endOfDay()->utc(),
        ];
    }

    /**
     * The Jerusalem days covered by the current period, oldest first. Every
     * daily series on the board is aligned to this list, so a sparkline, a
     * heatmap cell and a stream row all mean the same day.
     *
     * @return array<int, string>
     */
    public function dayKeys(): array
    {
        $today = CarbonImmutable::now(UiTimezone::name())->startOfDay();

        return collect(range($this->days() - 1, 0))
            ->map(fn (int $daysAgo): string => $today->subDays($daysAgo)->format('Y-m-d'))
            ->all();
    }

    /** @return array{CarbonImmutable, CarbonImmutable} */
    public function previousPeriod(): array
    {
        [$currentStart] = $this->currentPeriod();
        $previousEnd = $currentStart->subSecond();
        $previousStart = $previousEnd->setTimezone(UiTimezone::name())
            ->subDays($this->days() - 1)
            ->startOfDay()
            ->utc();

        return [$previousStart, $previousEnd];
    }
}
