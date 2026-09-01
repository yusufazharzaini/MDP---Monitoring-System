<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\DataTransferObjects\DashboardFilter;
use App\Models\Supplier;
use App\Services\Dashboard\DashboardService;
use App\Services\Setting\KpiSettingService;
use App\Services\Setting\SystemSettingService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The dashboard payload of requirement 32, and the guarantee that every panel
 * describes the same population.
 */
final class DashboardServiceTest extends TestCase
{
    use RefreshDatabase;

    private DashboardService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->service = app(DashboardService::class);
    }

    #[Test]
    public function the_payload_carries_every_key_the_contract_promises(): void
    {
        $payload = $this->service->payload(DashboardFilter::currentMonth());

        foreach ([
            'filters', 'summary', 'trend', 'supplier_performance',
            'pareto', 'recent_deliveries', 'critical_materials', 'definitions',
        ] as $key) {
            $this->assertArrayHasKey($key, $payload);
        }
    }

    #[Test]
    public function the_summary_reproduces_the_reference_kpi_cards(): void
    {
        $summary = $this->service->getSummary(DashboardFilter::currentMonth());

        $this->assertSame(96.8, $summary['service_rate']);
        $this->assertSame(1250, $summary['total_delivery']);
        $this->assertSame(1210, $summary['on_time_delivery']);
        $this->assertSame(40, $summary['late_delivery']);
        $this->assertSame(18, $summary['short_delivery']);
        $this->assertSame(7, $summary['critical_material']);
        $this->assertTrue($summary['target_met']);
        $this->assertSame('success', $summary['severity']);
    }

    #[Test]
    public function the_target_comes_from_kpi_settings_not_from_code(): void
    {
        $summary = $this->service->getSummary(DashboardFilter::currentMonth());
        $this->assertSame(95.0, $summary['target']);

        DB::table('kpi_settings')->where('code', 'SERVICE_RATE')->update(['target_value' => 99]);
        app(KpiSettingService::class)->flush();

        $retuned = $this->service->getSummary(DashboardFilter::currentMonth());
        $this->assertSame(99.0, $retuned['target']);
        $this->assertFalse($retuned['target_met'], 'A 96.8% rate must miss a 99% target.');
    }

    #[Test]
    public function the_trend_reproduces_the_reference_service_rate_line(): void
    {
        $trend = $this->service->getServiceRateTrend(DashboardFilter::currentMonth());

        $this->assertCount(6, $trend);
        // The service keeps two decimals; the chart rounds to one for display.
        $this->assertSame(
            [97.2, 96.5, 98.1, 95.8, 97.0, 96.8],
            array_map(static fn (array $point): float => round($point['service_rate'], 1), $trend),
        );
        // The window ends at the month the seed is anchored to. Hard-coding
        // '2026-08' made this test fail on the 1st of September for no reason
        // any reader could act on - the demo data is seeded relative to today.
        $this->assertSame(now()->format('Y-m'), $trend[5]['period'] ?? null);
    }

    #[Test]
    public function a_month_with_no_deliveries_reports_no_rate_rather_than_zero_percent(): void
    {
        $trend = $this->service->getServiceRateTrend(
            DashboardFilter::fromArray(['period' => '2020-06']),
        );

        foreach ($trend as $point) {
            $this->assertNull($point['service_rate'], 'An empty month is missing data, not 0% service.');
            $this->assertSame(0, $point['total_delivery']);
        }
    }

    #[Test]
    public function the_supplier_ranking_is_ordered_and_graded(): void
    {
        $ranking = $this->service->getSupplierPerformance(DashboardFilter::currentMonth());

        $this->assertCount(5, $ranking, 'The dashboard shows the configured top N.');
        $this->assertSame(1, $ranking[0]['rank']);

        $rates = array_column($ranking, 'service_rate');
        $sorted = $rates;
        rsort($sorted);
        $this->assertSame($sorted, $rates, 'Ranking must be ordered by service rate.');
    }

    #[Test]
    public function the_pareto_dataset_reproduces_the_reference_chart(): void
    {
        $pareto = $this->service->getPareto(DashboardFilter::currentMonth());

        $this->assertSame(83, $pareto['total_problems']);
        $this->assertSame(
            [46.0, 75.0, 89.0, 96.0, 100.0],
            array_map(
                static fn (array $c): float => round($c['cumulative_percentage']),
                $pareto['categories'],
            ),
        );
    }

    #[Test]
    public function the_monitoring_table_surfaces_lines_that_need_attention(): void
    {
        $rows = $this->service->getRecentDeliveries(DashboardFilter::currentMonth(), 5);

        $this->assertNotEmpty($rows);
        $this->assertNotSame(
            ['ON_TIME_FULL'],
            array_unique(array_column($rows, 'overall_status')),
            'A monitoring table showing only clean rows is not monitoring anything.',
        );

        foreach ($rows as $row) {
            $this->assertNotEmpty($row['remarks'], 'Every row must explain its status.');
        }
    }

    #[Test]
    public function every_panel_narrows_to_the_same_supplier_when_filtered(): void
    {
        $supplier = Supplier::query()->where('code', 'SUP-001')->firstOrFail();
        $filter = DashboardFilter::fromArray([
            'period' => now()->format('Y-m'),
            'supplier_id' => $supplier->getKey(),
        ]);

        $payload = $this->service->payload($filter);

        // Supplier A's published figures: 250 deliveries, 246 on time.
        $this->assertSame(250, $payload['summary']['total_delivery']);
        $this->assertSame(246, $payload['summary']['on_time_delivery']);

        $this->assertCount(1, $payload['supplier_performance']);
        $this->assertSame($supplier->getKey(), $payload['supplier_performance'][0]['supplier_id']);

        foreach ($payload['recent_deliveries'] as $row) {
            $this->assertSame($supplier->short_name, $row['supplier']);
        }
    }

    #[Test]
    public function the_definition_panel_states_the_formula_actually_in_use(): void
    {
        $definitions = $this->service->getDefinitions();
        $serviceRate = collect($definitions)->firstWhere('title', 'Service Rate');

        $this->assertSame('On Time Delivery / Total Delivery x 100', $serviceRate['formula']);

        app(SystemSettingService::class)
            ->set(SystemSettingService::SERVICE_RATE_FORMULA, 'weighted');

        $updated = collect($this->service->getDefinitions())->firstWhere('title', 'Service Rate');
        $this->assertStringContainsString('Quantity Fulfillment', $updated['formula']);
    }

    #[Test]
    public function the_whole_payload_costs_a_fixed_number_of_queries(): void
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->service->payload(DashboardFilter::currentMonth());
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Every panel is an aggregate. The count must not depend on how many
        // deliveries the month contains.
        $this->assertLessThanOrEqual(
            14,
            $queries,
            "The dashboard took {$queries} queries; an aggregate dashboard should need a handful.",
        );
    }
}
