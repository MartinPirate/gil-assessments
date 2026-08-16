<?php

namespace App\Filament\Widgets\Concerns;

use Carbon\CarbonImmutable;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

/**
 * Lets a widget honour the dashboard's date filter.
 *
 * The filter bar had been putting its two dates on the page and no widget was
 * reading them, so changing the range changed nothing on screen — a control
 * that looks like it works is worse than no control at all.
 *
 * Also gives the preceding period of the same length, which is what a
 * percentage change has to be measured against: "up 12%" means nothing unless
 * you say since when.
 */
trait ReadsTheDashboardRange
{
    use InteractsWithPageFilters;

    protected function rangeFrom(): CarbonImmutable
    {
        $from = $this->pageFilters['from'] ?? null;

        return $from
            ? CarbonImmutable::parse($from)->startOfDay()
            : CarbonImmutable::now()->subDays(29)->startOfDay();
    }

    protected function rangeUntil(): CarbonImmutable
    {
        $until = $this->pageFilters['until'] ?? null;

        return $until
            ? CarbonImmutable::parse($until)->endOfDay()
            : CarbonImmutable::now()->endOfDay();
    }

    /**
     * The window of the same length immediately before the current one, so
     * "against the previous 30 days" means exactly that.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    protected function previousRange(): array
    {
        $from = $this->rangeFrom();
        $until = $this->rangeUntil();

        // At least a day, so a single-day range still has something to compare
        // against rather than dividing by nothing.
        $days = max(1, $from->diffInDays($until) + 1);

        return [
            $from->subDays($days),
            $from->subSecond(),
        ];
    }

    protected function rangeLabel(): string
    {
        return $this->rangeFrom()->format('d/m/Y').' – '.$this->rangeUntil()->format('d/m/Y');
    }

    /**
     * Percentage change between two figures.
     *
     * Null when there is nothing to compare against: growth from zero is not
     * "infinite percent", it is a number nobody should be shown.
     */
    protected function percentageChange(float $now, float $before): ?float
    {
        if ($before <= 0.0) {
            return null;
        }

        return round((($now - $before) / $before) * 100, 1);
    }
}
