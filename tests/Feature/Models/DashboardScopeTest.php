<?php

declare(strict_types=1);

namespace Tests\Feature\Models;

use App\DataTransferObjects\DashboardFilter;
use App\Enums\DeliveryStatus;
use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\DeliveryProblem;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\Plant;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Requirement 7: query scopes for the dashboard filter.
 *
 * Requirement 21 of the master specification says every dashboard panel must
 * narrow its population the same way, so the KPI cards, ranking, Pareto chart
 * and PO monitoring table never disagree. These tests hold the models to that:
 * one DashboardFilter, applied through scopeForDashboard, on every model the
 * dashboard reads.
 */
final class DashboardScopeTest extends TestCase
{
    use RefreshDatabase;

    private Supplier $target;

    private Supplier $other;

    private Plant $plant;

    private Material $material;

    private MaterialCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedReferenceData();

        $this->target = Supplier::factory()->create(['name' => 'Target Supplier']);
        $this->other = Supplier::factory()->create(['name' => 'Other Supplier']);
        $this->plant = Plant::factory()->create();
        $this->category = MaterialCategory::factory()->create();
        $this->material = Material::factory()->create(['category_id' => $this->category->getKey()]);

        // One receipt inside the period for each supplier, plus one outside it.
        $this->receipt($this->target, '2026-08-10');
        $this->receipt($this->other, '2026-08-11');
        $this->receipt($this->target, '2026-07-15');
    }

    private function filter(array $overrides = []): DashboardFilter
    {
        return DashboardFilter::fromArray([
            'date_from' => '2026-08-01',
            'date_to' => '2026-08-31',
            ...$overrides,
        ]);
    }

    #[Test]
    public function the_period_alone_narrows_every_model_consistently(): void
    {
        $filter = $this->filter();

        $this->assertSame(2, Delivery::query()->forDashboard($filter)->count());
        $this->assertSame(2, DeliveryItem::query()->forDashboard($filter)->count());
        $this->assertSame(2, PurchaseOrderItem::query()->forDashboard($filter)->count());
        $this->assertSame(2, DeliveryProblem::query()->forDashboard($filter)->count());
    }

    #[Test]
    public function the_supplier_filter_reaches_every_model(): void
    {
        $filter = $this->filter(['supplier_id' => $this->target->getKey()]);

        $this->assertSame(1, Delivery::query()->forDashboard($filter)->count());
        $this->assertSame(1, DeliveryItem::query()->forDashboard($filter)->count());
        $this->assertSame(1, PurchaseOrderItem::query()->forDashboard($filter)->count());
        $this->assertSame(1, DeliveryProblem::query()->forDashboard($filter)->count());
        $this->assertSame(1, PurchaseOrder::query()->forDashboard($filter)->count());
    }

    #[Test]
    public function the_plant_filter_reaches_models_that_only_know_the_plant_indirectly(): void
    {
        $elsewhere = Plant::factory()->create();

        $this->assertSame(2, Delivery::query()->forDashboard($this->filter(['plant_id' => $this->plant->getKey()]))->count());
        $this->assertSame(0, Delivery::query()->forDashboard($this->filter(['plant_id' => $elsewhere->getKey()]))->count());

        // delivery_problems has no plant_id of its own; it reaches it via the delivery.
        $this->assertSame(2, DeliveryProblem::query()->forDashboard($this->filter(['plant_id' => $this->plant->getKey()]))->count());
        $this->assertSame(0, DeliveryProblem::query()->forDashboard($this->filter(['plant_id' => $elsewhere->getKey()]))->count());
    }

    #[Test]
    public function the_material_category_filter_reaches_models_through_the_material(): void
    {
        $otherCategory = MaterialCategory::factory()->create();

        $inCategory = $this->filter(['material_category_id' => $this->category->getKey()]);
        $outside = $this->filter(['material_category_id' => $otherCategory->getKey()]);

        $this->assertSame(2, DeliveryItem::query()->forDashboard($inCategory)->count());
        $this->assertSame(0, DeliveryItem::query()->forDashboard($outside)->count());
        $this->assertSame(2, PurchaseOrderItem::query()->forDashboard($inCategory)->count());
        $this->assertSame(0, PurchaseOrderItem::query()->forDashboard($outside)->count());
    }

    #[Test]
    public function a_cancelled_delivery_leaves_the_population_on_every_model(): void
    {
        $filter = $this->filter();

        Delivery::query()->first()?->forceFill(['status' => DeliveryStatus::CANCELLED])->save();

        $this->assertSame(1, Delivery::query()->forDashboard($filter)->count());
        $this->assertSame(1, DeliveryItem::query()->forDashboard($filter)->count());
    }

    #[Test]
    public function a_reversed_date_range_is_corrected_rather_than_rejected(): void
    {
        $filter = DashboardFilter::fromArray(['date_from' => '2026-08-31', 'date_to' => '2026-08-01']);

        $this->assertSame('2026-08-01', $filter->dateFrom);
        $this->assertSame('2026-08-31', $filter->dateTo);
    }

    #[Test]
    public function a_period_string_expands_to_the_whole_month(): void
    {
        $filter = DashboardFilter::fromArray(['period' => '2026-02']);

        $this->assertSame('2026-02-01', $filter->dateFrom);
        $this->assertSame('2026-02-28', $filter->dateTo, '2026 is not a leap year.');
        $this->assertSame('2026-02', $filter->periodLabel());
    }

    #[Test]
    public function the_trend_series_carries_the_filters_other_criteria_into_every_month(): void
    {
        $filter = $this->filter(['supplier_id' => $this->target->getKey()]);
        $months = $filter->trailingMonths(6);

        $this->assertCount(6, $months);
        $this->assertSame('2026-03', $months[0]->periodLabel());
        $this->assertSame('2026-08', $months[5]->periodLabel());

        foreach ($months as $month) {
            $this->assertSame($this->target->getKey(), $month->supplierId);
        }
    }

    #[Test]
    public function the_trend_window_never_skips_a_month_on_a_thirty_first(): void
    {
        // Carbon::parse('2026-08-31')->subMonths(4) is 1 May, not April.
        // The filter normalises to the first of the month before stepping back,
        // so a six-month window is always six consecutive months.
        $filter = DashboardFilter::fromArray([
            'date_from' => '2026-08-01',
            'date_to' => '2026-08-31',
        ]);

        $this->assertSame(
            ['2026-03', '2026-04', '2026-05', '2026-06', '2026-07', '2026-08'],
            array_map(
                static fn (DashboardFilter $month): string => $month->periodLabel(),
                $filter->trailingMonths(6),
            ),
        );

        $window = $filter->spanningMonths(6);
        $this->assertSame('2026-03-01', $window->dateFrom);
        $this->assertSame('2026-08-31', $window->dateTo);
    }

    #[Test]
    public function two_filters_with_the_same_criteria_share_a_cache_key(): void
    {
        $this->assertSame(
            $this->filter(['supplier_id' => 7])->cacheKey('kpi'),
            $this->filter(['supplier_id' => 7])->cacheKey('kpi'),
        );

        $this->assertNotSame(
            $this->filter(['supplier_id' => 7])->cacheKey('kpi'),
            $this->filter(['supplier_id' => 8])->cacheKey('kpi'),
        );
    }

    /**
     * One purchase order, one receipt and one problem for a supplier on a date.
     */
    private function receipt(Supplier $supplier, string $date): void
    {
        $order = PurchaseOrder::factory()->approved()->create([
            'supplier_id' => $supplier->getKey(),
            'plant_id' => $this->plant->getKey(),
            'po_date' => $date,
        ]);

        $item = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $order->getKey(),
            'material_id' => $this->material->getKey(),
            'warehouse_id' => Warehouse::factory()->forPlant($this->plant)->create()->getKey(),
            'uom_id' => $this->material->uom_id,
            'schedule_delivery_date' => $date,
        ]);

        $delivery = Delivery::factory()->on($date)->create([
            'purchase_order_id' => $order->getKey(),
            'supplier_id' => $supplier->getKey(),
            'plant_id' => $this->plant->getKey(),
        ]);

        DeliveryItem::factory()->fulfilling($item)->create(['delivery_id' => $delivery->getKey()]);

        DeliveryProblem::factory()->create([
            'delivery_id' => $delivery->getKey(),
            'supplier_id' => $supplier->getKey(),
            'material_id' => $this->material->getKey(),
            'problem_date' => $date,
        ]);
    }
}
