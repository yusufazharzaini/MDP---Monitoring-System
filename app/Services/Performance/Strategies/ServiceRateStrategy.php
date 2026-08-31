<?php

declare(strict_types=1);

namespace App\Services\Performance\Strategies;

use App\DataTransferObjects\DeliveryMetrics;

/**
 * How the business chooses to define its headline service rate.
 *
 * Requirement 16 asks for the default formula today and the option of a
 * weighted one later, without callers changing. Adding a third definition means
 * adding a class here and a value in system_settings - nothing else moves.
 */
interface ServiceRateStrategy
{
    public function calculate(DeliveryMetrics $metrics): float;

    /**
     * Value of `service_rate.formula` that selects this strategy.
     */
    public function key(): string;

    /**
     * Human-readable formula, shown in the dashboard's definition panel so the
     * number on screen always explains itself.
     */
    public function description(): string;
}
