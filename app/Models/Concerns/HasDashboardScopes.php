<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\DataTransferObjects\DashboardFilter;
use Illuminate\Database\Eloquent\Builder;

/**
 * Period filtering shared by every model the dashboard aggregates over.
 *
 * Each model names the date column that represents "when it happened" for
 * reporting purposes - which is not always the obvious one. A delivery is
 * measured on the day the goods arrived; a purchase order line is measured on
 * the day it was promised.
 *
 * @method static Builder<static> betweenDates(string $from, string $to)
 * @method static Builder<static> forPeriod(DashboardFilter $filter)
 */
trait HasDashboardScopes
{
    /**
     * Fully qualified date column this model is reported on.
     */
    abstract public function dashboardDateColumn(): string;

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeBetweenDates(Builder $query, string $from, string $to): Builder
    {
        return $query->whereBetween($this->dashboardDateColumn(), [$from, $to]);
    }

    /**
     * Narrow to the filter's period only, leaving its other criteria alone.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForPeriod(Builder $query, DashboardFilter $filter): Builder
    {
        return $query->betweenDates($filter->dateFrom, $filter->dateTo);
    }

    /**
     * Apply an optional equality filter, skipping nulls.
     *
     * Keeps the calling scopes free of repetitive `if ($value !== null)`.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    protected function whereOptional(Builder $query, string $column, int|string|null $value): Builder
    {
        return $value === null ? $query : $query->where($column, $value);
    }
}
