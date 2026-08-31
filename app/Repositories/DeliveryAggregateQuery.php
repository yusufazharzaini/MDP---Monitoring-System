<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DataTransferObjects\DashboardFilter;
use App\Enums\DeliveryStatus;
use App\Enums\PurchaseOrderStatus;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The filtered base queries every dashboard aggregate is built on.
 *
 * These return query-builder instances, never models: the whole point is that
 * a month of delivery lines is counted by the database and only the counts
 * cross into PHP.
 *
 * There are three populations, and they are not interchangeable:
 *
 *  - `lines()`      delivery lines, dated by arrival - the KPI grain
 *  - `orderLines()` purchase order lines, dated by promise - the quantity grain
 *  - `problems()`   reported problems, dated by report - the Pareto grain
 */
class DeliveryAggregateQuery
{
    /**
     * Delivery lines that count towards performance, narrowed by the filter.
     */
    public function lines(DashboardFilter $filter): Builder
    {
        $query = DB::table('delivery_items')
            ->join('deliveries', 'deliveries.id', '=', 'delivery_items.delivery_id')
            ->where('deliveries.status', '!=', DeliveryStatus::CANCELLED->value)
            ->whereBetween('deliveries.delivery_date', [$filter->dateFrom, $filter->dateTo]);

        $this->applyOptional($query, 'deliveries.plant_id', $filter->plantId);
        $this->applyOptional($query, 'deliveries.supplier_id', $filter->supplierId);
        $this->applyOptional($query, 'delivery_items.material_id', $filter->materialId);
        $this->applyOptional($query, 'delivery_items.overall_status', $filter->status);
        $this->applyMaterialCategory($query, 'delivery_items.material_id', $filter);

        return $query;
    }

    /**
     * Purchase order lines whose promised date falls inside the period.
     *
     * Cancelled orders are excluded so this population matches `lines()`.
     */
    public function orderLines(DashboardFilter $filter): Builder
    {
        $query = DB::table('purchase_order_items')
            ->join('purchase_orders', 'purchase_orders.id', '=', 'purchase_order_items.purchase_order_id')
            ->where('purchase_orders.status', '!=', PurchaseOrderStatus::CANCELLED->value)
            ->whereBetween('purchase_order_items.schedule_delivery_date', [$filter->dateFrom, $filter->dateTo]);

        $this->applyOptional($query, 'purchase_orders.plant_id', $filter->plantId);
        $this->applyOptional($query, 'purchase_orders.supplier_id', $filter->supplierId);
        $this->applyOptional($query, 'purchase_order_items.material_id', $filter->materialId);
        $this->applyOptional($query, 'purchase_order_items.overall_status', $filter->status);
        $this->applyMaterialCategory($query, 'purchase_order_items.material_id', $filter);

        return $query;
    }

    /**
     * Delivery problems reported inside the period.
     */
    public function problems(DashboardFilter $filter): Builder
    {
        $query = DB::table('delivery_problems')
            ->whereBetween('delivery_problems.problem_date', [$filter->dateFrom, $filter->dateTo]);

        $this->applyOptional($query, 'delivery_problems.supplier_id', $filter->supplierId);
        $this->applyOptional($query, 'delivery_problems.material_id', $filter->materialId);
        $this->applyOptional($query, 'delivery_problems.status', $filter->status);
        $this->applyMaterialCategory($query, 'delivery_problems.material_id', $filter);

        // A problem carries no plant of its own; it inherits the delivery's.
        if ($filter->plantId !== null) {
            $query->whereExists(
                fn (Builder $sub) => $sub->from('deliveries')
                    ->whereColumn('deliveries.id', 'delivery_problems.delivery_id')
                    ->where('deliveries.plant_id', $filter->plantId),
            );
        }

        return $query;
    }

    /**
     * A driver-portable "YYYY-MM" expression, so a monthly trend is one grouped
     * query rather than one query per month.
     */
    public function monthExpression(string $column): string
    {
        return match (DB::getDriverName()) {
            'sqlite' => "strftime('%Y-%m', {$column})",
            'pgsql' => "to_char({$column}, 'YYYY-MM')",
            default => "DATE_FORMAT({$column}, '%Y-%m')",
        };
    }

    /**
     * SUM over a boolean condition, spelled portably.
     *
     * MySQL would accept SUM(col = 'x'); SQLite and PostgreSQL would not agree
     * on the result type, so every counted condition goes through CASE WHEN.
     */
    public function countWhere(string $column, string $alias): string
    {
        return "SUM(CASE WHEN {$column} = ? THEN 1 ELSE 0 END) AS {$alias}";
    }

    private function applyOptional(Builder $query, string $column, int|string|null $value): void
    {
        if ($value !== null) {
            $query->where($column, $value);
        }
    }

    /**
     * Material category is reached through the material, and only joined when
     * the filter actually asks for it - an unused join is a wasted one.
     */
    private function applyMaterialCategory(Builder $query, string $materialColumn, DashboardFilter $filter): void
    {
        if ($filter->materialCategoryId === null) {
            return;
        }

        $query->whereExists(
            fn (Builder $sub) => $sub->from('materials')
                ->whereColumn('materials.id', $materialColumn)
                ->where('materials.category_id', $filter->materialCategoryId),
        );
    }
}
