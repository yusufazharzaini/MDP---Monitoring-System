<?php

declare(strict_types=1);

namespace App\Services\Performance\Strategies;

use App\DataTransferObjects\DeliveryMetrics;

/**
 * Punctuality and fulfilment combined:
 *
 *   Service Rate = On Time Rate x w1 + Quantity Fulfillment x w2
 *
 * The weights come from system_settings and are normalised so they always sum
 * to 1 - a pair of weights that does not add up is a configuration slip, not a
 * reason to publish a rate above 100%.
 */
final class WeightedServiceRate implements ServiceRateStrategy
{
    public function __construct(
        private readonly float $onTimeWeight,
        private readonly float $quantityWeight,
    ) {}

    public function calculate(DeliveryMetrics $metrics): float
    {
        $total = $this->onTimeWeight + $this->quantityWeight;

        if ($total <= 0.0) {
            return $metrics->onTimeRate();
        }

        $onTime = $this->onTimeWeight / $total;
        $quantity = $this->quantityWeight / $total;

        return round(
            $metrics->onTimeRate() * $onTime + $metrics->quantityFulfillment() * $quantity,
            2,
        );
    }

    public function key(): string
    {
        return 'weighted';
    }

    public function description(): string
    {
        $total = $this->onTimeWeight + $this->quantityWeight;
        $onTime = $total > 0 ? round($this->onTimeWeight / $total * 100) : 100;
        $quantity = $total > 0 ? round($this->quantityWeight / $total * 100) : 0;

        return "(On Time Rate x {$onTime}%) + (Quantity Fulfillment x {$quantity}%)";
    }
}
