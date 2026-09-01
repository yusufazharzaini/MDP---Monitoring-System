<?php

declare(strict_types=1);

namespace Tests\Feature\Performance;

use App\DataTransferObjects\DashboardFilter;
use App\Enums\DeliveryItemCondition;
use App\Enums\SupplierGrade;
use App\Models\Material;
use App\Models\Plant;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Delivery\DeliveryService;
use App\Services\Performance\SupplierPerformanceService;
use App\Services\PurchaseOrder\PurchaseOrderService;
use App\Services\Setting\KpiSettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The ranking table - Phase 7's exit criterion.
 *
 * The existing service tests prove the arithmetic against the seeded demo
 * figures. These build the population by hand instead, because the questions
 * here are about ordering and edges: what happens on a tie, exactly on a grade
 * boundary, and for a supplier that did not deliver at all. Those are the cases
 * a league table gets quietly wrong.
 */
final class SupplierRankingTest extends TestCase
{
    use RefreshDatabase;

    private SupplierPerformanceService $service;

    private Plant $plant;

    private Warehouse $warehouse;

    private Material $material;

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedReferenceData();
        Carbon::setTestNow('2026-08-26 09:00:00');

        $this->service = app(SupplierPerformanceService::class);
        $this->actor = $this->userWithRole('WAREHOUSE');
        $this->plant = Plant::factory()->create();
        $this->warehouse = Warehouse::factory()->forPlant($this->plant)->create();
        $this->material = Material::factory()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function filter(): DashboardFilter
    {
        return DashboardFilter::fromArray(['date_from' => '2026-08-01', 'date_to' => '2026-08-31']);
    }

    /**
     * Give a supplier a run of receipts: `$onTime` punctual and `$late` late,
     * which fixes its service rate exactly.
     */
    private function supplierWith(string $name, int $onTime, int $late): Supplier
    {
        $supplier = Supplier::factory()->create(['name' => $name]);

        for ($i = 0; $i < $onTime + $late; $i++) {
            $this->receipt($supplier, isLate: $i >= $onTime);
        }

        return $supplier;
    }

    private function receipt(Supplier $supplier, bool $isLate): void
    {
        $orders = app(PurchaseOrderService::class);
        $buyer = $this->userWithRole('PURCHASING');

        $order = $orders->create(
            [
                'po_date' => '2026-08-01',
                'supplier_id' => $supplier->getKey(),
                'plant_id' => $this->plant->getKey(),
                'currency' => 'IDR',
            ],
            [[
                'material_id' => $this->material->getKey(),
                'warehouse_id' => $this->warehouse->getKey(),
                'uom_id' => $this->material->uom_id,
                // A receipt on 2026-08-20 is late against a schedule of the
                // 15th and on time against the 25th.
                'schedule_delivery_date' => $isLate ? '2026-08-15' : '2026-08-25',
                'qty_ordered' => 100,
                'unit_price' => 1000,
            ]],
            $buyer,
        );

        $orders->submit($order, $buyer);
        $orders->approve($order, $this->userWithRole('MANAGEMENT'));

        /** @var PurchaseOrder $order */
        $line = $order->items()->firstOrFail();

        app(DeliveryService::class)->receive(
            $order,
            ['delivery_date' => '2026-08-20'],
            [[
                'purchase_order_item_id' => $line->getKey(),
                'qty_received' => 100,
                'condition' => DeliveryItemCondition::GOOD->value,
            ]],
            $this->actor,
        );
    }

    #[Test]
    public function the_ranking_orders_by_service_rate_descending(): void
    {
        $this->supplierWith('Middle', onTime: 8, late: 2);   // 80%
        $this->supplierWith('Best', onTime: 10, late: 0);    // 100%
        $this->supplierWith('Worst', onTime: 5, late: 5);    // 50%

        $ranking = $this->service->getSupplierRanking($this->filter());

        $this->assertSame(['Best', 'Middle', 'Worst'], $ranking->pluck('supplier_name')->all());
        $this->assertSame([1, 2, 3], $ranking->pluck('rank')->all());
    }

    #[Test]
    public function a_tie_on_service_rate_is_broken_by_the_larger_sample(): void
    {
        // Both are perfect, but ten deliveries prove more than two.
        $this->supplierWith('Lightly proven', onTime: 2, late: 0);
        $this->supplierWith('Heavily proven', onTime: 10, late: 0);

        $ranking = $this->service->getSupplierRanking($this->filter());

        $this->assertSame(100.0, $ranking[0]['service_rate']);
        $this->assertSame(100.0, $ranking[1]['service_rate']);
        $this->assertSame('Heavily proven', $ranking[0]['supplier_name']);
        $this->assertSame('Lightly proven', $ranking[1]['supplier_name']);
    }

    #[Test]
    public function a_tie_on_rate_and_volume_is_broken_by_name_so_the_order_is_stable(): void
    {
        $this->supplierWith('Zulu Manufaktur', onTime: 4, late: 0);
        $this->supplierWith('Alfa Manufaktur', onTime: 4, late: 0);

        $first = $this->service->getSupplierRanking($this->filter())->pluck('supplier_name')->all();
        $second = $this->service->getSupplierRanking($this->filter())->pluck('supplier_name')->all();

        $this->assertSame(['Alfa Manufaktur', 'Zulu Manufaktur'], $first);
        // Same answer twice: a league table that reshuffles between page loads
        // is not a league table.
        $this->assertSame($first, $second);
    }

    #[Test]
    public function a_supplier_exactly_on_a_grade_boundary_takes_the_better_grade(): void
    {
        // GOOD floors at 95: 19 of 20 on time is exactly 95.0%.
        $this->supplierWith('On the line', onTime: 19, late: 1);

        $row = $this->service->getSupplierRanking($this->filter())->firstOrFail();

        $this->assertSame(95.0, $row['service_rate']);
        $this->assertSame(SupplierGrade::GOOD->value, $row['grade']);
    }

    #[Test]
    public function a_supplier_just_below_a_boundary_takes_the_lower_grade(): void
    {
        // 18 of 20 is 90.0% - the AVERAGE floor, not GOOD.
        $this->supplierWith('Just under', onTime: 18, late: 2);

        $row = $this->service->getSupplierRanking($this->filter())->firstOrFail();

        $this->assertSame(90.0, $row['service_rate']);
        $this->assertSame(SupplierGrade::AVERAGE->value, $row['grade']);
    }

    #[Test]
    public function retuning_a_band_moves_the_grade_without_touching_the_rate(): void
    {
        $this->supplierWith('On the line', onTime: 19, late: 1);

        $this->assertSame(
            SupplierGrade::GOOD->value,
            $this->service->getSupplierRanking($this->filter())->firstOrFail()['grade'],
        );

        DB::table('kpi_settings')->where('code', 'GRADE_GOOD')->update(['target_value' => 96]);
        app(KpiSettingService::class)->flush();

        $row = $this->service->getSupplierRanking($this->filter())->firstOrFail();

        $this->assertSame(95.0, $row['service_rate'], 'the rate is a fact, not a setting');
        $this->assertSame(SupplierGrade::AVERAGE->value, $row['grade']);
    }

    #[Test]
    public function a_supplier_with_no_deliveries_is_absent_rather_than_ranked_at_zero(): void
    {
        $this->supplierWith('Delivered', onTime: 5, late: 0);
        $dormant = Supplier::factory()->create(['name' => 'Dormant']);

        $names = $this->service->getSupplierRanking($this->filter())->pluck('supplier_name')->all();

        // Ranking a supplier that never delivered at 0% would read as terrible
        // performance rather than as no data, and would drag a report's average
        // down with a number that means nothing.
        $this->assertSame(['Delivered'], $names);
        $this->assertNotContains($dormant->name, $names);
    }

    #[Test]
    public function a_supplier_outside_the_period_does_not_appear(): void
    {
        $this->supplierWith('August', onTime: 3, late: 0);

        $july = DashboardFilter::fromArray(['date_from' => '2026-07-01', 'date_to' => '2026-07-31']);

        $this->assertCount(0, $this->service->getSupplierRanking($july));
    }

    #[Test]
    public function the_ranking_can_be_capped_without_disturbing_the_order(): void
    {
        $this->supplierWith('Best', onTime: 10, late: 0);
        $this->supplierWith('Middle', onTime: 8, late: 2);
        $this->supplierWith('Worst', onTime: 5, late: 5);

        $top = $this->service->getSupplierRanking($this->filter(), limit: 2);

        $this->assertCount(2, $top);
        $this->assertSame(['Best', 'Middle'], $top->pluck('supplier_name')->all());
        $this->assertSame([1, 2], $top->pluck('rank')->all());
    }

    #[Test]
    public function the_ranking_does_not_grow_a_query_per_supplier(): void
    {
        $this->supplierWith('A', onTime: 3, late: 1);
        $this->supplierWith('B', onTime: 3, late: 1);

        // Warm the KPI threshold cache first. Its single lookup is a fixed cost
        // paid once per request, and counting it here would measure the cache
        // rather than the property this test exists to prove.
        $this->service->getSupplierRanking($this->filter());

        $withTwo = $this->countQueries(fn () => $this->service->getSupplierRanking($this->filter()));

        $this->supplierWith('C', onTime: 3, late: 1);
        $this->supplierWith('D', onTime: 3, late: 1);
        $this->supplierWith('E', onTime: 3, late: 1);

        $withFive = $this->countQueries(fn () => $this->service->getSupplierRanking($this->filter()));

        $this->assertSame(1, $withTwo, 'the ranking is one grouped aggregate');
        $this->assertSame(
            $withTwo,
            $withFive,
            'the ranking cost must not depend on how many suppliers there are',
        );
    }

    private function countQueries(callable $work): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $work();

        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    #[Test]
    public function the_scorecard_reports_the_same_rate_the_ranking_does(): void
    {
        $supplier = $this->supplierWith('Consistent', onTime: 9, late: 1);

        $ranked = $this->service->getSupplierRanking($this->filter())->firstOrFail();
        $scorecard = $this->service->getSupplierScorecard($supplier, $this->filter());

        // Two panels quoting different numbers for the same supplier is the
        // failure this system exists to avoid.
        $this->assertSame($ranked['service_rate'], $scorecard['service_rate']);
        $this->assertSame($ranked['grade'], $scorecard['grade']);
    }

    #[Test]
    public function the_grade_bands_come_from_settings_rather_than_the_page(): void
    {
        $bands = $this->service->gradeBands();

        $this->assertSame(['EXCELLENT', 'GOOD', 'AVERAGE', 'POOR'], array_column($bands, 'grade'));
        $this->assertSame([98.0, 95.0, 90.0, 0.0], array_column($bands, 'floor'));
        // Each band stops where the one above it starts, so the legend cannot
        // describe a gap or an overlap.
        $this->assertSame([null, 98.0, 95.0, 90.0], array_column($bands, 'ceiling'));

        DB::table('kpi_settings')->where('code', 'GRADE_EXCELLENT')->update(['target_value' => 99]);
        app(KpiSettingService::class)->flush();

        $this->assertSame(99.0, $this->service->gradeBands()[0]['floor']);
    }
}
