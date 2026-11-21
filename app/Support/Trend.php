<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/** Small helpers for "+9% from last month" numbers and monthly chart series. */
class Trend
{
    /** Percentage change from $previous to $current (rounded). 0 → n is reported as +100%. */
    public static function change(int|float $current, int|float $previous): int
    {
        if ($previous == 0) {
            return $current > 0 ? 100 : 0;
        }

        return (int) round(($current - $previous) / $previous * 100);
    }

    public static function rate(int $done, int $total): int
    {
        return $total > 0 ? (int) round($done / $total * 100) : 0;
    }

    /**
     * The last $count month starts, oldest first (includes the current month).
     *
     * @return Collection<int, CarbonImmutable>
     */
    public static function months(int $count, ?CarbonImmutable $now = null): Collection
    {
        $now = ($now ?? CarbonImmutable::now())->startOfMonth();

        return collect(range($count - 1, 0))->map(fn (int $back) => $now->subMonths($back));
    }
}
