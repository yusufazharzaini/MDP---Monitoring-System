<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\DataTransferObjects\DashboardFilter;
use App\Enums\SupplierGrade;
use App\Models\Supplier;
use App\Services\Performance\SupplierPerformanceService;
use App\Services\Setting\KpiSettingService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class SupplierPerformanceServiceTest extends TestCase
{
    use RefreshDatabase;

    private SupplierPerformanceService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->service = app(SupplierPerformanceService::class);
    }

    #[Test]
    public function it_reproduces_the_published_supplier_service_rates(): void
    {
        $performance = $this->service->getSupplierPerformance(DashboardFilter::currentMonth())
            ->keyBy('supplier_name');

        $expected = [
            'Supplier A' => 98.4,
            'Supplier B' => 95.0,
            'Supplier C' => 93.2,
            'Supplier D' => 86.7,
            'Supplier E' => 93.3,
        ];

        foreach ($expected as $name => $rate) {
            $this->assertSame(
                $rate,
                round($performance[$name]['service_rate'], 1),
                "{$name} service rate",
            );
        }
    }

    #[Test]
    public function grades_follow_the_configured_bands(): void
    {
        $performance = $this->service->getSupplierPerformance(DashboardFilter::currentMonth())
            ->keyBy('supplier_name');

        $this->assertSame(SupplierGrade::EXCELLENT->value, $performance['Supplier A']['grade']);
        $this->assertSame(SupplierGrade::GOOD->value, $performance['Supplier B']['grade']);
        $this->assertSame(SupplierGrade::AVERAGE->value, $performance['Supplier C']['grade']);
        $this->assertSame(SupplierGrade::POOR->value, $performance['Supplier D']['grade']);
    }

    #[Test]
    public function retuning_the_bands_regrades_without_a_deploy(): void
    {
        DB::table('kpi_settings')->where('code', 'GRADE_EXCELLENT')->update(['target_value' => 99]);
        app(KpiSettingService::class)->flush();

        $performance = $this->service->getSupplierPerformance(DashboardFilter::currentMonth())
            ->keyBy('supplier_name');

        // Supplier A is 98.4% - excellent under a 98 band, merely good under 99.
        $this->assertSame(SupplierGrade::GOOD->value, $performance['Supplier A']['grade']);
    }

    #[Test]
    public function the_ranking_is_ordered_with_deterministic_tiebreakers(): void
    {
        $ranking = $this->service->getSupplierRanking(DashboardFilter::currentMonth());

        $this->assertSame(range(1, $ranking->count()), $ranking->pluck('rank')->all());

        // Three suppliers are on 100%; volume then name must settle the order.
        $perfect = $ranking->where('service_rate', 100.0)->values();
        $this->assertGreaterThan(1, $perfect->count());

        $volumes = $perfect->pluck('total_delivery')->all();
        $sorted = $volumes;
        rsort($sorted);
        $this->assertSame($sorted, $volumes, 'Equal rates must be broken by volume.');
    }

    #[Test]
    public function the_ranking_can_be_limited_to_the_top_n(): void
    {
        $top = $this->service->getSupplierRanking(DashboardFilter::currentMonth(), 3);

        $this->assertCount(3, $top);
        $this->assertSame([1, 2, 3], $top->pluck('rank')->all());
    }

    #[Test]
    public function the_monthly_trend_covers_every_month_in_the_window(): void
    {
        $supplier = Supplier::query()->where('code', 'SUP-001')->firstOrFail();

        $trend = $this->service->getSupplierMonthlyTrend(
            $supplier->getKey(),
            DashboardFilter::currentMonth(),
            6,
        );

        $this->assertCount(6, $trend);
        $this->assertSame(now()->format('Y-m'), $trend[5]['period']);
        $this->assertSame(250, $trend[5]['total_delivery']);
        $this->assertSame(98.4, round($trend[5]['service_rate'], 1));
    }

    #[Test]
    public function a_month_the_supplier_did_not_deliver_in_reports_no_rate(): void
    {
        $supplier = Supplier::factory()->create();

        $trend = $this->service->getSupplierMonthlyTrend(
            $supplier->getKey(),
            DashboardFilter::currentMonth(),
            3,
        );

        foreach ($trend as $point) {
            $this->assertNull($point['service_rate'], 'No deliveries is not the same as 0% service.');
        }
    }

    #[Test]
    public function the_scorecard_gathers_metrics_grade_trend_and_problems(): void
    {
        $supplier = Supplier::query()->where('code', 'SUP-004')->firstOrFail();

        $card = $this->service->getSupplierScorecard($supplier, DashboardFilter::currentMonth());

        $this->assertSame($supplier->code, $card['supplier']['code']);
        $this->assertSame(30, $card['metrics']['total_delivery']);
        $this->assertSame(86.7, round($card['service_rate'], 1));
        $this->assertSame(SupplierGrade::POOR->value, $card['grade']);
        $this->assertFalse($card['meets_target']);
        $this->assertCount(6, $card['trend']);
        $this->assertIsArray($card['problem_breakdown']);
    }

    #[Test]
    public function the_ranking_is_one_grouped_query_however_many_suppliers(): void
    {
        // Warm the KPI threshold cache first: its one lookup is a fixed cost,
        // not a per-supplier one, and measuring it here would hide the property
        // this test exists to prove.
        $this->service->getSupplierPerformance(DashboardFilter::currentMonth());

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->service->getSupplierPerformance(DashboardFilter::currentMonth());
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(1, $queries, 'Supplier ranking must not query per supplier.');
    }
}
