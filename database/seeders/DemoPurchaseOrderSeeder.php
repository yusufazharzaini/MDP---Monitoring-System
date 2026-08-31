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
 * runtime uses, replaying a line's receipts in date order exactly as
 * DeliveryStatusService would. SeedConsistencyTest asserts the two agree.
 */
class DemoPurchaseOrderSeeder extends Seeder
{
    private const CHUNK = 500;

    public function run(): void
    {
        $orders = (new DemoOrderPlanner(
            Carbon::now()->startOfMonth(),
            DemoMaterialSeeder::normalMaterialCodes(),
            Plant::query()->orderBy('id')->pluck('code')->all(),
        ))->build();

        $suppliers = Supplier::query()->pluck('id', 'code');
        $plants = Plant::query()->pluck('id', 'code');
        $materials = Material::query()->get(['id', 'code', 'uom_id'])->keyBy('code');
        $warehouses = Warehouse::query()
            ->orderBy('id')
            ->get(['id', 'plant_id'])
            ->groupBy('plant_id')
            ->map(static fn ($group) => $group->first()->id);
        $createdBy = User::query()->orderBy('id')->value('id');

        $calculator = app(DeliveryStatusCalculator::class);
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
                'status' => $this->orderStatus($order, $calculator)->value,
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
        $itemRows = [];

        foreach ($orders as $order) {
            $schedule = Carbon::parse($order->scheduleDate);
            $receiptDates = $this->receiptDates($order);

            foreach ($order->lines as $line) {
                $material = $materials[$line->materialCode];
                $received = $order->receivedFor($line->lineNo);
                $settled = $received > 0 ? end($receiptDates) : null;

                $verdict = $calculator->evaluate(
                    $line->qtyOrdered,
                    $received,
                    $settled === null ? null : Carbon::parse($settled),
                    $schedule,
                );

                $itemRows[] = [
                    'purchase_order_id' => $orderIds[$order->poNumber],
                    'material_id' => $material->id,
                    'warehouse_id' => $warehouses[$plants[$order->plantCode]],
                    'uom_id' => $material->uom_id,
                    'line_no' => $line->lineNo,
                    'schedule_delivery_date' => $order->scheduleDate,
                    'qty_ordered' => $line->qtyOrdered,
                    'unit_price' => $line->unitPrice,
                    'amount' => $line->amount(),
                    'qty_received' => $received,
                    'first_receipt_date' => $received > 0 ? reset($receiptDates) : null,
                    'last_receipt_date' => $settled,
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
     * Receipt dates in chronological order - the order DeliveryStatusService
     * replays them in.
     *
     * @return array<int, string>
     */
    private function receiptDates(DemoOrderSpec $order): array
    {
        $dates = array_map(
            static fn ($receipt): string => $receipt->deliveryDate,
            $order->receipts,
        );

        sort($dates);

        return $dates;
    }

    /**
     * A demo order is COMPLETED when every line is satisfied, PARTIAL when some
     * quantity is still outstanding, and APPROVED while nothing has arrived.
     */
    private function orderStatus(DemoOrderSpec $order, DeliveryStatusCalculator $calculator): PurchaseOrderStatus
    {
        if (! $order->isDelivered()) {
            return PurchaseOrderStatus::APPROVED;
        }

        foreach ($order->lines as $line) {
            $status = $calculator->quantityStatus($line->qtyOrdered, $order->receivedFor($line->lineNo));

            if (! in_array($status, [QuantityStatus::FULL, QuantityStatus::OVER], true)) {
                return PurchaseOrderStatus::PARTIAL;
            }
        }

        return PurchaseOrderStatus::COMPLETED;
    }
}
