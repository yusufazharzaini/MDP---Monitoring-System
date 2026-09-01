<?php

declare(strict_types=1);

namespace Tests\Feature\Performance;

use App\DataTransferObjects\DashboardFilter;
use App\Models\Delivery;
use App\Models\Material;
use App\Models\Plant;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Services\Dashboard\DashboardCache;
use App\Services\Dashboard\DashboardService;
use App\Services\Delivery\DeliveryService;
use App\Services\PurchaseOrder\PurchaseOrderService;
use App\Services\Setting\KpiSettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The dashboard cache, and the writes that must retire it.
 *
 * A cached operations screen is only useful if it cannot outlive the data
 * underneath it: a clerk books goods and then watches numbers that do not
 * include them is worse than no cache at all. These tests run against a real
 * cache store, because phpunit.xml's array driver dies with each process and a
 * stale read cannot surface there.
 */
final class DashboardCacheTest extends TestCase
{
    use RefreshDatabase;

    private DashboardService $dashboard;

    private DashboardCache $cache;

    private Plant $plant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedReferenceData();
        Carbon::setTestNow('2026-08-26 09:00:00');

        config(['cache.default' => 'database', 'mdp.dashboard.cache_ttl' => 300]);
        Cache::store('database')->clear();

        $this->dashboard = app(DashboardService::class);
        $this->cache = app(DashboardCache::class);
        $this->plant = Plant::factory()->create();
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

    private function receive(float $qty = 100): Delivery
    {
        $material = Material::factory()->create();
        $buyer = $this->userWithRole('PURCHASING');

        $orders = app(PurchaseOrderService::class);
        $order = $orders->create(
            [
                'po_date' => '2026-08-01',
                'supplier_id' => Supplier::factory()->create()->getKey(),
                'plant_id' => $this->plant->getKey(),
                'currency' => 'IDR',
            ],
            [[
                'material_id' => $material->getKey(),
                'warehouse_id' => Warehouse::factory()->forPlant($this->plant)->create()->getKey(),
                'uom_id' => $material->uom_id,
                'schedule_delivery_date' => '2026-08-26',
                'qty_ordered' => $qty,
                'unit_price' => 1000,
            ]],
            $buyer,
        );

        $orders->submit($order, $buyer);
        $orders->approve($order, $this->userWithRole('MANAGEMENT'));

        /** @var PurchaseOrder $order */
        $line = $order->items()->firstOrFail();

        return app(DeliveryService::class)->receive(
            $order,
            ['delivery_date' => '2026-08-26'],
            [['purchase_order_item_id' => $line->getKey(), 'qty_received' => $qty, 'condition' => 'GOOD']],
            $this->userWithRole('WAREHOUSE'),
        );
    }

    #[Test]
    public function a_repeated_payload_is_served_without_touching_the_database(): void
    {
        $this->receive();

        $cold = $this->businessQueriesFor(fn () => $this->dashboard->payload($this->filter()));
        $warm = $this->businessQueriesFor(fn () => $this->dashboard->payload($this->filter()));

        // The whole point of the cache: the second read touches no business
        // table at all. It is not zero queries outright - the database cache
        // store reads its own table - so what is measured is the aggregate
        // work, which is the expensive half.
        $this->assertGreaterThan(10, $cold);
        $this->assertSame(0, $warm);
    }

    /**
     * Queries against the business tables, ignoring the cache store's own.
     *
     * @param  callable():mixed  $work
     */
    private function businessQueriesFor(callable $work): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $work();

        // Quote-agnostic: SQLite writes "cache" and MySQL writes `cache`, and
        // the cache store's own reads are not the work being measured.
        $queries = collect(DB::getQueryLog())
            ->reject(fn (array $entry): bool => preg_match('/["`\[]cache["`\]]/', $entry['query']) === 1)
            ->count();

        DB::disableQueryLog();

        return $queries;
    }

    #[Test]
    public function booking_a_receipt_retires_the_cached_dashboard(): void
    {
        $this->receive();
        $before = $this->dashboard->getSummary($this->filter())['total_delivery'];

        $this->receive();
        $after = $this->dashboard->getSummary($this->filter())['total_delivery'];

        // Without invalidation this stayed at 1 for the whole TTL, and a clerk
        // watched five minutes of numbers that did not include their receipt.
        $this->assertSame(1, $before);
        $this->assertSame(2, $after);
    }

    #[Test]
    public function correcting_a_receipt_retires_it_too(): void
    {
        $delivery = $this->receive(100);
        $this->assertSame(100.0, $this->dashboard->getSummary($this->filter())['quantity_received']);

        app(DeliveryService::class)->update(
            $delivery,
            ['delivery_date' => '2026-08-26'],
            [[
                'purchase_order_item_id' => $delivery->items()->value('purchase_order_item_id'),
                'qty_received' => 60,
                'condition' => 'GOOD',
            ]],
            $this->userWithRole('WAREHOUSE'),
        );

        $this->assertSame(60.0, $this->dashboard->getSummary($this->filter())['quantity_received']);
    }

    #[Test]
    public function cancelling_a_receipt_retires_it_too(): void
    {
        $delivery = $this->receive();
        $this->assertSame(1, $this->dashboard->getSummary($this->filter())['total_delivery']);

        app(DeliveryService::class)->cancel($delivery, $this->userWithRole('LOGISTIC'), 'Salah input');

        $this->assertSame(0, $this->dashboard->getSummary($this->filter())['total_delivery']);
    }

    #[Test]
    public function retuning_a_kpi_threshold_retires_the_cached_verdicts(): void
    {
        $this->receive();
        $this->assertTrue($this->dashboard->getSummary($this->filter())['target_met']);

        DB::table('kpi_settings')->where('code', 'SERVICE_RATE')->update(['target_value' => 101]);
        app(KpiSettingService::class)->flush();

        // A cached payload carries the old target baked into its verdict.
        $summary = $this->dashboard->getSummary($this->filter());
        $this->assertSame(101.0, $summary['target']);
        $this->assertFalse($summary['target_met']);
    }

    #[Test]
    public function the_version_stamp_retires_every_filter_at_once(): void
    {
        $this->receive();

        $august = $this->filter();
        $narrowed = DashboardFilter::fromArray([
            'date_from' => '2026-08-01',
            'date_to' => '2026-08-31',
            'plant_id' => $this->plant->getKey(),
        ]);

        $this->dashboard->getSummary($august);
        $this->dashboard->getSummary($narrowed);
        $version = $this->cache->version();

        $this->receive();

        // One bump, every cached payload retired - no tags needed, which the
        // database and file stores do not support anyway.
        $this->assertSame($version + 1, $this->cache->version());
        $this->assertSame(2, $this->dashboard->getSummary($august)['total_delivery']);
        $this->assertSame(2, $this->dashboard->getSummary($narrowed)['total_delivery']);
    }

    #[Test]
    public function a_zero_ttl_disables_caching_entirely(): void
    {
        config(['mdp.dashboard.cache_ttl' => 0]);
        $this->receive();

        $this->dashboard->getSummary($this->filter());

        // An operator who turns the cache off gets fresh numbers every time.
        $this->assertGreaterThan(
            0,
            $this->businessQueriesFor(fn () => $this->dashboard->getSummary($this->filter())),
        );
    }
}
