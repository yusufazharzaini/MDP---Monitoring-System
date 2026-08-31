<?php

declare(strict_types=1);

namespace App\Services\Performance;

use App\DataTransferObjects\DeliveryMetrics;
use App\Services\Performance\Strategies\OnTimeServiceRate;
use App\Services\Performance\Strategies\ServiceRateStrategy;
use App\Services\Performance\Strategies\WeightedServiceRate;
use App\Services\Setting\SystemSettingService;

/**
 * Resolves the configured service-rate formula and applies it.
 *
 * Callers ask for a number; which definition produced it is the business's
 * decision, held in system_settings rather than in code.
 */
class ServiceRateCalculator
{
    public function __construct(
        private readonly SystemSettingService $settings,
    ) {}

    public function calculate(DeliveryMetrics $metrics): float
    {
        return $this->strategy()->calculate($metrics);
    }

    public function strategy(): ServiceRateStrategy
    {
        $configured = $this->settings->string(SystemSettingService::SERVICE_RATE_FORMULA, 'on_time');

        return match ($configured) {
            'weighted' => new WeightedServiceRate(
                $this->settings->float(SystemSettingService::SERVICE_RATE_WEIGHT_ON_TIME, 0.5),
                $this->settings->float(SystemSettingService::SERVICE_RATE_WEIGHT_QUANTITY, 0.5),
            ),
            default => new OnTimeServiceRate,
        };
    }

    /**
     * The formula in words, for the dashboard's definition panel.
     */
    public function formulaDescription(): string
    {
        return $this->strategy()->description();
    }
}
