<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\DataTransferObjects\DashboardFilter;
use App\Enums\OverallDeliveryStatus;
use App\Repositories\DashboardRepository;
use App\Services\Performance\DeliveryPerformanceService;
use App\Services\Performance\SupplierPerformanceService;
use App\Services\Setting\KpiSettingService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Assembles the dashboard payload defined in requirement 32.
 *
 * Every panel is narrowed by the same DashboardFilter, so the KPI cards, the
 * trend, the ranking, the Pareto chart and the monitoring table describe one
 * population and cannot contradict each other.
 */
class DashboardService
{
    public function __construct(
        private readonly DashboardRepository $repository,
        private readonly DeliveryPerformanceService $performance,
        private readonly SupplierPerformanceService $suppliers,
        private readonly ParetoAnalysisService $pareto,
        private readonly CriticalMaterialService $criticalMaterials,
        private readonly KpiSettingService $kpi,
    ) {}

    /**
     * The whole contract, in one call.
     *
     * @return array<string, mixed>
     */
    public function payload(DashboardFilter $filter): array
    {
        return $this->remember($filter, 'dashboard.payload', function () use ($filter): array {
            // Gathered once and shared with the summary: the four
            // critical-material rules are four queries, and running them again
            // just to fill in a KPI card would double that for no new answer.
            $criticalMaterials = $this->criticalMaterials->getCriticalMaterials($filter);

            return [
                'filters' => $filter->toArray(),
                'summary' => $this->getSummary($filter, $criticalMaterials->count()),
                'trend' => $this->getServiceRateTrend($filter),
                'supplier_performance' => $this->getSupplierPerformance($filter),
                'pareto' => $this->getPareto($filter),
                'recent_deliveries' => $this->getRecentDeliveries($filter),
                'critical_materials' => $criticalMaterials->values()->all(),
                'definitions' => $this->getDefinitions(),
            ];
        });
    }

    /**
     * The six KPI cards.
     *
     * @param  int|null  $criticalMaterialCount  Pass a count already gathered by the
     *                                           caller to avoid re-running the four
     *                                           critical-material rule queries.
     * @return array<string, mixed>
     */
    public function getSummary(DashboardFilter $filter, ?int $criticalMaterialCount = null): array
    {
        $metrics = $this->performance->metrics($filter);
        $serviceRate = $this->performance->serviceRateFor($metrics);
        $target = $this->kpi->serviceRateTarget();
        $setting = $this->kpi->find('SERVICE_RATE');

        return [
            ...$metrics->toArray(),
            'service_rate' => $serviceRate,
            'critical_material' => $criticalMaterialCount ?? $this->criticalMaterials->countCriticalMaterials($filter),
            'target' => $target,
            'target_met' => $serviceRate >= $target,
            'severity' => $setting?->severityFor($serviceRate) ?? 'info',
        ];
    }

    /**
     * Service rate month by month, in one grouped query over the whole window.
     *
     * Months with no deliveries carry a null rate rather than 0%: an empty
     * month is missing data, not a total failure to deliver.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getServiceRateTrend(DashboardFilter $filter, ?int $months = null): array
    {
        $months ??= (int) config('mdp.dashboard.trend_months', 6);
        $window = $filter->spanningMonths($months);
        $monthly = $this->performance->monthlyMetrics($window);
        $target = $this->kpi->serviceRateTarget();

        return array_map(function (DashboardFilter $month) use ($monthly, $target): array {
            $period = $month->periodLabel();
            $metrics = $monthly[$period] ?? null;

            return [
                'period' => $period,
                'label' => Carbon::parse($month->dateFrom)->format('M'),
                'total_delivery' => $metrics?->totalDelivery ?? 0,
                'on_time_delivery' => $metrics?->onTimeDelivery ?? 0,
                'late_delivery' => $metrics?->lateDelivery ?? 0,
                'service_rate' => $metrics?->hasActivity()
                    ? $this->performance->serviceRateFor($metrics)
                    : null,
                'target' => $target,
            ];
        }, $window->trailingMonths($months));
    }

    /**
     * The supplier ranking table, limited to the top N by default.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getSupplierPerformance(DashboardFilter $filter, ?int $limit = null): array
    {
        $limit ??= (int) config('mdp.dashboard.supplier_ranking_limit', 5);

        return $this->suppliers->getSupplierRanking($filter, $limit)->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function getPareto(DashboardFilter $filter): array
    {
        return $this->pareto->generateParetoDataset($filter);
    }

    /**
     * The PO delivery monitoring table, at order-line grain.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getRecentDeliveries(DashboardFilter $filter, ?int $limit = null): array
    {
        $limit ??= (int) config('mdp.dashboard.recent_delivery_limit', 10);

        return $this->repository->orderLineMonitoring($filter, $limit)
            ->values()
            ->map(function (object $row, int $index): array {
                $status = OverallDeliveryStatus::from($row->overall_status);

                return [
                    'no' => $index + 1,
                    'purchase_order_ulid' => $row->purchase_order_ulid,
                    'po_number' => $row->po_number,
                    'supplier' => $row->supplier_short_name ?: $row->supplier_name,
                    'material' => $row->material_name,
                    'material_code' => $row->material_code,
                    'schedule_delivery_date' => $row->schedule_delivery_date,
                    'actual_delivery_date' => $row->last_receipt_date,
                    'qty_ordered' => (float) $row->qty_ordered,
                    'qty_received' => (float) $row->qty_received,
                    'overall_status' => $status->value,
                    'status_label' => $status->label(),
                    'status_variant' => $status->badgeVariant(),
                    'remarks' => $row->remarks ?: $this->remarkFor($row, $status),
                ];
            })
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getCriticalMaterials(DashboardFilter $filter, ?int $limit = null): array
    {
        $materials = $this->criticalMaterials->getCriticalMaterials($filter);

        return ($limit === null ? $materials : $materials->take($limit))->values()->all();
    }

    /**
     * The formula panel, so every number on screen can explain itself.
     *
     * @return array<int, array<string, string>>
     */
    public function getDefinitions(): array
    {
        return [
            [
                'title' => 'On Time Rate',
                'description' => 'Persentase delivery yang diterima sesuai dengan schedule.',
                'formula' => 'On Time Delivery / Total Delivery x 100%',
            ],
            [
                'title' => 'Quantity Fulfillment',
                'description' => 'Persentase quantity yang diterima sesuai dengan PO.',
                'formula' => 'Qty Receive / Qty PO x 100%',
            ],
            [
                'title' => 'Service Rate',
                'description' => 'Formula service rate yang sedang aktif.',
                'formula' => $this->performance->serviceRateFormula(),
            ],
        ];
    }

    /**
     * Cache an assembled payload when a TTL is configured. Any write through
     * DeliveryStatusService flushes this by tag-free key expiry, so a stale
     * dashboard is bounded by the TTL rather than indefinite.
     *
     * @param  callable():array<string, mixed>  $callback
     * @return array<string, mixed>
     */
    private function remember(DashboardFilter $filter, string $prefix, callable $callback): array
    {
        $ttl = (int) config('mdp.dashboard.cache_ttl', 0);

        if ($ttl <= 0) {
            return $callback();
        }

        return Cache::remember($filter->cacheKey($prefix), $ttl, $callback);
    }

    /**
     * Fill the monitoring table's remarks column when the row has none of its
     * own, so the reason a line is flagged is always visible.
     */
    private function remarkFor(object $row, OverallDeliveryStatus $status): string
    {
        $shortfall = (float) $row->qty_ordered - (float) $row->qty_received;

        return match ($status) {
            OverallDeliveryStatus::PENDING => 'Belum ada delivery',
            OverallDeliveryStatus::ON_TIME_FULL => '-',
            OverallDeliveryStatus::OVER_DELIVERY => 'Kelebihan '.abs(round($shortfall, 2)),
            OverallDeliveryStatus::ON_TIME_SHORT => 'Kurang '.round($shortfall, 2),
            OverallDeliveryStatus::LATE_FULL => $this->lateRemark($row),
            OverallDeliveryStatus::LATE_SHORT => $this->lateRemark($row).', kurang '.round($shortfall, 2),
        };
    }

    private function lateRemark(object $row): string
    {
        if ($row->last_receipt_date === null) {
            return 'Terlambat';
        }

        $days = Carbon::parse($row->schedule_delivery_date)
            ->diffInDays(Carbon::parse($row->last_receipt_date), false);

        return 'Terlambat '.max(0, (int) $days).' hari';
    }
}
