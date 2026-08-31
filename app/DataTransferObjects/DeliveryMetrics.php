<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

/**
 * The raw counts behind every delivery KPI, gathered in one database round trip.
 *
 * Every rate on the dashboard is derived from this object rather than from its
 * own query, which is what stops the cards disagreeing with each other.
 *
 * Two grains are represented deliberately, because the specification defines
 * them that way:
 *
 *  - counts (total/on time/late/short/over) are at the **delivery line** grain,
 *    measured on the date the goods arrived;
 *  - quantities (ordered/received/shortage/excess) are at the **order line**
 *    grain, measured on the date the goods were promised.
 */
final readonly class DeliveryMetrics
{
    public function __construct(
        public int $totalDelivery,
        public int $onTimeDelivery,
        public int $lateDelivery,
        public int $shortDelivery,
        public int $overDelivery,
        public float $quantityOrdered,
        public float $quantityReceived,
        public float $quantityShortage,
        public float $quantityExcess,
        public int $pendingOrderLines,
    ) {}

    public static function empty(): self
    {
        return new self(0, 0, 0, 0, 0, 0.0, 0.0, 0.0, 0.0, 0);
    }

    /**
     * On Time Delivery / Total Delivery x 100.
     */
    public function onTimeRate(): float
    {
        return $this->percentage($this->onTimeDelivery, $this->totalDelivery);
    }

    public function lateRate(): float
    {
        return $this->percentage($this->lateDelivery, $this->totalDelivery);
    }

    public function shortRate(): float
    {
        return $this->percentage($this->shortDelivery, $this->totalDelivery);
    }

    /**
     * Total Quantity Received / Total Quantity Ordered x 100.
     *
     * Capped at 100: an over-delivery does not make a supplier more than fully
     * compliant, and letting it push the figure past 100 would mask a shortfall
     * elsewhere in the same period.
     */
    public function quantityFulfillment(): float
    {
        if ($this->quantityOrdered <= 0.0) {
            return 0.0;
        }

        return round(min(100.0, $this->quantityReceived / $this->quantityOrdered * 100), 2);
    }

    public function hasActivity(): bool
    {
        return $this->totalDelivery > 0;
    }

    /**
     * @return array<string, float|int>
     */
    public function toArray(): array
    {
        return [
            'total_delivery' => $this->totalDelivery,
            'on_time_delivery' => $this->onTimeDelivery,
            'late_delivery' => $this->lateDelivery,
            'short_delivery' => $this->shortDelivery,
            'over_delivery' => $this->overDelivery,
            'pending_order_lines' => $this->pendingOrderLines,
            'quantity_ordered' => round($this->quantityOrdered, 4),
            'quantity_received' => round($this->quantityReceived, 4),
            'quantity_shortage' => round($this->quantityShortage, 4),
            'quantity_excess' => round($this->quantityExcess, 4),
            'on_time_rate' => $this->onTimeRate(),
            'late_rate' => $this->lateRate(),
            'quantity_fulfillment' => $this->quantityFulfillment(),
        ];
    }

    private function percentage(int $part, int $whole): float
    {
        return $whole <= 0 ? 0.0 : round($part / $whole * 100, 2);
    }
}
