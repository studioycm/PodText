<?php

namespace App\Enums;

use Carbon\CarbonImmutable;
use Filament\Support\Contracts\HasLabel;

enum DashboardRange: string implements HasLabel
{
    case Last7Days = 'last_7_days';
    case Last30Days = 'last_30_days';
    case Last60Days = 'last_60_days';

    private const TIMEZONE = 'Asia/Jerusalem';

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
        $today = CarbonImmutable::now(self::TIMEZONE);

        return [
            $today->subDays($this->days() - 1)->startOfDay()->utc(),
            $today->endOfDay()->utc(),
        ];
    }

    /** @return array{CarbonImmutable, CarbonImmutable} */
    public function previousPeriod(): array
    {
        [$currentStart] = $this->currentPeriod();
        $previousEnd = $currentStart->subSecond();
        $previousStart = $previousEnd->setTimezone(self::TIMEZONE)
            ->subDays($this->days() - 1)
            ->startOfDay()
            ->utc();

        return [$previousStart, $previousEnd];
    }
}
