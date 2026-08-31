<?php

declare(strict_types=1);

namespace Tests\Feature\Concurrency;

use App\Models\Delivery;
use App\Models\Material;
use App\Models\Plant;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Services\Delivery\DeliveryService;
use App\Services\PurchaseOrder\PurchaseOrderService;
use App\Services\Support\NumberGeneratorService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * What holds when two people write at once.
 *
 * Several warehouses receiving against the same order on the same afternoon is
 * an ordinary Tuesday here. PHPUnit cannot run two real connections in
 * contention, so these tests prove the two mechanisms that make that safe -
 * the locking read and the unique keys - and the arithmetic that has to survive
 * interleaved writes. True multi-process contention is left to the database,
 * which is where it is actually enforced.
 */
final class ConcurrentWriteTest extends TestCase
{
    use RefreshDatabase;

    private PurchaseOrder $order;

    private PurchaseOrderItem $line;

    private Plant $plant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedReferenceData();
        Carbon::setTestNow('2026-08-26 09:00:00');

        $this->plant = Plant::factory()->create();
        $material = Material::factory()->create();
        $buyer = $this->userWithRole('PURCHASING');

        $orders = app(PurchaseOrderService::class);
        $this->order = $orders->create(
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
                'qty_ordered' => 1000,
                'unit_price' => 5000,
            ]],
            $buyer,
        );

        $orders->submit($this->order, $buyer);
        $orders->approve($this->order, $this->userWithRole('MANAGEMENT'));

        $this->line = $this->order->items()->firstOrFail();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function requiresRowLocks(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb', 'pgsql'], true)) {
            $this->markTestSkipped('Row locks are only emitted on MySQL/PostgreSQL.');
        }
    }

    private function receive(float $qty): Delivery
    {
        return app(DeliveryService::class)->receive(
            $this->order->refresh(),
            ['delivery_date' => '2026-08-26'],
            [['purchase_order_item_id' => $this->line->getKey(), 'qty_received' => $qty, 'condition' => 'GOOD']],
            $this->userWithRole('WAREHOUSE'),
        );
    }

    #[Test]
    public function interleaved_receipts_against_one_line_sum_rather_than_overwrite(): void
    {
        $this->receive(300);
        $this->receive(250);
        $this->receive(450);

        // Each receipt re-reads and re-settles the rollup, so three warehouses
        // booking the same line leave the sum, not the last write.
        $this->assertSame(1000.0, (float) $this->line->refresh()->qty_received);
        $this->assertSame(3, $this->line->deliveryItems()->count());
    }

    #[Test]
    public function the_rollup_still_agrees_with_its_lines_after_a_correction(): void
    {
        $first = $this->receive(300);
        $this->receive(250);

        app(DeliveryService::class)->update(
            $first,
            ['delivery_date' => '2026-08-26'],
            [['purchase_order_item_id' => $this->line->getKey(), 'qty_received' => 500, 'condition' => 'GOOD']],
            $this->userWithRole('WAREHOUSE'),
        );

        $this->assertSame(750.0, (float) $this->line->refresh()->qty_received);
    }

    #[Test]
    public function a_cancellation_between_two_receipts_removes_only_its_own_quantity(): void
    {
        $first = $this->receive(300);
        $this->receive(250);

        app(DeliveryService::class)->cancel($first, $this->userWithRole('LOGISTIC'), 'Salah gudang');

        $this->assertSame(250.0, (float) $this->line->refresh()->qty_received);
    }

    #[Test]
    public function the_number_generator_takes_a_locking_read(): void
    {
        // SQLite's grammar emits no FOR UPDATE at all - it serialises writers
        // instead - so the clause is only observable on the engine that
        // actually honours it.
        $this->requiresRowLocks();

        DB::flushQueryLog();
        DB::enableQueryLog();

        app(NumberGeneratorService::class)->purchaseOrderNumber();

        $sql = collect(DB::getQueryLog())->pluck('query')->implode(' ');
        DB::disableQueryLog();

        // The lock is what stops two buyers reading the same highest number and
        // minting it twice.
        $this->assertStringContainsString('for update', strtolower($sql));
    }

    #[Test]
    public function a_duplicate_document_number_is_refused_by_the_schema(): void
    {
        $number = app(NumberGeneratorService::class)->purchaseOrderNumber();

        $insert = fn (): bool => DB::table('purchase_orders')->insert([
            'ulid' => (string) Str::ulid(),
            'po_number' => $number,
            'po_date' => '2026-08-01',
            'supplier_id' => $this->order->supplier_id,
            'plant_id' => $this->plant->getKey(),
            'status' => 'DRAFT',
            'currency' => 'IDR',
            'total_amount' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $insert();

        // Whatever the application layer does, two orders can never share an
        // identifier - the unique index is the last word.
        $this->expectException(UniqueConstraintViolationException::class);
        $insert();
    }

    #[Test]
    public function one_receipt_cannot_book_the_same_order_line_twice(): void
    {
        $delivery = $this->receive(300);

        $this->expectException(UniqueConstraintViolationException::class);

        // The KPI grain is the delivery line; a duplicate row would double-count
        // in every aggregate.
        DB::table('delivery_items')->insert([
            'delivery_id' => $delivery->getKey(),
            'purchase_order_item_id' => $this->line->getKey(),
            'material_id' => $this->line->material_id,
            'uom_id' => $this->line->uom_id,
            'qty_received' => 100,
            'condition' => 'GOOD',
            'timeliness_status' => 'ON_TIME',
            'quantity_status' => 'SHORT',
            'overall_status' => 'ON_TIME_SHORT',
            'days_late' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    #[Test]
    public function two_evaluations_cannot_cover_the_same_supplier_and_month(): void
    {
        $row = fn (): array => [
            'supplier_id' => $this->order->supplier_id,
            'period_year' => 2026,
            'period_month' => 7,
            'delivery_score' => 90,
            'quality_score' => 90,
            'quantity_score' => 90,
            'responsiveness_score' => 90,
            'total_score' => 90,
            'grade' => 'AVERAGE',
            'status' => 'DRAFT',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        DB::table('supplier_evaluations')->insert($row());

        $this->expectException(UniqueConstraintViolationException::class);
        DB::table('supplier_evaluations')->insert($row());
    }

    #[Test]
    public function a_receipt_and_its_rollup_are_written_in_one_transaction(): void
    {
        $before = (float) $this->line->qty_received;

        try {
            app(DeliveryService::class)->receive(
                $this->order->refresh(),
                ['delivery_date' => '2026-08-26'],
                [[
                    // A line belonging to no order at all: the write must fail
                    // whole, leaving neither a receipt nor a moved rollup.
                    'purchase_order_item_id' => 999_999,
                    'qty_received' => 100,
                    'condition' => 'GOOD',
                ]],
                $this->userWithRole('WAREHOUSE'),
            );
        } catch (\Throwable) {
            // Expected.
        }

        $this->assertSame($before, (float) $this->line->refresh()->qty_received);
        $this->assertSame(0, Delivery::query()->count());
    }
}
