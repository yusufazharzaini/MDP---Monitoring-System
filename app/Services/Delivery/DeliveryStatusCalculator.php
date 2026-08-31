<?php

declare(strict_types=1);

namespace App\Services\Delivery;

use App\Enums\OverallDeliveryStatus;
use App\Enums\QuantityStatus;
use App\Enums\TimelinessStatus;
use Carbon\CarbonInterface;

/**
 * The delivery performance rules, expressed as pure functions.
 *
 * This class touches no database and holds no state beyond its configured
 * over-delivery tolerance, which is what makes the rules in docs/03 directly
 * unit-testable.
 */
class DeliveryStatusCalculator
{
    /**
     * Quantities are stored as decimal(18,4); compare with half-of-last-digit
     * tolerance so binary float drift never turns an exact receipt into SHORT.
     */
    private const EPSILON = 0.00005;

    /**
     * @param  float  $overTolerancePercent  Receipts above the ordered quantity but within
     *                                       this percentage still count as FULL (assumption A12).
     */
    public function __construct(
        private readonly float $overTolerancePercent = 0.0,
    ) {}

    /**
     * Fulfilment of a received quantity against what was ordered.
     */
    public function quantityStatus(float $ordered, float $received): QuantityStatus
    {
        if ($received <= self::EPSILON) {
            return QuantityStatus::PENDING;
        }

        if ($received < $ordered - self::EPSILON) {
            return QuantityStatus::SHORT;
        }

        $overThreshold = $ordered * (1 + $this->overTolerancePercent / 100);

        if ($received > $overThreshold + self::EPSILON) {
            return QuantityStatus::OVER;
        }

        return QuantityStatus::FULL;
    }

    /**
     * Punctuality of a receipt against its scheduled date. Compared date-only:
     * arriving any time on the scheduled day is on time.
     */
    public function timelinessStatus(?CarbonInterface $actual, ?CarbonInterface $schedule): TimelinessStatus
    {
        if ($actual === null || $schedule === null) {
            return TimelinessStatus::PENDING;
        }

        // copy() first: Eloquent hands back mutable Carbon instances, and
        // startOfDay() would otherwise rewrite the model's own attribute.
        return $actual->copy()->startOfDay()->lessThanOrEqualTo($schedule->copy()->startOfDay())
            ? TimelinessStatus::ON_TIME
            : TimelinessStatus::LATE;
    }

    /**
     * Whole days past schedule; zero when on time or not yet delivered.
     */
    public function daysLate(?CarbonInterface $actual, ?CarbonInterface $schedule): int
    {
        if ($actual === null || $schedule === null) {
            return 0;
        }

        $difference = $schedule->copy()->startOfDay()->diffInDays($actual->copy()->startOfDay(), false);

        return max(0, (int) $difference);
    }

    /**
     * Combines punctuality and fulfilment into the single verdict the dashboard
     * and PO monitoring table display.
     */
    public function overall(TimelinessStatus $timeliness, QuantityStatus $quantity): OverallDeliveryStatus
    {
        if ($quantity === QuantityStatus::PENDING || $timeliness === TimelinessStatus::PENDING) {
            return OverallDeliveryStatus::PENDING;
        }

        if ($quantity === QuantityStatus::OVER) {
            return OverallDeliveryStatus::OVER_DELIVERY;
        }

        return match ([$timeliness, $quantity]) {
            [TimelinessStatus::ON_TIME, QuantityStatus::FULL] => OverallDeliveryStatus::ON_TIME_FULL,
            [TimelinessStatus::ON_TIME, QuantityStatus::SHORT] => OverallDeliveryStatus::ON_TIME_SHORT,
            [TimelinessStatus::LATE, QuantityStatus::FULL] => OverallDeliveryStatus::LATE_FULL,
            [TimelinessStatus::LATE, QuantityStatus::SHORT] => OverallDeliveryStatus::LATE_SHORT,
            default => OverallDeliveryStatus::PENDING,
        };
    }

    /**
     * Convenience wrapper evaluating a whole receipt in one call.
     *
     * @return array{
     *     timeliness: TimelinessStatus,
     *     quantity: QuantityStatus,
     *     overall: OverallDeliveryStatus,
     *     days_late: int
     * }
     */
    public function evaluate(
        float $ordered,
        float $received,
        ?CarbonInterface $actualDate,
        ?CarbonInterface $scheduleDate,
    ): array {
        $quantity = $this->quantityStatus($ordered, $received);
        $timeliness = $this->timelinessStatus($actualDate, $scheduleDate);

        return [
            'timeliness' => $timeliness,
            'quantity' => $quantity,
            'overall' => $this->overall($timeliness, $quantity),
            'days_late' => $this->daysLate($actualDate, $scheduleDate),
        ];
    }

    public function overTolerancePercent(): float
    {
        return $this->overTolerancePercent;
    }
}
