<?php

declare(strict_types=1);

namespace App\Services\Performance;

use App\DataTransferObjects\DashboardFilter;
use App\DataTransferObjects\DeliveryMetrics;
use App\Enums\SupplierGrade;
use App\Models\Supplier;
use App\Repositories\DashboardRepository;
use App\Services\Setting\KpiSettingService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Supplier performance: who is delivering well, who is not, and how that has
 * moved over time.
 *
 * Grades are never hard-coded here - they come from kpi_settings through
 * KpiSettingService, so the business can retune the bands without a deploy.
 */
class SupplierPerformanceService
{
    public function __construct(
        private readonly DashboardRepository $repository,
        private readonly DeliveryPerformanceService $performance,
        private readonly KpiSettingService $kpi,
    ) {}

    /**
     * Every supplier active in the period, with its counts and grade.
     *
     * One grouped query, whatever the number of suppliers or deliveries.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function getSupplierPerformance(DashboardFilter $filter): Collection
    {
        return $this->repository->supplierMetrics($filter)->map(function (object $row): array {
            $metrics = new DeliveryMetrics(
                totalDelivery: (int) $row->total_delivery,
                onTimeDelivery: (int) $row->on_time_delivery,
                lateDelivery: (int) $row->late_delivery,
                shortDelivery: (int) $row->short_delivery,
                overDelivery: 0,
                quantityOrdered: 0.0,
                quantityReceived: 0.0,
                quantityShortage: 0.0,
                quantityExcess: 0.0,
                pendingOrderLines: 0,
            );

            $serviceRate = $metrics->onTimeRate();

            return [
                'supplier_id' => (int) $row->supplier_id,
                'supplier_ulid' => $row->supplier_ulid,
                'supplier_code' => $row->supplier_code,
                'supplier_name' => $row->supplier_name,
                'total_delivery' => $metrics->totalDelivery,
                'on_time_delivery' => $metrics->onTimeDelivery,
                'late_delivery' => $metrics->lateDelivery,
                'short_delivery' => $metrics->shortDelivery,
                'service_rate' => $serviceRate,
                'grade' => $this->kpi->gradeFor($serviceRate)->value,
                'grade_label' => $this->kpi->gradeFor($serviceRate)->label(),
                'grade_variant' => $this->kpi->gradeFor($serviceRate)->badgeVariant(),
            ];
        });
    }

    /**
     * The ranking table: best service rate first.
     *
     * The tiebreakers matter. Two suppliers on 100% are not equally proven, so
     * the one with more deliveries ranks higher; name breaks the remaining ties
     * so the order is stable across page loads and snapshot tests.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function getSupplierRanking(DashboardFilter $filter, ?int $limit = null): Collection
    {
        $ranked = $this->getSupplierPerformance($filter)
            ->sort(function (array $a, array $b): int {
                return [$b['service_rate'], $b['total_delivery'], $a['supplier_name']]
                    <=> [$a['service_rate'], $a['total_delivery'], $b['supplier_name']];
            })
            ->values()
            ->map(function (array $row, int $index): array {
                $row['rank'] = $index + 1;

                return $row;
            });

        return $limit === null ? $ranked : $ranked->take($limit)->values();
    }

    /**
     * One supplier's service rate month by month, in one grouped query.
     *
     * Months with no deliveries are returned with a null rate rather than 0%,
     * because "did not deliver" and "delivered badly" are different answers.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getSupplierMonthlyTrend(int $supplierId, DashboardFilter $filter, int $months = 6): array
    {
        $window = $filter->spanningMonths($months);
        $rows = $this->repository->supplierMonthlyMetrics($window, $supplierId)->keyBy('period');

        return array_map(function (DashboardFilter $month) use ($rows): array {
            $period = $month->periodLabel();
            $row = $rows->get($period);

            $total = (int) ($row->total_delivery ?? 0);
            $onTime = (int) ($row->on_time_delivery ?? 0);

            return [
                'period' => $period,
                'label' => Carbon::parse($month->dateFrom)->format('M'),
                'total_delivery' => $total,
                'on_time_delivery' => $onTime,
                'late_delivery' => (int) ($row->late_delivery ?? 0),
                'short_delivery' => (int) ($row->short_delivery ?? 0),
                'service_rate' => $total > 0 ? round($onTime / $total * 100, 2) : null,
            ];
        }, $window->trailingMonths($months));
    }

    /**
     * Everything a supplier scorecard shows: the period's counts, the grade,
     * the trend, and where the problems are concentrated.
     *
     * @return array<string, mixed>
     */
    public function getSupplierScorecard(Supplier $supplier, DashboardFilter $filter, int $months = 6): array
    {
        $scoped = $this->scopedToSupplier($filter, $supplier->getKey());
        $metrics = $this->performance->metrics($scoped);
        $serviceRate = $this->performance->serviceRateFor($metrics);
        $grade = $this->kpi->gradeFor($serviceRate);

        return [
            'supplier' => [
                'id' => $supplier->getKey(),
                'ulid' => $supplier->ulid,
                'code' => $supplier->code,
                'name' => $supplier->name,
                'short_name' => $supplier->short_name,
                'supplier_type' => $supplier->supplier_type->value,
                'lead_time_days' => $supplier->lead_time_days,
            ],
            'period' => $scoped->toArray(),
            'metrics' => $metrics->toArray(),
            'service_rate' => $serviceRate,
            'service_rate_target' => $this->kpi->serviceRateTarget(),
            'meets_target' => $serviceRate >= $this->kpi->serviceRateTarget(),
            'grade' => $grade->value,
            'grade_label' => $grade->label(),
            'grade_variant' => $grade->badgeVariant(),
            'trend' => $this->getSupplierMonthlyTrend($supplier->getKey(), $filter, $months),
            'problem_breakdown' => $this->repository->problemFrequency($scoped)
                ->map(fn (object $row): array => [
                    'category' => $row->category_name,
                    'count' => (int) $row->problem_count,
                ])
                ->all(),
        ];
    }

    /**
     * Grade a service rate using the configured bands.
     */
    public function gradeFor(float $serviceRate): SupplierGrade
    {
        return $this->kpi->gradeFor($serviceRate);
    }

    private function scopedToSupplier(DashboardFilter $filter, int $supplierId): DashboardFilter
    {
        return new DashboardFilter(
            dateFrom: $filter->dateFrom,
            dateTo: $filter->dateTo,
            plantId: $filter->plantId,
            supplierId: $supplierId,
            materialId: $filter->materialId,
            materialCategoryId: $filter->materialCategoryId,
            status: $filter->status,
        );
    }
}
