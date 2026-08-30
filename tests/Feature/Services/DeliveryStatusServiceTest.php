<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Enums\DeliveryItemCondition;
use App\Enums\DeliveryStatus;
use App\Enums\OverallDeliveryStatus;
use App\Enums\PurchaseOrderStatus;
use App\Enums\QuantityStatus;
use App\Enums\TimelinessStatus;
use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\Material;
use App\Models\Plant;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Services\Delivery\DeliveryStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Persistence behaviour of the status engine: the cumulative replay across
 * split receipts, the exclusion of cancelled deliveries, and the purchase
 * order rollup.
 */
final class DeliveryStatusServiceTest extends TestCase
{
    use RefreshDatabase;

    private DeliveryStatusService $service;

    private Supplier $supplier;

    private Plant $plant;

    private Warehouse $warehouse;

    private Material $material;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedReferenceData();

        $this->service = app(DeliveryStatusService::class);
        $this->supplier = Supplier::factory()->create();
        $this->plant = Plant::factory()->create();
        $this->warehouse = Warehouse::factory()->forPlant($this->plant)->create();
        $this->material = Material::factory()->create();
    }

    #[Test]
    public function a_punctual_complete_receipt_settles_the_line_as_on_time_full(): void
    {
        $item = $this->orderLine(qty: 1000, schedule: '2026-08-26');
        $this->receive($item, qty: 1000, on: '2026-08-26');

        $this->service->recalculateForPurchaseOrderItem($item);
        $item->refresh();

        $this->assertSame(QuantityStatus::FULL, $item->fulfillment_status);
        $this->assertSame(TimelinessStatus::ON_TIME, $item->timeliness_status);
        $this->assertSame(OverallDeliveryStatus::ON_TIME_FULL, $item->overall_status);
        $this->assertSame(1000.0, (float) $item->qty_received);
        $this->assertSame('2026-08-26', $item->last_receipt_date?->toDateString());
    }

    #[Test]
    public function a_late_short_receipt_settles_the_line_as_late_short(): void
    {
        $item = $this->orderLine(qty: 1000, schedule: '2026-08-26');
        $line = $this->receive($item, qty: 950, on: '2026-08-28');

        $this->service->recalculateForPurchaseOrderItem($item);

        $this->assertSame(OverallDeliveryStatus::LATE_SHORT, $item->refresh()->overall_status);
        $this->assertSame(OverallDeliveryStatus::LATE_SHORT, $line->refresh()->overall_status);
        $this->assertSame(2, $line->days_late);
    }

    #[Test]
    public function split_receipts_replay_cumulatively_so_only_the_settling_one_is_full(): void
    {
        $item = $this->orderLine(qty: 1000, schedule: '2026-08-26');
        $first = $this->receive($item, qty: 400, on: '2026-08-24');
        $second = $this->receive($item, qty: 600, on: '2026-08-25');

        $this->service->recalculateForPurchaseOrderItem($item);

        $this->assertSame(QuantityStatus::SHORT, $first->refresh()->quantity_status);
        $this->assertSame(QuantityStatus::FULL, $second->refresh()->quantity_status);
        $this->assertSame(QuantityStatus::FULL, $item->refresh()->fulfillment_status);
        $this->assertSame(1000.0, (float) $item->qty_received);
        $this->assertSame('2026-08-24', $item->first_receipt_date?->toDateString());
        $this->assertSame('2026-08-25', $item->last_receipt_date?->toDateString());
    }

    #[Test]
    public function the_settling_receipt_decides_punctuality_for_a_split_line(): void
    {
        $item = $this->orderLine(qty: 1000, schedule: '2026-08-26');
        $this->receive($item, qty: 400, on: '2026-08-20');
        $this->receive($item, qty: 600, on: '2026-08-29');

        $this->service->recalculateForPurchaseOrderItem($item);

        $this->assertSame(TimelinessStatus::LATE, $item->refresh()->timeliness_status);
        $this->assertSame(OverallDeliveryStatus::LATE_FULL, $item->overall_status);
    }

    #[Test]
    public function a_cancelled_delivery_is_excluded_from_the_rollup(): void
    {
        $item = $this->orderLine(qty: 1000, schedule: '2026-08-26');
        $this->receive($item, qty: 400, on: '2026-08-24');
        $cancelled = $this->receive($item, qty: 600, on: '2026-08-25');

        $cancelled->delivery->forceFill(['status' => DeliveryStatus::CANCELLED])->save();

        $this->service->recalculateForPurchaseOrderItem($item);
        $item->refresh();

        $this->assertSame(400.0, (float) $item->qty_received);
        $this->assertSame(QuantityStatus::SHORT, $item->fulfillment_status);
    }

    #[Test]
    public function rejected_goods_are_recorded_but_never_count_as_fulfilled(): void
    {
        $item = $this->orderLine(qty: 1000, schedule: '2026-08-26');
        $this->receive($item, qty: 1000, on: '2026-08-26', condition: DeliveryItemCondition::REJECTED);

        $this->service->recalculateForPurchaseOrderItem($item);
        $item->refresh();

        $this->assertSame(0.0, (float) $item->qty_received);
        $this->assertSame(QuantityStatus::PENDING, $item->fulfillment_status);
        $this->assertSame(OverallDeliveryStatus::PENDING, $item->overall_status);
    }

    #[Test]
    public function a_purchase_order_completes_only_once_every_line_is_satisfied(): void
    {
        $order = $this->order();
        $first = $this->orderLine(qty: 500, schedule: '2026-08-26', order: $order, lineNo: 1);
        $second = $this->orderLine(qty: 300, schedule: '2026-08-26', order: $order, lineNo: 2);

        $this->receive($first, qty: 500, on: '2026-08-26');
        $this->service->recalculateForPurchaseOrderItem($first);
        $this->service->refreshPurchaseOrderStatus($order->refresh());

        $this->assertSame(PurchaseOrderStatus::PARTIAL, $order->refresh()->status);

        $this->receive($second, qty: 300, on: '2026-08-26');
        $this->service->recalculateForPurchaseOrderItem($second);
        $this->service->refreshPurchaseOrderStatus($order->refresh());

        $this->assertSame(PurchaseOrderStatus::COMPLETED, $order->refresh()->status);
    }

    #[Test]
    public function a_cancelled_purchase_order_is_never_reopened_by_a_recalculation(): void
    {
        $order = $this->order();
        $item = $this->orderLine(qty: 500, schedule: '2026-08-26', order: $order);
        $this->receive($item, qty: 500, on: '2026-08-26');

        $order->forceFill(['status' => PurchaseOrderStatus::CANCELLED])->save();

        $this->service->recalculateForPurchaseOrderItem($item);
        $this->service->refreshPurchaseOrderStatus($order->refresh());

        $this->assertSame(PurchaseOrderStatus::CANCELLED, $order->refresh()->status);
    }

    #[Test]
    public function recalculating_a_delivery_updates_its_header_status(): void
    {
        $order = $this->order();
        $item = $this->orderLine(qty: 1000, schedule: '2026-08-26', order: $order);
        $line = $this->receive($item, qty: 900, on: '2026-08-26');

        $this->service->recalculateForDelivery($line->delivery);

        $this->assertSame(DeliveryStatus::PARTIAL, $line->delivery->refresh()->status);
        $this->assertSame(PurchaseOrderStatus::PARTIAL, $order->refresh()->status);
    }

    private function order(): PurchaseOrder
    {
        return PurchaseOrder::factory()->approved()->create([
            'supplier_id' => $this->supplier->getKey(),
            'plant_id' => $this->plant->getKey(),
        ]);
    }

    private function orderLine(
        float $qty,
        string $schedule,
        ?PurchaseOrder $order = null,
        int $lineNo = 1,
    ): PurchaseOrderItem {
        return PurchaseOrderItem::factory()->create([
            'purchase_order_id' => ($order ?? $this->order())->getKey(),
            'material_id' => $this->material->getKey(),
            'warehouse_id' => $this->warehouse->getKey(),
            'uom_id' => $this->material->uom_id,
            'line_no' => $lineNo,
            'schedule_delivery_date' => $schedule,
            'qty_ordered' => $qty,
        ]);
    }

    private function receive(
        PurchaseOrderItem $item,
        float $qty,
        string $on,
        DeliveryItemCondition $condition = DeliveryItemCondition::GOOD,
    ): DeliveryItem {
        $delivery = Delivery::factory()->on($on)->create([
            'purchase_order_id' => $item->purchase_order_id,
            'supplier_id' => $this->supplier->getKey(),
            'plant_id' => $this->plant->getKey(),
        ]);

        return DeliveryItem::factory()->create([
            'delivery_id' => $delivery->getKey(),
            'purchase_order_item_id' => $item->getKey(),
            'material_id' => $item->material_id,
            'uom_id' => $item->uom_id,
            'qty_received' => $qty,
            'condition' => $condition,
        ]);
    }
}
