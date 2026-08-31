<?php

declare(strict_types=1);

namespace Tests\Feature\Delivery;

use App\Enums\DeliveryItemCondition;
use App\Enums\DeliveryStatus;
use App\Enums\OverallDeliveryStatus;
use App\Enums\PurchaseOrderStatus;
use App\Enums\QuantityStatus;
use App\Enums\TimelinessStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Delivery;
use App\Models\Material;
use App\Models\Plant;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Delivery\DeliveryService;
use App\Services\PurchaseOrder\PurchaseOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Receiving, and the statuses it settles.
 *
 * This is where the calculator built in Phase 1 finally meets real receipts:
 * booking goods must move the order line's rollup, the line's verdict, the
 * delivery header and the purchase order itself - all in one transaction.
 */
final class DeliveryReceivingTest extends TestCase
{
    use RefreshDatabase;

    private DeliveryService $deliveries;

    private User $clerk;

    private PurchaseOrder $order;

    private PurchaseOrderItem $line;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedReferenceData();
        Carbon::setTestNow('2026-08-26 09:00:00');

        $this->deliveries = app(DeliveryService::class);
        $this->clerk = $this->userWithRole('WAREHOUSE');

        $supplier = Supplier::factory()->create();
        $plant = Plant::factory()->create();
        $warehouse = Warehouse::factory()->forPlant($plant)->create();
        $material = Material::factory()->create();

        $orders = app(PurchaseOrderService::class);
        $this->order = $orders->create(
            [
                'po_date' => '2026-08-01',
                'supplier_id' => $supplier->getKey(),
                'plant_id' => $plant->getKey(),
                'currency' => 'IDR',
            ],
            [[
                'material_id' => $material->getKey(),
                'warehouse_id' => $warehouse->getKey(),
                'uom_id' => $material->uom_id,
                'schedule_delivery_date' => '2026-08-26',
                'qty_ordered' => 1000,
                'unit_price' => 5000,
            ]],
            $this->userWithRole('PURCHASING'),
        );

        $orders->submit($this->order, $this->userWithRole('PURCHASING'));
        $orders->approve($this->order, $this->userWithRole('MANAGEMENT'));

        $this->line = $this->order->items()->firstOrFail();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function receive(float $qty, string $date = '2026-08-26', array $overrides = []): Delivery
    {
        return $this->deliveries->receive(
            $this->order->refresh(),
            ['delivery_date' => $date, ...$overrides],
            [[
                'purchase_order_item_id' => $this->line->getKey(),
                'qty_received' => $qty,
                'condition' => DeliveryItemCondition::GOOD->value,
            ]],
            $this->clerk,
        );
    }

    // --- the four specified business rule cases --------------------------

    #[Test]
    public function a_punctual_complete_receipt_settles_as_on_time_full(): void
    {
        $delivery = $this->receive(1000, '2026-08-26');

        $item = $delivery->items()->firstOrFail();
        $this->assertSame(TimelinessStatus::ON_TIME, $item->timeliness_status);
        $this->assertSame(QuantityStatus::FULL, $item->quantity_status);
        $this->assertSame(OverallDeliveryStatus::ON_TIME_FULL, $item->overall_status);
        $this->assertSame(0, $item->days_late);
    }

    #[Test]
    public function a_late_complete_receipt_settles_as_late_full(): void
    {
        Carbon::setTestNow('2026-08-28 09:00:00');
        $delivery = $this->receive(1000, '2026-08-28');

        $item = $delivery->items()->firstOrFail();
        $this->assertSame(OverallDeliveryStatus::LATE_FULL, $item->overall_status);
        $this->assertSame(2, $item->days_late);
    }

    #[Test]
    public function a_punctual_short_receipt_settles_as_on_time_short(): void
    {
        $delivery = $this->receive(950, '2026-08-26');

        $this->assertSame(
            OverallDeliveryStatus::ON_TIME_SHORT,
            $delivery->items()->firstOrFail()->overall_status,
        );
    }

    #[Test]
    public function a_late_short_receipt_settles_as_late_short(): void
    {
        Carbon::setTestNow('2026-08-28 09:00:00');
        $delivery = $this->receive(950, '2026-08-28');

        $this->assertSame(
            OverallDeliveryStatus::LATE_SHORT,
            $delivery->items()->firstOrFail()->overall_status,
        );
    }

    #[Test]
    public function receiving_more_than_ordered_is_flagged_not_blocked(): void
    {
        $delivery = $this->receive(1200, '2026-08-26');

        $this->assertSame(
            OverallDeliveryStatus::OVER_DELIVERY,
            $delivery->items()->firstOrFail()->overall_status,
        );
        $this->assertSame(QuantityStatus::OVER, $this->line->refresh()->fulfillment_status);
    }

    // --- the rollup and the order ----------------------------------------

    #[Test]
    public function booking_a_receipt_moves_the_order_line_rollup(): void
    {
        $this->receive(400);

        $this->line->refresh();
        $this->assertSame(400.0, (float) $this->line->qty_received);
        $this->assertSame('2026-08-26', $this->line->first_receipt_date?->toDateString());
        $this->assertSame(600.0, $this->line->outstandingQuantity());
        $this->assertSame(QuantityStatus::SHORT, $this->line->fulfillment_status);
    }

    #[Test]
    public function a_partial_receipt_moves_the_order_to_partial(): void
    {
        $this->receive(400);

        $this->assertSame(PurchaseOrderStatus::PARTIAL, $this->order->refresh()->status);
        $this->assertTrue($this->order->acceptsDeliveries(), 'A partial order still takes more goods.');
    }

    #[Test]
    public function the_settling_receipt_completes_the_order(): void
    {
        $this->receive(400, '2026-08-24');
        $this->assertSame(PurchaseOrderStatus::PARTIAL, $this->order->refresh()->status);

        $this->receive(600, '2026-08-26');

        $this->assertSame(PurchaseOrderStatus::COMPLETED, $this->order->refresh()->status);
        $this->assertSame(1000.0, (float) $this->line->refresh()->qty_received);
        $this->assertFalse($this->order->acceptsDeliveries());
    }

    #[Test]
    public function split_receipts_are_judged_cumulatively(): void
    {
        $first = $this->receive(400, '2026-08-24');
        $second = $this->receive(600, '2026-08-26');

        // The first receipt was cumulatively short; the second settles the line.
        $this->assertSame(QuantityStatus::SHORT, $first->items()->firstOrFail()->quantity_status);
        $this->assertSame(QuantityStatus::FULL, $second->items()->firstOrFail()->quantity_status);
        $this->assertSame(OverallDeliveryStatus::ON_TIME_FULL, $this->line->refresh()->overall_status);
    }

    #[Test]
    public function the_settling_receipt_decides_whether_the_line_was_late(): void
    {
        $this->receive(400, '2026-08-20');
        Carbon::setTestNow('2026-08-29 09:00:00');
        $this->receive(600, '2026-08-29');

        $this->assertSame(TimelinessStatus::LATE, $this->line->refresh()->timeliness_status);
        $this->assertSame(OverallDeliveryStatus::LATE_FULL, $this->line->overall_status);
    }

    #[Test]
    public function rejected_goods_are_recorded_but_do_not_fulfil_the_order(): void
    {
        $this->deliveries->receive(
            $this->order,
            ['delivery_date' => '2026-08-26'],
            [[
                'purchase_order_item_id' => $this->line->getKey(),
                'qty_received' => 1000,
                'condition' => DeliveryItemCondition::REJECTED->value,
            ]],
            $this->clerk,
        );

        $this->line->refresh();
        $this->assertSame(0.0, (float) $this->line->qty_received);
        $this->assertSame(QuantityStatus::PENDING, $this->line->fulfillment_status);
        $this->assertSame(PurchaseOrderStatus::APPROVED, $this->order->refresh()->status);
    }

    // --- guards -----------------------------------------------------------

    #[Test]
    public function goods_cannot_be_booked_against_an_unapproved_order(): void
    {
        $draft = PurchaseOrder::factory()->draft()->create();

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessageMatches('/tidak dapat menerima delivery/');

        $this->deliveries->receive($draft, ['delivery_date' => '2026-08-26'], [], $this->clerk);
    }

    #[Test]
    public function goods_cannot_arrive_in_the_future(): void
    {
        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessageMatches('/masa depan/');

        $this->receive(100, '2026-09-15');
    }

    #[Test]
    public function a_receipt_cannot_reference_another_orders_line(): void
    {
        $foreign = PurchaseOrderItem::factory()->create();

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessageMatches('/purchase order yang sama/');

        $this->deliveries->receive(
            $this->order,
            ['delivery_date' => '2026-08-26'],
            [[
                'purchase_order_item_id' => $foreign->getKey(),
                'qty_received' => 10,
                'condition' => DeliveryItemCondition::GOOD->value,
            ]],
            $this->clerk,
        );
    }

    #[Test]
    public function one_receipt_cannot_book_the_same_order_line_twice(): void
    {
        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessageMatches('/hanya boleh muncul sekali/');

        $this->deliveries->receive(
            $this->order,
            ['delivery_date' => '2026-08-26'],
            [
                ['purchase_order_item_id' => $this->line->getKey(), 'qty_received' => 400, 'condition' => 'GOOD'],
                ['purchase_order_item_id' => $this->line->getKey(), 'qty_received' => 600, 'condition' => 'GOOD'],
            ],
            $this->clerk,
        );
    }

    // --- correction and reversal -----------------------------------------

    #[Test]
    public function correcting_a_receipt_re_settles_everything_it_touched(): void
    {
        $delivery = $this->receive(400);
        $this->assertSame(PurchaseOrderStatus::PARTIAL, $this->order->refresh()->status);

        $this->deliveries->update(
            $delivery,
            ['delivery_date' => '2026-08-26'],
            [[
                'purchase_order_item_id' => $this->line->getKey(),
                'qty_received' => 1000,
                'condition' => DeliveryItemCondition::GOOD->value,
            ]],
            $this->clerk,
        );

        $this->assertSame(1000.0, (float) $this->line->refresh()->qty_received);
        $this->assertSame(PurchaseOrderStatus::COMPLETED, $this->order->refresh()->status);
    }

    #[Test]
    public function cancelling_a_receipt_takes_its_quantity_back_out(): void
    {
        $delivery = $this->receive(1000);
        $this->assertSame(PurchaseOrderStatus::COMPLETED, $this->order->refresh()->status);

        $this->deliveries->cancel($delivery, $this->clerk, 'Barang dikembalikan ke supplier');

        $this->line->refresh();
        $this->assertSame(DeliveryStatus::CANCELLED, $delivery->refresh()->status);
        $this->assertSame(0.0, (float) $this->line->qty_received);
        $this->assertSame(QuantityStatus::PENDING, $this->line->fulfillment_status);
        // The order falls back to accepting goods again.
        $this->assertSame(PurchaseOrderStatus::APPROVED, $this->order->refresh()->status);
    }

    #[Test]
    public function a_cancelled_receipt_keeps_its_lines_but_loses_their_verdicts(): void
    {
        $delivery = $this->receive(1000);
        $this->deliveries->cancel($delivery, $this->clerk, 'Salah input');

        $item = $delivery->items()->firstOrFail();
        $this->assertSame(1000.0, (float) $item->qty_received, 'What was booked stays on the record.');
        $this->assertSame(OverallDeliveryStatus::PENDING, $item->overall_status);
        $this->assertSame(0, $item->days_late);
    }

    #[Test]
    public function cancelling_one_of_two_receipts_leaves_the_other_counting(): void
    {
        $first = $this->receive(400, '2026-08-24');
        $this->receive(600, '2026-08-26');
        $this->assertSame(PurchaseOrderStatus::COMPLETED, $this->order->refresh()->status);

        $this->deliveries->cancel($first, $this->clerk, 'Dobel input');

        $this->assertSame(600.0, (float) $this->line->refresh()->qty_received);
        $this->assertSame(PurchaseOrderStatus::PARTIAL, $this->order->refresh()->status);
    }

    #[Test]
    public function a_cancelled_receipt_cannot_be_cancelled_or_edited_again(): void
    {
        $delivery = $this->receive(500);
        $this->deliveries->cancel($delivery, $this->clerk, 'Salah input');

        $this->expectException(BusinessRuleException::class);

        $this->deliveries->cancel($delivery, $this->clerk, 'Sekali lagi');
    }

    #[Test]
    public function a_receipt_is_never_deleted(): void
    {
        $delivery = $this->receive(100);

        $this->assertFalse($this->clerk->can('delete', $delivery));
        $this->assertFalse($this->userWithRole('SUPER_ADMIN')->can('delete', $delivery));
    }
}
