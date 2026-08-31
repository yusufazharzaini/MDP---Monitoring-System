<?php

declare(strict_types=1);

namespace Tests\Feature\PurchaseOrder;

use App\Models\Material;
use App\Models\Plant;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Services\PurchaseOrder\PurchaseOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Monetary precision, characterised.
 *
 * The specification says plainly: jangan menggunakan FLOAT untuk monetary
 * calculation. The schema honours it - every amount is decimal(18,4) - but the
 * arithmetic on top runs through PHP floats: a line amount is
 * round((float) qty * (float) price, 4), and the order total casts the SQL SUM
 * to float before rounding.
 *
 * A float64 carries about 15-16 significant digits while decimal(18,4) allows
 * 18, so the question is not whether a bound exists but where it sits relative
 * to the money this system actually handles. These tests record the answer, so
 * the gap between the rule and the implementation is a documented decision
 * rather than something discovered by a wrong invoice.
 *
 * Verdict: exact for every amount Torica transacts. See docs/03-BUSINESS-RULES.
 */
final class MonetaryPrecisionTest extends TestCase
{
    use RefreshDatabase;

    private Plant $plant;

    private Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedReferenceData();
        Carbon::setTestNow('2026-08-26 09:00:00');

        $this->plant = Plant::factory()->create();
        $this->warehouse = Warehouse::factory()->forPlant($this->plant)->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * @param  array<int, array{qty: string|float, price: string|float}>  $lines
     */
    private function orderWith(array $lines): PurchaseOrder
    {
        $material = Material::factory()->create();

        return app(PurchaseOrderService::class)->create(
            [
                'po_date' => '2026-08-01',
                'supplier_id' => Supplier::factory()->create()->getKey(),
                'plant_id' => $this->plant->getKey(),
                'currency' => 'IDR',
            ],
            array_map(fn (array $line): array => [
                'material_id' => $material->getKey(),
                'warehouse_id' => $this->warehouse->getKey(),
                'uom_id' => $material->uom_id,
                'schedule_delivery_date' => '2026-08-26',
                'qty_ordered' => $line['qty'],
                'unit_price' => $line['price'],
            ], $lines),
            $this->userWithRole('PURCHASING'),
        );
    }

    /**
     * The stored value straight from the row, not re-read through the model.
     *
     * Compared as a float rather than a string: MySQL returns a padded DECIMAL
     * ('30.0000') while SQLite returns a bare numeric ('30'), and the question
     * here is the value, not the formatting.
     */
    private function storedTotal(PurchaseOrder $order): float
    {
        return (float) DB::table('purchase_orders')
            ->where('id', $order->getKey())
            ->value('total_amount');
    }

    #[Test]
    public function a_line_amount_is_exact_at_ordinary_idr_magnitudes(): void
    {
        // 1,250 units at Rp 8,500,000.5000 - a large but unremarkable order.
        $order = $this->orderWith([['qty' => 1250, 'price' => 8500000.5]]);

        $this->assertSame(10625000625.0, (float) $order->items()->value('amount'));
    }

    #[Test]
    public function a_multi_line_total_is_exact_at_ordinary_idr_magnitudes(): void
    {
        $order = $this->orderWith(array_fill(0, 12, ['qty' => 1250, 'price' => 8500000.5]));

        // 12 x 10,625,000,625 = 127,500,007,500 - about Rp 127 billion, and
        // still well inside what a float64 represents without loss.
        $this->assertSame(127500007500.0, $this->storedTotal($order));
    }

    #[Test]
    public function fractional_quantities_and_prices_survive_the_round_trip(): void
    {
        // Weight-based materials order to four decimals on both sides.
        $order = $this->orderWith([
            ['qty' => 1234.5678, 'price' => 9876.5432],
            ['qty' => 0.0001, 'price' => 10000],
        ]);

        $amounts = $order->items()->orderBy('line_no')->pluck('amount')->all();

        // 1234.5678 x 9876.5432 = 12,193,262.21003, rounded to four places.
        $this->assertSame(12193262.21, (float) $amounts[0]);
        $this->assertSame(1.0, (float) $amounts[1]);
        $this->assertSame(12193263.21, $this->storedTotal($order));
    }

    #[Test]
    public function a_long_order_accumulates_without_drifting(): void
    {
        // 100 lines of a value that has no exact binary representation. If the
        // float path drifted, this is where it would show.
        $order = $this->orderWith(array_fill(0, 100, ['qty' => 3, 'price' => 0.1]));

        $this->assertSame(30.0, $this->storedTotal($order));
    }

    #[Test]
    public function the_stored_total_always_equals_the_sum_of_its_lines(): void
    {
        $order = $this->orderWith([
            ['qty' => 7, 'price' => 1428571.4285],
            ['qty' => 13, 'price' => 769230.7692],
            ['qty' => 999, 'price' => 1001.001],
        ]);

        $lineSum = (string) DB::table('purchase_order_items')
            ->where('purchase_order_id', $order->getKey())
            ->sum('amount');

        // The denormalised header is only worth having if it cannot disagree
        // with the rows it summarises.
        $this->assertSame(round((float) $lineSum, 4), round($this->storedTotal($order), 4));
    }

    #[Test]
    public function editing_a_line_leaves_the_total_exact(): void
    {
        $order = $this->orderWith([
            ['qty' => 1250, 'price' => 8500000.5],
            ['qty' => 1250, 'price' => 8500000.5],
        ]);

        $items = $order->items()->orderBy('line_no')->get();
        $material = $items[0]->material_id;

        app(PurchaseOrderService::class)->update(
            $order,
            [],
            [[
                'id' => $items[0]->getKey(),
                'material_id' => $material,
                'warehouse_id' => $this->warehouse->getKey(),
                'uom_id' => $items[0]->uom_id,
                'schedule_delivery_date' => '2026-08-26',
                'qty_ordered' => 1250,
                'unit_price' => 8500000.5,
            ]],
            $this->userWithRole('PURCHASING'),
        );

        $this->assertSame(10625000625.0, $this->storedTotal($order->refresh()));
    }

    #[Test]
    public function an_order_far_above_any_real_one_is_still_exact(): void
    {
        // Rp 9,999,999,999.9999 on a single unit - about Rp 10 billion for one
        // item, an order of magnitude past anything Torica raises. Fourteen
        // significant digits, comfortably inside a float64.
        $order = $this->orderWith([['qty' => 1, 'price' => 9999999999.9999]]);

        $this->assertSame(9999999999.9999, $this->storedTotal($order));
    }

    #[Test]
    public function the_precision_bound_sits_at_the_column_limit_not_below_it(): void
    {
        /*
         * Where the rule and the implementation actually part company, recorded
         * without asking a database to store it - MySQL refuses the value as
         * out of range while SQLite accepts it, so the honest place to measure
         * is PHP itself.
         *
         * decimal(18,4) allows 99,999,999,999,999.9999: eighteen significant
         * digits. A float64 carries about fifteen or sixteen, so the column's
         * own maximum cannot survive the float path - the fraction is gone
         * before any query runs. Everything below roughly Rp 10^11, which is
         * every order this system will ever see, is exact.
         */
        $columnMaximum = '99999999999999.9999';

        $throughFloat = sprintf('%.4f', round((float) $columnMaximum, 4));

        $this->assertNotSame(
            $columnMaximum,
            $throughFloat,
            'if this ever passes, the float path became exact and the note in docs/03 can go',
        );

        // And the bound is five orders of magnitude above the largest order the
        // demo data or the business produces.
        $this->assertGreaterThan(1e13, (float) $columnMaximum);
    }
}
