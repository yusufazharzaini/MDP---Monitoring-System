<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use App\Enums\DeliveryStatus;
use App\Enums\OverallDeliveryStatus;
use App\Enums\QuantityStatus;
use App\Enums\TimelinessStatus;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\Support\DemoBlueprint;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Proves the seeded demo dataset reproduces the reference dashboard exactly.
 *
 * These numbers are the contract between the seeder and the dashboard: if a
 * change to the planner or the status rules moves them, this test says so.
 */
final class DemoDataIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private string $from;

    private string $to;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->from = Carbon::now()->startOfMonth()->toDateString();
        $this->to = Carbon::now()->endOfMonth()->toDateString();
    }

    #[Test]
    public function the_current_period_reproduces_the_reference_kpi_cards(): void
    {
        $total = $this->lines()->count();
        $onTime = $this->lines()->where('delivery_items.timeliness_status', TimelinessStatus::ON_TIME->value)->count();
        $late = $this->lines()->where('delivery_items.timeliness_status', TimelinessStatus::LATE->value)->count();
        $short = $this->lines()->where('delivery_items.quantity_status', QuantityStatus::SHORT->value)->count();

        $this->assertSame(1250, $total, 'Total delivery');
        $this->assertSame(1210, $onTime, 'On time delivery');
        $this->assertSame(40, $late, 'Late delivery');
        $this->assertSame(18, $short, 'Short delivery');
        $this->assertSame(96.8, round($onTime / $total * 100, 1), 'Service rate');
    }

    #[Test]
    public function the_supplier_ranking_reproduces_the_published_service_rates(): void
    {
        $expected = [
            'Supplier A' => 98.4,
            'Supplier B' => 95.0,
            'Supplier C' => 93.2,
            'Supplier D' => 86.7,
            'Supplier E' => 93.3,
        ];

        $rows = $this->lines()
            ->join('suppliers', 'suppliers.id', '=', 'deliveries.supplier_id')
            ->groupBy('suppliers.name')
            ->selectRaw('suppliers.name as name, count(*) as total')
            ->selectRaw(
                'sum(case when delivery_items.timeliness_status = ? then 1 else 0 end) as on_time',
                [TimelinessStatus::ON_TIME->value],
            )
            ->get()
            ->keyBy('name');

        foreach ($expected as $supplier => $rate) {
            $row = $rows[$supplier] ?? null;

            $this->assertNotNull($row, "{$supplier} has no deliveries in the current period.");
            $this->assertSame(
                $rate,
                round($row->on_time / $row->total * 100, 1),
                "{$supplier} service rate",
            );
        }
    }

    #[Test]
    public function the_trend_reproduces_the_reference_service_rate_line(): void
    {
        $expected = [5 => 97.2, 4 => 96.5, 3 => 98.1, 2 => 95.8, 1 => 97.0, 0 => 96.8];

        foreach ($expected as $monthsAgo => $rate) {
            // Normalise to the first of the month *before* subtracting: on a
            // 31st, Carbon::now()->subMonths(4) lands on 1 May rather than in
            // April, silently skipping a month.
            $month = Carbon::now()->startOfMonth()->subMonths($monthsAgo);

            $total = $this->lines($month)->count();
            $onTime = $this->lines($month)
                ->where('delivery_items.timeliness_status', TimelinessStatus::ON_TIME->value)
                ->count();

            $this->assertGreaterThan(0, $total, "No deliveries seeded for {$month->format('Y-m')}.");
            $this->assertSame(
                $rate,
                round($onTime / $total * 100, 1),
                "Service rate for {$month->format('Y-m')}",
            );
        }
    }

    #[Test]
    public function the_period_contains_exactly_seven_critical_materials(): void
    {
        $activeInPeriod = DB::table('delivery_items')
            ->join('deliveries', 'deliveries.id', '=', 'delivery_items.delivery_id')
            ->whereBetween('deliveries.delivery_date', [$this->from, $this->to])
            ->select('delivery_items.material_id');

        $flagged = DB::table('materials')
            ->where('is_critical', true)
            ->whereIn('id', $activeInPeriod)
            ->pluck('id');

        $late = $this->lines()
            ->where('delivery_items.timeliness_status', TimelinessStatus::LATE->value)
            ->distinct()
            ->pluck('delivery_items.material_id');

        $shortfall = DB::table('purchase_order_items')
            ->whereBetween('schedule_delivery_date', [$this->from, $this->to])
            ->where('fulfillment_status', QuantityStatus::SHORT->value)
            ->distinct()
            ->pluck('material_id');

        $criticalProblems = DB::table('delivery_problems')
            ->whereBetween('problem_date', [$this->from, $this->to])
            ->where('severity', 'CRITICAL')
            ->whereNotNull('material_id')
            ->distinct()
            ->pluck('material_id');

        $union = collect([$flagged, $late, $shortfall, $criticalProblems])
            ->flatten()
            ->unique();

        $this->assertCount(DemoBlueprint::EXPECTED_CRITICAL_MATERIALS, $union);
    }

    #[Test]
    public function the_pareto_distribution_matches_the_reference_chart(): void
    {
        $rows = DB::table('delivery_problems')
            ->join('problem_categories', 'problem_categories.id', '=', 'delivery_problems.problem_category_id')
            ->whereBetween('problem_date', [$this->from, $this->to])
            ->groupBy('problem_categories.code')
            ->selectRaw('problem_categories.code as code, count(*) as total')
            ->orderByDesc('total')
            ->get();

        $counts = $rows->pluck('total', 'code')->all();
        $this->assertSame(DemoBlueprint::PROBLEM_DISTRIBUTION, $counts);

        $total = array_sum($counts);
        $this->assertSame(83, $total);

        $cumulative = 0;
        $percentages = [];
        foreach ($counts as $count) {
            $cumulative += $count;
            $percentages[] = round($cumulative / $total * 100);
        }

        $this->assertSame([46.0, 75.0, 89.0, 96.0, 100.0], $percentages);
    }

    #[Test]
    public function the_period_contains_genuine_split_shipments(): void
    {
        $split = DB::table('purchase_order_items as poi')
            ->join('delivery_items as di', 'di.purchase_order_item_id', '=', 'poi.id')
            ->join('deliveries as d', 'd.id', '=', 'di.delivery_id')
            ->whereBetween('d.delivery_date', [$this->from, $this->to])
            ->groupBy('poi.id')
            ->havingRaw('COUNT(DISTINCT di.delivery_id) > 1')
            ->select('poi.id')
            ->get();

        $this->assertCount(
            DemoBlueprint::CURRENT_MONTH_SPLIT_LINES,
            $split,
            'The demo period must contain split shipments, otherwise partial and '
                .'multiple delivery are supported but never demonstrated.',
        );
    }

    #[Test]
    public function a_split_shipment_settles_its_order_line_in_full(): void
    {
        $item = DB::table('purchase_order_items as poi')
            ->join('delivery_items as di', 'di.purchase_order_item_id', '=', 'poi.id')
            ->join('deliveries as d', 'd.id', '=', 'di.delivery_id')
            ->whereBetween('d.delivery_date', [$this->from, $this->to])
            ->groupBy('poi.id', 'poi.qty_ordered', 'poi.qty_received', 'poi.fulfillment_status')
            ->havingRaw('COUNT(DISTINCT di.delivery_id) > 1')
            ->select('poi.id', 'poi.qty_ordered', 'poi.qty_received', 'poi.fulfillment_status')
            ->first();

        $this->assertNotNull($item);
        $this->assertSame(QuantityStatus::FULL->value, $item->fulfillment_status);
        $this->assertEqualsWithDelta((float) $item->qty_ordered, (float) $item->qty_received, 0.0001);

        // The first receipt is cumulatively short; the second settles the line.
        $statuses = DB::table('delivery_items as di')
            ->join('deliveries as d', 'd.id', '=', 'di.delivery_id')
            ->where('di.purchase_order_item_id', $item->id)
            ->orderBy('d.delivery_date')
            ->orderBy('di.id')
            ->pluck('di.quantity_status')
            ->all();

        $this->assertSame(
            [QuantityStatus::SHORT->value, QuantityStatus::FULL->value],
            $statuses,
        );
    }

    #[Test]
    public function every_overall_status_is_represented_in_the_demo_data(): void
    {
        $present = $this->lines()
            ->distinct()
            ->pluck('delivery_items.overall_status')
            ->all();

        foreach ([
            OverallDeliveryStatus::ON_TIME_FULL,
            OverallDeliveryStatus::LATE_FULL,
            OverallDeliveryStatus::ON_TIME_SHORT,
            OverallDeliveryStatus::LATE_SHORT,
            OverallDeliveryStatus::OVER_DELIVERY,
        ] as $status) {
            $this->assertContains(
                $status->value,
                $present,
                "The demo period has no {$status->value} row, so that state is undemonstrated.",
            );
        }

        // PENDING lives on order lines that have not been received at all.
        $this->assertGreaterThan(
            0,
            DB::table('purchase_order_items')
                ->where('overall_status', OverallDeliveryStatus::PENDING->value)
                ->count(),
        );
    }

    #[Test]
    public function no_cancelled_delivery_pollutes_the_seeded_population(): void
    {
        $this->assertSame(
            0,
            DB::table('deliveries')->where('status', DeliveryStatus::CANCELLED->value)->count(),
        );
    }

    #[Test]
    public function every_delivery_line_points_at_a_purchase_order_line_for_the_same_material(): void
    {
        $mismatched = DB::table('delivery_items')
            ->join('purchase_order_items', 'purchase_order_items.id', '=', 'delivery_items.purchase_order_item_id')
            ->whereColumn('purchase_order_items.material_id', '!=', 'delivery_items.material_id')
            ->count();

        $this->assertSame(0, $mismatched);
    }

    #[Test]
    public function the_denormalised_rollup_agrees_with_the_delivery_lines(): void
    {
        $drifted = DB::table('purchase_order_items as poi')
            ->leftJoin('delivery_items as di', 'di.purchase_order_item_id', '=', 'poi.id')
            ->leftJoin('deliveries as d', 'd.id', '=', 'di.delivery_id')
            ->where(function ($query): void {
                $query->whereNull('d.status')->orWhere('d.status', '!=', DeliveryStatus::CANCELLED->value);
            })
            ->groupBy('poi.id', 'poi.qty_received')
            ->havingRaw('abs(poi.qty_received - coalesce(sum(di.qty_received), 0)) > 0.0001')
            ->get();

        $this->assertCount(0, $drifted, 'purchase_order_items.qty_received drifted from its delivery lines.');
    }

    /**
     * Delivery lines that count towards performance for the given month.
     */
    private function lines(?Carbon $month = null): Builder
    {
        $from = $month === null ? $this->from : $month->copy()->startOfMonth()->toDateString();
        $to = $month === null ? $this->to : $month->copy()->endOfMonth()->toDateString();

        return DB::table('delivery_items')
            ->join('deliveries', 'deliveries.id', '=', 'delivery_items.delivery_id')
            ->where('deliveries.status', '!=', DeliveryStatus::CANCELLED->value)
            ->whereBetween('deliveries.delivery_date', [$from, $to]);
    }
}
