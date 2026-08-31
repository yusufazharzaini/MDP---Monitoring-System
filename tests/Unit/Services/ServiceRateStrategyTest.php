<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\DataTransferObjects\DeliveryMetrics;
use App\Services\Performance\Strategies\OnTimeServiceRate;
use App\Services\Performance\Strategies\WeightedServiceRate;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The service rate formulas of requirement 16, tested as pure functions.
 */
final class ServiceRateStrategyTest extends TestCase
{
    private function metrics(int $total, int $onTime, float $ordered = 0, float $received = 0): DeliveryMetrics
    {
        return new DeliveryMetrics(
            totalDelivery: $total,
            onTimeDelivery: $onTime,
            lateDelivery: $total - $onTime,
            shortDelivery: 0,
            overDelivery: 0,
            quantityOrdered: $ordered,
            quantityReceived: $received,
            quantityShortage: max(0.0, $ordered - $received),
            quantityExcess: max(0.0, $received - $ordered),
            pendingOrderLines: 0,
        );
    }

    #[Test]
    public function the_default_formula_is_punctuality(): void
    {
        // The reference dashboard's headline: 1210 / 1250 = 96.8%.
        $this->assertSame(96.8, (new OnTimeServiceRate)->calculate($this->metrics(1250, 1210)));
    }

    #[Test]
    public function the_default_formula_ignores_quantity(): void
    {
        $strategy = new OnTimeServiceRate;

        $this->assertSame(
            $strategy->calculate($this->metrics(100, 95, 1000, 1000)),
            $strategy->calculate($this->metrics(100, 95, 1000, 500)),
        );
    }

    #[Test]
    public function the_weighted_formula_blends_punctuality_and_fulfilment(): void
    {
        $strategy = new WeightedServiceRate(0.5, 0.5);

        // 90% on time, 100% fulfilled -> 95%.
        $this->assertSame(95.0, $strategy->calculate($this->metrics(100, 90, 1000, 1000)));
    }

    #[Test]
    public function weights_are_normalised_so_they_cannot_inflate_the_rate(): void
    {
        // 3:1 given as 30 and 10 must behave exactly like 0.75 and 0.25.
        $asFractions = new WeightedServiceRate(0.75, 0.25);
        $asPercentages = new WeightedServiceRate(30.0, 10.0);
        $metrics = $this->metrics(100, 80, 1000, 900);

        $this->assertSame(
            $asFractions->calculate($metrics),
            $asPercentages->calculate($metrics),
        );
        $this->assertLessThanOrEqual(100.0, $asPercentages->calculate($metrics));
    }

    #[Test]
    public function zero_weights_fall_back_to_punctuality_rather_than_dividing_by_zero(): void
    {
        $strategy = new WeightedServiceRate(0.0, 0.0);

        $this->assertSame(80.0, $strategy->calculate($this->metrics(100, 80, 1000, 500)));
    }

    #[Test]
    public function a_period_with_no_deliveries_scores_zero_not_an_error(): void
    {
        $this->assertSame(0.0, (new OnTimeServiceRate)->calculate(DeliveryMetrics::empty()));
        $this->assertSame(0.0, (new WeightedServiceRate(0.5, 0.5))->calculate(DeliveryMetrics::empty()));
    }

    #[Test]
    public function each_strategy_describes_the_formula_it_applies(): void
    {
        $this->assertSame('On Time Delivery / Total Delivery x 100', (new OnTimeServiceRate)->description());
        $this->assertStringContainsString('75%', (new WeightedServiceRate(0.75, 0.25))->description());
    }
}
