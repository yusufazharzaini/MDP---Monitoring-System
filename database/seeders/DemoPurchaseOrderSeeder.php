<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\PurchaseOrderStatus;
use App\Enums\QuantityStatus;
use App\Models\Material;
use App\Models\Plant;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Delivery\DeliveryStatusCalculator;
use Database\Seeders\Support\DemoOrderPlanner;
use Database\Seeders\Support\DemoOrderSpec;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creates the demo purchase orders and their lines, including the denormalised
 * receipt rollup each line will have once DemoDeliverySeeder books the matching
 * receipts.
 *
 * Rollup values are computed here with the same DeliveryStatusCalculator the
 * runtime uses, and DeliverySeedConsistencyTest asserts that re-running
 * DeliveryStatusService over the seeded data changes nothing.
 */
class DemoPurchaseOrderSeeder extends Seeder
{
    private const CHUNK = 500;

    public function run(): void
    {
        $planner = new DemoOrderPlanner(
            Carbon::now()->startOfMonth(),
            DemoMaterialSeeder::normalMaterialCodes(),
            Plant::query()->orderBy('id')->pluck('code')->all(),
        );

        $orders = $planner->build();

        $suppliers = Supplier::query()->pluck('id', 'code');
        $plants = Plant::query()->pluck('id', 'code');
        $materials = Material::query()->get(['id', 'code', 'uom_id'])->keyBy('code');
        $warehouses = Warehouse::query()
            ->orderBy('id')
            ->get(['id', 'plant_id'])
            ->groupBy('plant_id')
            ->map(static fn ($group) => $group->first()->id);
        $createdBy = User::query()->orderBy('id')->value('id');

        $now = Carbon::now();
        $orderRows = [];

        foreach ($orders as $order) {
            $orderRows[] = [
                'ulid' => (string) Str::ulid(),
                'po_number' => $order->poNumber,
                'po_date' => $order->poDate,
                'supplier_id' => $suppliers[$order->supplierCode],
                'plant_id' => $plants[$order->plantCode],
                'currency' => 'IDR',
                'payment_term' => 'NET 30',
                'status' => $this->orderStatus($order)->value,
                'total_amount' => $order->totalAmount(),
                'remarks' => null,
                'created_by' => $createdBy,
                'approved_by' => $createdBy,
                'approved_at' => Carbon::parse($order->poDate)->addDay(),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($orderRows, self::CHUNK) as $chunk) {
            DB::table('purchase_orders')->insert($chunk);
        }

        $orderIds = DB::table('purchase_orders')->pluck('id', 'po_number');
        $calculator = app(DeliveryStatusCalculator::class);
        $itemRows = [];

        foreach ($orders as $order) {
            $orderId = $orderIds[$order->poNumber];
            $schedule = Carbon::parse($order->scheduleDate);
            $actual = $order->deliveryDate === null ? null : Carbon::parse($order->deliveryDate);

            foreach ($order->lines as $line) {
                $material = $materials[$line->materialCode];
                $verdict = $calculator->evaluate(
                    $line->qtyOrdered,
                    $line->qtyReceived,
                    $line->delivered ? $actual : null,
                    $schedule,
                );

                $itemRows[] = [
                    'purchase_order_id' => $orderId,
                    'material_id' => $material->id,
                    'warehouse_id' => $warehouses[$plants[$order->plantCode]],
                    'uom_id' => $material->uom_id,
                    'line_no' => $line->lineNo,
                    'schedule_delivery_date' => $order->scheduleDate,
                    'qty_ordered' => $line->qtyOrdered,
                    'unit_price' => $line->unitPrice,
                    'amount' => $line->amount(),
                    'qty_received' => $line->qtyReceived,
                    'first_receipt_date' => $line->delivered ? $order->deliveryDate : null,
                    'last_receipt_date' => $line->delivered ? $order->deliveryDate : null,
                    'fulfillment_status' => $verdict['quantity']->value,
                    'timeliness_status' => $verdict['timeliness']->value,
                    'overall_status' => $verdict['overall']->value,
                    'remarks' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($itemRows, self::CHUNK) as $chunk) {
            DB::table('purchase_order_items')->insert($chunk);
        }
    }

    /**
     * A demo order is COMPLETED when every line is satisfied, PARTIAL when some
     * quantity arrived short, and APPROVED while nothing has been received.
     */
    private function orderStatus(DemoOrderSpec $order): PurchaseOrderStatus
    {
        if (! $order->isDelivered()) {
            return PurchaseOrderStatus::APPROVED;
        }

        $calculator = app(DeliveryStatusCalculator::class);

        foreach ($order->lines as $line) {
            $status = $calculator->quantityStatus($line->qtyOrdered, $line->qtyReceived);

            if (! in_array($status, [QuantityStatus::FULL, QuantityStatus::OVER], true)) {
                return PurchaseOrderStatus::PARTIAL;
            }
        }

        return PurchaseOrderStatus::COMPLETED;
    }
}
