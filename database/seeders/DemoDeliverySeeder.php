<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\DeliveryItemCondition;
use App\Enums\DeliveryStatus;
use App\Enums\QuantityStatus;
use App\Models\Material;
use App\Models\Plant;
use App\Models\User;
use App\Services\Delivery\DeliveryStatusCalculator;
use Database\Seeders\Support\DemoOrderPlanner;
use Database\Seeders\Support\DemoOrderSpec;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Books the receipts for the demo purchase orders.
 *
 * Rebuilds the identical plan as DemoPurchaseOrderSeeder (the planner is pure)
 * and matches it back to the persisted lines by po_number + line_no, so the two
 * seeders stay in step without sharing state.
 */
class DemoDeliverySeeder extends Seeder
{
    private const CHUNK = 500;

    public function run(): void
    {
        $planner = new DemoOrderPlanner(
            Carbon::now()->startOfMonth(),
            DemoMaterialSeeder::normalMaterialCodes(),
            Plant::query()->orderBy('id')->pluck('code')->all(),
        );

        $orders = array_values(array_filter(
            $planner->build(),
            static fn (DemoOrderSpec $order): bool => $order->isDelivered(),
        ));

        $purchaseOrders = DB::table('purchase_orders')
            ->select('id', 'po_number', 'supplier_id', 'plant_id')
            ->get()
            ->keyBy('po_number');

        $items = DB::table('purchase_order_items')
            ->select('id', 'purchase_order_id', 'line_no', 'material_id', 'uom_id')
            ->get()
            ->groupBy('purchase_order_id');

        $materials = Material::query()->pluck('id', 'code');
        $receivedBy = User::query()->orderBy('id')->value('id');
        $now = Carbon::now();

        $deliveryRows = [];

        foreach ($orders as $order) {
            $header = $purchaseOrders[$order->poNumber];

            $deliveryRows[] = [
                'ulid' => (string) Str::ulid(),
                'delivery_number' => $order->deliveryNumber,
                'purchase_order_id' => $header->id,
                'supplier_id' => $header->supplier_id,
                'plant_id' => $header->plant_id,
                'delivery_date' => $order->deliveryDate,
                'do_number' => 'DO-'.substr($order->deliveryNumber, 3),
                'vehicle_number' => 'B '.(1000 + (crc32($order->deliveryNumber) % 9000)).' TRC',
                'driver_name' => 'Driver '.substr($order->deliveryNumber, -4),
                'received_by' => $receivedBy,
                'status' => $this->deliveryStatus($order)->value,
                'remarks' => $order->daysLate > 0 ? 'Terlambat '.$order->daysLate.' hari' : null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($deliveryRows, self::CHUNK) as $chunk) {
            DB::table('deliveries')->insert($chunk);
        }

        $deliveryIds = DB::table('deliveries')->pluck('id', 'delivery_number');
        $calculator = app(DeliveryStatusCalculator::class);
        $itemRows = [];

        foreach ($orders as $order) {
            $header = $purchaseOrders[$order->poNumber];
            $deliveryId = $deliveryIds[$order->deliveryNumber];
            $lineIndex = $items[$header->id]->keyBy('line_no');

            $schedule = Carbon::parse($order->scheduleDate);
            $actual = Carbon::parse((string) $order->deliveryDate);

            foreach ($order->lines as $line) {
                $poItem = $lineIndex[$line->lineNo];
                $verdict = $calculator->evaluate($line->qtyOrdered, $line->qtyReceived, $actual, $schedule);

                $itemRows[] = [
                    'delivery_id' => $deliveryId,
                    'purchase_order_item_id' => $poItem->id,
                    'material_id' => $materials[$line->materialCode],
                    'uom_id' => $poItem->uom_id,
                    'qty_received' => $line->qtyReceived,
                    'condition' => DeliveryItemCondition::GOOD->value,
                    'timeliness_status' => $verdict['timeliness']->value,
                    'quantity_status' => $verdict['quantity']->value,
                    'overall_status' => $verdict['overall']->value,
                    'days_late' => $verdict['days_late'],
                    'remarks' => $line->short ? 'Quantity kurang dari PO' : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($itemRows, self::CHUNK) as $chunk) {
            DB::table('delivery_items')->insert($chunk);
        }
    }

    /**
     * COMPLETED when the receipt satisfies every line it touches, PARTIAL when
     * any line arrived short.
     */
    private function deliveryStatus(DemoOrderSpec $order): DeliveryStatus
    {
        $calculator = app(DeliveryStatusCalculator::class);

        foreach ($order->lines as $line) {
            $status = $calculator->quantityStatus($line->qtyOrdered, $line->qtyReceived);

            if (! in_array($status, [QuantityStatus::FULL, QuantityStatus::OVER], true)) {
                return DeliveryStatus::PARTIAL;
            }
        }

        return DeliveryStatus::COMPLETED;
    }
}
