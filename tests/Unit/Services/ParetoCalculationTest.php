<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Repositories\DashboardRepository;
use App\Services\Dashboard\ParetoAnalysisService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The Pareto arithmetic of requirement 18, tested without a database.
 *
 * The repository is never touched by these methods, so a null-safe stand-in is
 * enough to construct the service.
 */
final class ParetoCalculationTest extends TestCase
{
    private ParetoAnalysisService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new ParetoAnalysisService(
            $this->createStub(DashboardRepository::class),
        );
    }

    #[Test]
    public function a_share_is_the_count_over_the_total(): void
    {
        $this->assertSame(45.78, $this->service->calculatePercentage(38, 83));
        $this->assertSame(100.0, $this->service->calculatePercentage(83, 83));
    }

    #[Test]
    public function an_empty_period_yields_zero_rather_than_dividing_by_zero(): void
    {
        $this->assertSame(0.0, $this->service->calculatePercentage(0, 0));
        $this->assertSame([0.0, 0.0], $this->service->calculateCumulativePercentage([0, 0]));
    }

    #[Test]
    public function the_cumulative_curve_reproduces_the_reference_chart(): void
    {
        // 38 / 24 / 12 / 6 / 3 out of 83 problems.
        $cumulative = $this->service->calculateCumulativePercentage([38, 24, 12, 6, 3]);

        $this->assertSame([45.78, 74.7, 89.16, 96.39, 100.0], $cumulative);
        $this->assertSame([46.0, 75.0, 89.0, 96.0, 100.0], array_map(
            static fn (float $v): float => round($v),
            $cumulative,
        ));
    }

    #[Test]
    public function the_curve_always_ends_at_one_hundred(): void
    {
        foreach ([[1], [5, 3, 2], [100, 1], [7, 7, 7]] as $counts) {
            $cumulative = $this->service->calculateCumulativePercentage($counts);

            $this->assertSame(100.0, end($cumulative), 'Cumulative percentage must close at 100.');
        }
    }

    #[Test]
    public function the_curve_never_decreases(): void
    {
        $cumulative = $this->service->calculateCumulativePercentage([38, 24, 12, 6, 3]);
        $previous = 0.0;

        foreach ($cumulative as $value) {
            $this->assertGreaterThanOrEqual($previous, $value);
            $previous = $value;
        }
    }

    #[Test]
    public function the_cumulative_curve_follows_the_order_it_is_given(): void
    {
        // Ranking is the caller's job; the service must not silently re-sort,
        // because a curve computed over an unsorted list is not a Pareto chart.
        $this->assertSame(
            [3.61, 10.84, 25.3, 54.22, 100.0],
            $this->service->calculateCumulativePercentage([3, 6, 12, 24, 38]),
        );
    }
}
