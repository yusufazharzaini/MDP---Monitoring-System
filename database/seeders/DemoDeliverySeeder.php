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
use Database\Seeders\Support\DemoLineSpec;
use Database\Seeders\Support\DemoOrderPlanner;
use Database\Seeders\Support\DemoOrderSpec;
use Database\Seeders\Support\DemoReceiptSpec;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Books the receipts for the demo purchase orders, including the split
 * shipments where one order line is filled by two deliveries.
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
        $orders = array_values(array_filter(
            (new DemoOrderPlanner(
                Carbon::now()->startOfMonth(),
                DemoMaterialSeeder::normalMaterialCodes(),
                Plant::query()->orderBy('id')->pluck('code')->all(),
            ))->build(),
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
        $calculator = app(DeliveryStatusCalculator::class);
        $now = Carbon::now();

        $deliveryRows = [];

        foreach ($orders as $order) {
            $header = $purchaseOrders[$order->poNumber];

            foreach ($order->receipts as $receipt) {
                $deliveryRows[] = [
                    'ulid' => (string) Str::ulid(),
                    'delivery_number' => $receipt->deliveryNumber,
                    'purchase_order_id' => $header->id,
                    'supplier_id' => $header->supplier_id,
                    'plant_id' => $header->plant_id,
                    'delivery_date' => $receipt->deliveryDate,
                    'do_number' => 'DO-'.substr($receipt->deliveryNumber, 3),
                    'vehicle_number' => 'B '.(1000 + (crc32($receipt->deliveryNumber) % 9000)).' TRC',
                    'driver_name' => 'Driver '.substr($receipt->deliveryNumber, -4),
                    'received_by' => $receivedBy,
                    'status' => $this->deliveryStatus($order, $receipt, $calculator)->value,
                    'remarks' => $this->remarksFor($order, $receipt),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($deliveryRows, self::CHUNK) as $chunk) {
            DB::table('deliveries')->insert($chunk);
        }

        $deliveryIds = DB::table('deliveries')->pluck('id', 'delivery_number');
        $itemRows = [];

        foreach ($orders as $order) {
            $header = $purchaseOrders[$order->poNumber];
            $lineIndex = $items[$header->id]->keyBy('line_no');
            $schedule = Carbon::parse($order->scheduleDate);

            // Replay receipts in date order so each line records the cumulative
            // position at the moment it arrived - the rule in docs/03 section 2.
            $cumulative = [];

            foreach ($this->orderedReceipts($order) as $receipt) {
                foreach ($receipt->quantities as $lineNo => $quantity) {
                    $line = $this->lineFor($order, $lineNo);
                    $poItem = $lineIndex[$lineNo];

                    $cumulative[$lineNo] = ($cumulative[$lineNo] ?? 0.0) + $quantity;

                    $verdict = $calculator->evaluate(
                        $line->qtyOrdered,
                        $cumulative[$lineNo],
                        Carbon::parse($receipt->deliveryDate),
                        $schedule,
                    );

                    $itemRows[] = [
                        'delivery_id' => $deliveryIds[$receipt->deliveryNumber],
                        'purchase_order_item_id' => $poItem->id,
                        'material_id' => $materials[$line->materialCode],
                        'uom_id' => $poItem->uom_id,
                        'qty_received' => $quantity,
                        'condition' => DeliveryItemCondition::GOOD->value,
                        'timeliness_status' => $verdict['timeliness']->value,
                        'quantity_status' => $verdict['quantity']->value,
                        'overall_status' => $verdict['overall']->value,
                        'days_late' => $verdict['days_late'],
                        'remarks' => $verdict['quantity'] === QuantityStatus::SHORT
                            ? 'Quantity kurang dari PO'
                            : null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
        }

        foreach (array_chunk($itemRows, self::CHUNK) as $chunk) {
            DB::table('delivery_items')->insert($chunk);
        }
    }

    /**
     * @return array<int, DemoReceiptSpec>
     */
    private function orderedReceipts(DemoOrderSpec $order): array
    {
        $receipts = $order->receipts;

        usort(
            $receipts,
            static fn (DemoReceiptSpec $a, DemoReceiptSpec $b): int => $a->deliveryDate <=> $b->deliveryDate,
        );

        return $receipts;
    }

    private function lineFor(DemoOrderSpec $order, int $lineNo): DemoLineSpec
    {
        foreach ($order->lines as $line) {
            if ($line->lineNo === $lineNo) {
                return $line;
            }
        }

        throw new \LogicException("Order {$order->poNumber} has no line {$lineNo}.");
    }

    /**
     * COMPLETED when this receipt settles every line it touches, PARTIAL while
     * quantity is still outstanding.
     */
    private function deliveryStatus(
        DemoOrderSpec $order,
        DemoReceiptSpec $receipt,
        DeliveryStatusCalculator $calculator,
    ): DeliveryStatus {
        foreach (array_keys($receipt->quantities) as $lineNo) {
            $line = $this->lineFor($order, $lineNo);
            $status = $calculator->quantityStatus($line->qtyOrdered, $order->receivedFor($lineNo));

            if (! in_array($status, [QuantityStatus::FULL, QuantityStatus::OVER], true)) {
                return DeliveryStatus::PARTIAL;
            }
        }

        return DeliveryStatus::COMPLETED;
    }

    private function remarksFor(DemoOrderSpec $order, DemoReceiptSpec $receipt): ?string
    {
        if ($receipt->daysLate > 0) {
            return 'Terlambat '.$receipt->daysLate.' hari';
        }

        if (count($order->receipts) > 1) {
            return 'Pengiriman bertahap (partial delivery)';
        }

        return null;
    }
}
