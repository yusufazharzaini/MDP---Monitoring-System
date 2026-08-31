<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DataTransferObjects\DashboardFilter;
use App\DataTransferObjects\DeliveryMetrics;
use App\Enums\OverallDeliveryStatus;
use App\Enums\ProblemSeverity;
use App\Enums\ProblemStatus;
use App\Enums\QuantityStatus;
use App\Enums\TimelinessStatus;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Every dashboard aggregate, expressed as SQL.
 *
 * Nothing here returns a model or a row set proportional to the number of
 * deliveries: a month of 1,250 delivery lines is counted by the database and
 * crosses into PHP as a handful of integers (requirement 33).
 */
class DashboardRepository
{
    public function __construct(
        private readonly DeliveryAggregateQuery $base,
    ) {}

    /**
     * Headline metrics for one period, in two aggregate queries.
     */
    public function metrics(DashboardFilter $filter): DeliveryMetrics
    {
        $counts = $this->base->lines($filter)
            ->selectRaw('COUNT(*) AS total_delivery')
            ->selectRaw($this->base->countWhere('delivery_items.timeliness_status', 'on_time_delivery'), [TimelinessStatus::ON_TIME->value])
            ->selectRaw($this->base->countWhere('delivery_items.timeliness_status', 'late_delivery'), [TimelinessStatus::LATE->value])
            ->selectRaw($this->base->countWhere('delivery_items.quantity_status', 'short_delivery'), [QuantityStatus::SHORT->value])
            ->selectRaw($this->base->countWhere('delivery_items.quantity_status', 'over_delivery'), [QuantityStatus::OVER->value])
            ->first();

        $quantities = $this->base->orderLines($filter)
            ->selectRaw('COALESCE(SUM(purchase_order_items.qty_ordered), 0) AS quantity_ordered')
            ->selectRaw('COALESCE(SUM(purchase_order_items.qty_received), 0) AS quantity_received')
            ->selectRaw($this->shortageExpression().' AS quantity_shortage')
            ->selectRaw($this->excessExpression().' AS quantity_excess')
            ->selectRaw($this->base->countWhere('purchase_order_items.overall_status', 'pending_order_lines'), [OverallDeliveryStatus::PENDING->value])
            ->first();

        return $this->hydrate($counts, $quantities);
    }

    /**
     * The whole trend in two grouped queries, not one query per month.
     *
     * @return array<string, DeliveryMetrics> keyed by 'YYYY-MM'
     */
    public function monthlyMetrics(DashboardFilter $filter): array
    {
        $deliveryMonth = $this->base->monthExpression('deliveries.delivery_date');
        $scheduleMonth = $this->base->monthExpression('purchase_order_items.schedule_delivery_date');

        $counts = $this->base->lines($filter)
            ->selectRaw("{$deliveryMonth} AS period")
            ->selectRaw('COUNT(*) AS total_delivery')
            ->selectRaw($this->base->countWhere('delivery_items.timeliness_status', 'on_time_delivery'), [TimelinessStatus::ON_TIME->value])
            ->selectRaw($this->base->countWhere('delivery_items.timeliness_status', 'late_delivery'), [TimelinessStatus::LATE->value])
            ->selectRaw($this->base->countWhere('delivery_items.quantity_status', 'short_delivery'), [QuantityStatus::SHORT->value])
            ->selectRaw($this->base->countWhere('delivery_items.quantity_status', 'over_delivery'), [QuantityStatus::OVER->value])
            ->groupByRaw($deliveryMonth)
            ->get()
            ->keyBy('period');

        $quantities = $this->base->orderLines($filter)
            ->selectRaw("{$scheduleMonth} AS period")
            ->selectRaw('COALESCE(SUM(purchase_order_items.qty_ordered), 0) AS quantity_ordered')
            ->selectRaw('COALESCE(SUM(purchase_order_items.qty_received), 0) AS quantity_received')
            ->selectRaw($this->shortageExpression().' AS quantity_shortage')
            ->selectRaw($this->excessExpression().' AS quantity_excess')
            ->selectRaw($this->base->countWhere('purchase_order_items.overall_status', 'pending_order_lines'), [OverallDeliveryStatus::PENDING->value])
            ->groupByRaw($scheduleMonth)
            ->get()
            ->keyBy('period');

        return $counts->keys()
            ->merge($quantities->keys())
            ->unique()
            ->mapWithKeys(fn (string $period): array => [
                $period => $this->hydrate($counts->get($period), $quantities->get($period)),
            ])
            ->all();
    }

    /**
     * Per-supplier counts for the ranking table, in one grouped query.
     *
     * @return Collection<int, object>
     */
    public function supplierMetrics(DashboardFilter $filter): Collection
    {
        return $this->base->lines($filter)
            ->join('suppliers', 'suppliers.id', '=', 'deliveries.supplier_id')
            ->selectRaw('suppliers.id AS supplier_id')
            ->selectRaw('suppliers.ulid AS supplier_ulid')
            ->selectRaw('suppliers.code AS supplier_code')
            ->selectRaw('suppliers.name AS supplier_name')
            ->selectRaw('COUNT(*) AS total_delivery')
            ->selectRaw($this->base->countWhere('delivery_items.timeliness_status', 'on_time_delivery'), [TimelinessStatus::ON_TIME->value])
            ->selectRaw($this->base->countWhere('delivery_items.timeliness_status', 'late_delivery'), [TimelinessStatus::LATE->value])
            ->selectRaw($this->base->countWhere('delivery_items.quantity_status', 'short_delivery'), [QuantityStatus::SHORT->value])
            ->groupBy('suppliers.id', 'suppliers.ulid', 'suppliers.code', 'suppliers.name')
            ->get();
    }

    /**
     * One supplier's month-by-month counts, in one grouped query.
     *
     * @return Collection<int, object>
     */
    public function supplierMonthlyMetrics(DashboardFilter $filter, int $supplierId): Collection
    {
        $month = $this->base->monthExpression('deliveries.delivery_date');

        return $this->base->lines($filter)
            ->where('deliveries.supplier_id', $supplierId)
            ->selectRaw("{$month} AS period")
            ->selectRaw('COUNT(*) AS total_delivery')
            ->selectRaw($this->base->countWhere('delivery_items.timeliness_status', 'on_time_delivery'), [TimelinessStatus::ON_TIME->value])
            ->selectRaw($this->base->countWhere('delivery_items.timeliness_status', 'late_delivery'), [TimelinessStatus::LATE->value])
            ->selectRaw($this->base->countWhere('delivery_items.quantity_status', 'short_delivery'), [QuantityStatus::SHORT->value])
            ->groupByRaw($month)
            ->orderByRaw($month)
            ->get();
    }

    /**
     * Problem counts per category - the Pareto population.
     *
     * @return Collection<int, object>
     */
    public function problemFrequency(DashboardFilter $filter): Collection
    {
        return $this->base->problems($filter)
            ->join('problem_categories', 'problem_categories.id', '=', 'delivery_problems.problem_category_id')
            ->selectRaw('problem_categories.id AS category_id')
            ->selectRaw('problem_categories.code AS category_code')
            ->selectRaw('problem_categories.name AS category_name')
            ->selectRaw('COUNT(*) AS problem_count')
            ->groupBy('problem_categories.id', 'problem_categories.code', 'problem_categories.name')
            ->orderByDesc('problem_count')
            ->orderBy('problem_categories.name')
            ->get();
    }

    /**
     * How many problems in the period were closed, and how many of those were
     * closed by their due date - the responsiveness score's inputs.
     */
    public function problemResolution(DashboardFilter $filter): object
    {
        $row = $this->base->problems($filter)
            ->selectRaw('COUNT(*) AS total_problems')
            ->selectRaw($this->base->countWhere('delivery_problems.status', 'closed_problems'), [ProblemStatus::CLOSED->value])
            ->selectRaw(
                'SUM(CASE WHEN delivery_problems.closed_at IS NOT NULL '
                .'AND (delivery_problems.due_date IS NULL OR delivery_problems.closed_at <= delivery_problems.due_date) '
                .'THEN 1 ELSE 0 END) AS resolved_on_time',
            )
            ->first();

        return $row ?? (object) ['total_problems' => 0, 'closed_problems' => 0, 'resolved_on_time' => 0];
    }

    /**
     * The PO delivery monitoring rows, at order-line grain.
     *
     * @return Collection<int, object>
     */
    public function orderLineMonitoring(DashboardFilter $filter, int $limit): Collection
    {
        return $this->base->orderLines($filter)
            ->join('suppliers', 'suppliers.id', '=', 'purchase_orders.supplier_id')
            ->join('materials', 'materials.id', '=', 'purchase_order_items.material_id')
            ->select([
                'purchase_orders.ulid AS purchase_order_ulid',
                'purchase_orders.po_number',
                'suppliers.name AS supplier_name',
                'suppliers.short_name AS supplier_short_name',
                'materials.code AS material_code',
                'materials.name AS material_name',
                'purchase_order_items.schedule_delivery_date',
                'purchase_order_items.last_receipt_date',
                'purchase_order_items.qty_ordered',
                'purchase_order_items.qty_received',
                'purchase_order_items.overall_status',
                'purchase_order_items.remarks',
            ])
            // Most recent first, but within a day the lines that need attention
            // come before the clean ones - a monitoring table that only shows
            // perfect rows is not monitoring anything.
            ->orderByDesc('purchase_order_items.schedule_delivery_date')
            ->orderByRaw('CASE WHEN purchase_order_items.overall_status = ? THEN 1 ELSE 0 END', [OverallDeliveryStatus::ON_TIME_FULL->value])
            ->orderBy('purchase_orders.po_number')
            ->limit($limit)
            ->get();
    }

    /**
     * Materials with at least one late receipt in the period.
     *
     * @return Collection<int, object> material_id => late_count
     */
    public function materialsWithLateReceipts(DashboardFilter $filter): Collection
    {
        return $this->base->lines($filter)
            ->where('delivery_items.timeliness_status', TimelinessStatus::LATE->value)
            ->selectRaw('delivery_items.material_id, COUNT(*) AS late_count')
            ->groupBy('delivery_items.material_id')
            ->get();
    }

    /**
     * Materials with an unfulfilled order line in the period.
     *
     * @return Collection<int, object>
     */
    public function materialsWithShortfall(DashboardFilter $filter): Collection
    {
        return $this->base->orderLines($filter)
            ->where('purchase_order_items.fulfillment_status', QuantityStatus::SHORT->value)
            ->selectRaw('purchase_order_items.material_id, COUNT(*) AS short_count')
            ->selectRaw($this->shortageExpression().' AS shortage_quantity')
            ->groupBy('purchase_order_items.material_id')
            ->get();
    }

    /**
     * Materials with a CRITICAL problem in the period.
     *
     * @return Collection<int, object>
     */
    public function materialsWithCriticalProblems(DashboardFilter $filter): Collection
    {
        return $this->base->problems($filter)
            ->where('delivery_problems.severity', ProblemSeverity::CRITICAL->value)
            ->whereNotNull('delivery_problems.material_id')
            ->selectRaw('delivery_problems.material_id, COUNT(*) AS problem_count')
            ->groupBy('delivery_problems.material_id')
            ->get();
    }

    /**
     * Materials flagged is_critical that saw activity in the period.
     *
     * @return Collection<int, object>
     */
    public function flaggedCriticalMaterials(DashboardFilter $filter): Collection
    {
        return $this->base->lines($filter)
            ->join('materials', 'materials.id', '=', 'delivery_items.material_id')
            ->where('materials.is_critical', true)
            ->selectRaw('materials.id AS material_id, COUNT(*) AS delivery_count')
            ->groupBy('materials.id')
            ->get();
    }

    /**
     * Hydrate the material rows a critical-material list needs to render.
     *
     * @param  array<int, int>  $materialIds
     * @return Collection<int, object>
     */
    public function materialDetails(array $materialIds): Collection
    {
        if ($materialIds === []) {
            return collect();
        }

        return DB::table('materials')
            ->join('material_categories', 'material_categories.id', '=', 'materials.category_id')
            ->join('uoms', 'uoms.id', '=', 'materials.uom_id')
            ->whereIn('materials.id', $materialIds)
            ->select([
                'materials.id',
                'materials.ulid',
                'materials.code',
                'materials.name',
                'materials.is_critical',
                'material_categories.name AS category_name',
                'uoms.code AS uom_code',
            ])
            ->orderBy('materials.code')
            ->get();
    }

    /**
     * Quantity promised but not delivered, never negative.
     */
    private function shortageExpression(): string
    {
        return 'COALESCE(SUM(CASE WHEN purchase_order_items.qty_ordered > purchase_order_items.qty_received '
            .'THEN purchase_order_items.qty_ordered - purchase_order_items.qty_received ELSE 0 END), 0)';
    }

    /**
     * Quantity delivered beyond what was ordered, never negative.
     */
    private function excessExpression(): string
    {
        return 'COALESCE(SUM(CASE WHEN purchase_order_items.qty_received > purchase_order_items.qty_ordered '
            .'THEN purchase_order_items.qty_received - purchase_order_items.qty_ordered ELSE 0 END), 0)';
    }

    private function hydrate(?object $counts, ?object $quantities): DeliveryMetrics
    {
        return new DeliveryMetrics(
            totalDelivery: (int) ($counts->total_delivery ?? 0),
            onTimeDelivery: (int) ($counts->on_time_delivery ?? 0),
            lateDelivery: (int) ($counts->late_delivery ?? 0),
            shortDelivery: (int) ($counts->short_delivery ?? 0),
            overDelivery: (int) ($counts->over_delivery ?? 0),
            quantityOrdered: (float) ($quantities->quantity_ordered ?? 0),
            quantityReceived: (float) ($quantities->quantity_received ?? 0),
            quantityShortage: (float) ($quantities->quantity_shortage ?? 0),
            quantityExcess: (float) ($quantities->quantity_excess ?? 0),
            pendingOrderLines: (int) ($quantities->pending_order_lines ?? 0),
        );
    }
}
