<?php

declare(strict_types=1);

namespace App\Services\Performance;

use App\DataTransferObjects\DashboardFilter;
use App\DataTransferObjects\DeliveryMetrics;
use App\Enums\OverallDeliveryStatus;
use App\Enums\QuantityStatus;
use App\Enums\TimelinessStatus;
use App\Repositories\DashboardRepository;
use App\Services\Delivery\DeliveryStatusCalculator;
use Carbon\CarbonInterface;

/**
 * The delivery performance rules, at both grains the business asks about.
 *
 * The per-record methods delegate to DeliveryStatusCalculator, which is a pure
 * class with no database access - that is what makes the rules in docs/03
 * directly unit-testable and what stops two different definitions of "late"
 * appearing in the codebase.
 *
 * The aggregate methods go through DashboardRepository, so a month of delivery
 * lines is counted by the database and only the counts reach PHP.
 */
class DeliveryPerformanceService
{
    public function __construct(
        private readonly DeliveryStatusCalculator $calculator,
        private readonly DashboardRepository $repository,
        private readonly ServiceRateCalculator $serviceRate,
    ) {}

    // ---------------------------------------------------------------------
    // Per-record rules
    // ---------------------------------------------------------------------

    /**
     * ON_TIME when the goods arrived on or before the promised day.
     */
    public function calculateTimeliness(?CarbonInterface $actualDate, ?CarbonInterface $scheduleDate): TimelinessStatus
    {
        return $this->calculator->timelinessStatus($actualDate, $scheduleDate);
    }

    /**
     * PENDING / SHORT / FULL / OVER against the ordered quantity.
     */
    public function calculateQuantityStatus(float $ordered, float $received): QuantityStatus
    {
        return $this->calculator->quantityStatus($ordered, $received);
    }

    /**
     * The combined verdict the dashboard and PO monitoring table display.
     */
    public function calculateOverallStatus(TimelinessStatus $timeliness, QuantityStatus $quantity): OverallDeliveryStatus
    {
        return $this->calculator->overall($timeliness, $quantity);
    }

    /**
     * Whole days past the promised date; zero when on time.
     */
    public function calculateLateDays(?CarbonInterface $actualDate, ?CarbonInterface $scheduleDate): int
    {
        return $this->calculator->daysLate($actualDate, $scheduleDate);
    }

    /**
     * Quantity promised but not delivered. Never negative: an over-delivery is
     * an excess, not a negative shortage.
     */
    public function calculateQuantityShortage(float $ordered, float $received): float
    {
        return round(max(0.0, $ordered - $received), 4);
    }

    /**
     * Quantity delivered beyond what was ordered. Never negative.
     */
    public function calculateQuantityExcess(float $ordered, float $received): float
    {
        return round(max(0.0, $received - $ordered), 4);
    }

    /**
     * Evaluate a whole receipt in one call.
     *
     * @return array{
     *     timeliness: TimelinessStatus,
     *     quantity: QuantityStatus,
     *     overall: OverallDeliveryStatus,
     *     days_late: int,
     *     shortage: float,
     *     excess: float
     * }
     */
    public function evaluate(
        float $ordered,
        float $received,
        ?CarbonInterface $actualDate,
        ?CarbonInterface $scheduleDate,
    ): array {
        return [
            ...$this->calculator->evaluate($ordered, $received, $actualDate, $scheduleDate),
            'shortage' => $this->calculateQuantityShortage($ordered, $received),
            'excess' => $this->calculateQuantityExcess($ordered, $received),
        ];
    }

    // ---------------------------------------------------------------------
    // Aggregates over a filter
    // ---------------------------------------------------------------------

    /**
     * Every headline count for a period, in one pass.
     *
     * Prefer this over calling the three rate methods separately: they each
     * derive from these counts, so one call is one round trip instead of three.
     */
    public function metrics(DashboardFilter $filter): DeliveryMetrics
    {
        return $this->repository->metrics($filter);
    }

    /**
     * On Time Delivery / Total Delivery x 100.
     */
    public function calculateOnTimeRate(DashboardFilter $filter): float
    {
        return $this->metrics($filter)->onTimeRate();
    }

    /**
     * Total Quantity Received / Total Quantity Ordered x 100.
     */
    public function calculateQuantityFulfillment(DashboardFilter $filter): float
    {
        return $this->metrics($filter)->quantityFulfillment();
    }

    /**
     * The configured service-rate formula, applied to the period's counts.
     */
    public function calculateServiceRate(DashboardFilter $filter): float
    {
        return $this->serviceRate->calculate($this->metrics($filter));
    }

    /**
     * Service rate straight from already-gathered counts, for callers that have
     * them - the trend, the supplier ranking - so nothing is queried twice.
     */
    public function serviceRateFor(DeliveryMetrics $metrics): float
    {
        return $this->serviceRate->calculate($metrics);
    }

    public function serviceRateFormula(): string
    {
        return $this->serviceRate->formulaDescription();
    }

    /**
     * Month-by-month counts across the filter's range, in one grouped query.
     *
     * @return array<string, DeliveryMetrics> keyed by 'YYYY-MM'
     */
    public function monthlyMetrics(DashboardFilter $filter): array
    {
        return $this->repository->monthlyMetrics($filter);
    }
}
