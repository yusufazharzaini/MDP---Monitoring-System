<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\DataTransferObjects\DashboardFilter;
use App\Enums\OverallDeliveryStatus;
use App\Enums\QuantityStatus;
use App\Enums\TimelinessStatus;
use App\Services\Performance\DeliveryPerformanceService;
use App\Services\Setting\SystemSettingService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class DeliveryPerformanceServiceTest extends TestCase
{
    use RefreshDatabase;

    private DeliveryPerformanceService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedReferenceData();
        $this->service = app(DeliveryPerformanceService::class);
    }

    // --- per-record rules ------------------------------------------------

    #[Test]
    public function it_resolves_the_four_specified_business_rule_cases(): void
    {
        $schedule = Carbon::parse('2026-08-26');

        $cases = [
            ['2026-08-26', 1000.0, OverallDeliveryStatus::ON_TIME_FULL],
            ['2026-08-28', 1000.0, OverallDeliveryStatus::LATE_FULL],
            ['2026-08-26', 950.0, OverallDeliveryStatus::ON_TIME_SHORT],
            ['2026-08-28', 950.0, OverallDeliveryStatus::LATE_SHORT],
        ];

        foreach ($cases as [$actual, $received, $expected]) {
            $timeliness = $this->service->calculateTimeliness(Carbon::parse($actual), $schedule);
            $quantity = $this->service->calculateQuantityStatus(1000, $received);

            $this->assertSame($expected, $this->service->calculateOverallStatus($timeliness, $quantity));
        }
    }

    #[Test]
    public function it_counts_whole_days_late(): void
    {
        $this->assertSame(2, $this->service->calculateLateDays(
            Carbon::parse('2026-08-28'),
            Carbon::parse('2026-08-26'),
        ));
        $this->assertSame(0, $this->service->calculateLateDays(
            Carbon::parse('2026-08-20'),
            Carbon::parse('2026-08-26'),
        ));
    }

    #[Test]
    public function shortage_and_excess_are_never_negative(): void
    {
        $this->assertSame(50.0, $this->service->calculateQuantityShortage(1000, 950));
        $this->assertSame(0.0, $this->service->calculateQuantityShortage(1000, 1200));

        $this->assertSame(200.0, $this->service->calculateQuantityExcess(1000, 1200));
        $this->assertSame(0.0, $this->service->calculateQuantityExcess(1000, 950));
    }

    #[Test]
    public function evaluate_returns_the_whole_verdict_in_one_call(): void
    {
        $verdict = $this->service->evaluate(
            1000,
            950,
            Carbon::parse('2026-08-28'),
            Carbon::parse('2026-08-26'),
        );

        $this->assertSame(TimelinessStatus::LATE, $verdict['timeliness']);
        $this->assertSame(QuantityStatus::SHORT, $verdict['quantity']);
        $this->assertSame(OverallDeliveryStatus::LATE_SHORT, $verdict['overall']);
        $this->assertSame(2, $verdict['days_late']);
        $this->assertSame(50.0, $verdict['shortage']);
        $this->assertSame(0.0, $verdict['excess']);
    }

    // --- aggregates ------------------------------------------------------

    #[Test]
    public function the_seeded_period_reproduces_the_reference_kpis(): void
    {
        $this->seed(DatabaseSeeder::class);

        $metrics = $this->service->metrics(DashboardFilter::currentMonth());

        $this->assertSame(1250, $metrics->totalDelivery);
        $this->assertSame(1210, $metrics->onTimeDelivery);
        $this->assertSame(40, $metrics->lateDelivery);
        $this->assertSame(18, $metrics->shortDelivery);
        $this->assertSame(96.8, $metrics->onTimeRate());
    }

    #[Test]
    public function the_service_rate_follows_the_configured_formula(): void
    {
        $this->seed(DatabaseSeeder::class);
        $filter = DashboardFilter::currentMonth();
        $settings = app(SystemSettingService::class);

        $this->assertSame(
            $this->service->calculateOnTimeRate($filter),
            $this->service->calculateServiceRate($filter),
            'The default formula is punctuality.',
        );

        $settings->set(SystemSettingService::SERVICE_RATE_FORMULA, 'weighted');
        $settings->set(SystemSettingService::SERVICE_RATE_WEIGHT_ON_TIME, 0.5);
        $settings->set(SystemSettingService::SERVICE_RATE_WEIGHT_QUANTITY, 0.5);

        $metrics = $this->service->metrics($filter);
        $expected = round(($metrics->onTimeRate() + $metrics->quantityFulfillment()) / 2, 2);

        $this->assertSame($expected, $this->service->calculateServiceRate($filter));
        $this->assertNotSame($metrics->onTimeRate(), $this->service->calculateServiceRate($filter));
    }

    #[Test]
    public function an_empty_period_reports_zeroes_rather_than_failing(): void
    {
        $metrics = $this->service->metrics(DashboardFilter::fromArray([
            'date_from' => '2000-01-01',
            'date_to' => '2000-01-31',
        ]));

        $this->assertSame(0, $metrics->totalDelivery);
        $this->assertSame(0.0, $metrics->onTimeRate());
        $this->assertSame(0.0, $metrics->quantityFulfillment());
    }

    #[Test]
    public function the_headline_metrics_cost_a_fixed_number_of_queries(): void
    {
        $this->seed(DatabaseSeeder::class);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->service->metrics(DashboardFilter::currentMonth());
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        // 1,250 delivery lines summarised in two aggregate queries - the whole
        // point of requirement 33.
        $this->assertSame(2, $queries);
    }

    #[Test]
    public function the_six_month_trend_is_two_queries_not_twelve(): void
    {
        $this->seed(DatabaseSeeder::class);
        $window = DashboardFilter::currentMonth()->spanningMonths(6);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $monthly = $this->service->monthlyMetrics($window);
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(2, $queries);
        $this->assertCount(6, $monthly);
    }
}
