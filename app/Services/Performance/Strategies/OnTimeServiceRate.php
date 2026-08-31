<?php

declare(strict_types=1);

namespace App\Services\Performance\Strategies;

use App\DataTransferObjects\DeliveryMetrics;

/**
 * The default: service rate is punctuality.
 *
 *   Service Rate = On Time Delivery / Total Delivery x 100
 */
final class OnTimeServiceRate implements ServiceRateStrategy
{
    public function calculate(DeliveryMetrics $metrics): float
    {
        return $metrics->onTimeRate();
    }

    public function key(): string
    {
        return 'on_time';
    }

    public function description(): string
    {
        return 'On Time Delivery / Total Delivery x 100';
    }
}
