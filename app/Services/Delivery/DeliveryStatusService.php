<?php

declare(strict_types=1);

namespace App\Services\Delivery;

use App\Enums\DeliveryStatus;
use App\Enums\OverallDeliveryStatus;
use App\Enums\PurchaseOrderStatus;
use App\Enums\QuantityStatus;
use App\Enums\TimelinessStatus;
use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Services\Dashboard\DashboardCache;
use Illuminate\Support\Collection;

/**
 * Owns every derived status column in the schema.
 *
 * Nothing else writes purchase_order_items.qty_received / *_status or
 * delivery_items.*_status - keeping a single writer is what makes the
 * denormalised rollup trustworthy.
 */
class DeliveryStatusService
{
    public function __construct(
        private readonly DeliveryStatusCalculator $calculator,
        private readonly DashboardCache $dashboardCache,
    ) {}

    /**
     * Recalculate everything touched by a delivery: each of its lines, the PO
     * items those lines fulfil, the delivery header and the parent PO.
     *
     * Callers are expected to already be inside a transaction.
     */
    public function recalculateForDelivery(Delivery $delivery): void
    {
        // Every number the dashboard shows is derived from what this method
        // settles, so the cached payloads retire with it. Without this the
        // screen shows figures that predate the receipt for the whole TTL.
        $this->dashboardCache->flush();

        $itemIds = $delivery->items()->pluck('purchase_order_item_id')->unique();

        PurchaseOrderItem::query()
            ->whereIn('id', $itemIds)
            ->get()
            ->each(fn (PurchaseOrderItem $item) => $this->recalculateForPurchaseOrderItem($item));

        $this->refreshDeliveryStatus($delivery->fresh(['items']) ?? $delivery);
        $this->refreshPurchaseOrderStatus($delivery->purchaseOrder()->first());
    }

    /**
     * Rebuild the cumulative fulfilment picture for one purchase order line.
     *
     * Delivery lines are replayed in receipt order so each line records the
     * cumulative position at the moment it arrived (assumption A3).
     */
    public function recalculateForPurchaseOrderItem(PurchaseOrderItem $item): void
    {
        $lines = $this->countableLinesFor($item);
        $ordered = (float) $item->qty_ordered;
        $schedule = $item->schedule_delivery_date;

        $cumulative = 0.0;
        $firstReceipt = null;
        $lastReceipt = null;

        foreach ($lines as $line) {
            $cumulative += $line->effectiveQuantity();
            $actual = $line->delivery->delivery_date;

            $verdict = $this->calculator->evaluate($ordered, $cumulative, $actual, $schedule);

            $line->forceFill([
                'timeliness_status' => $verdict['timeliness'],
                'quantity_status' => $verdict['quantity'],
                'overall_status' => $verdict['overall'],
                'days_late' => $verdict['days_late'],
            ])->save();

            $firstReceipt ??= $actual;
            $lastReceipt = $actual;
        }

        $itemVerdict = $this->calculator->evaluate($ordered, $cumulative, $lastReceipt, $schedule);

        $item->forceFill([
            'qty_received' => round($cumulative, 4),
            'first_receipt_date' => $firstReceipt,
            'last_receipt_date' => $lastReceipt,
            'fulfillment_status' => $itemVerdict['quantity'],
            'timeliness_status' => $itemVerdict['timeliness'],
            'overall_status' => $itemVerdict['overall'],
        ])->save();
    }

    /**
     * Derive the delivery header's operational status from its lines.
     *
     * DeliveryStatus::RECEIVED is an operator-set state (goods booked, not yet
     * verified); once lines exist this method resolves PARTIAL vs COMPLETED.
     */
    public function refreshDeliveryStatus(Delivery $delivery): void
    {
        if ($delivery->isCancelled()) {
            return;
        }

        $items = $delivery->relationLoaded('items') ? $delivery->items : $delivery->items()->get();

        if ($items->isEmpty()) {
            $delivery->forceFill(['status' => DeliveryStatus::PENDING])->save();

            return;
        }

        $outstanding = PurchaseOrderItem::query()
            ->whereIn('id', $items->pluck('purchase_order_item_id')->unique())
            ->whereIn('fulfillment_status', [QuantityStatus::PENDING, QuantityStatus::SHORT])
            ->exists();

        $delivery->forceFill([
            'status' => $outstanding ? DeliveryStatus::PARTIAL : DeliveryStatus::COMPLETED,
        ])->save();
    }

    /**
     * Roll the purchase order forward: COMPLETED once every line is satisfied,
     * PARTIAL while receipts are in progress. Terminal states are never reopened.
     */
    public function refreshPurchaseOrderStatus(?PurchaseOrder $order): void
    {
        if ($order === null || $order->status === PurchaseOrderStatus::CANCELLED) {
            return;
        }

        $items = $order->items()->get(['qty_received', 'fulfillment_status']);

        if ($items->isEmpty()) {
            return;
        }

        $allSatisfied = $items->every(
            static fn (PurchaseOrderItem $item): bool => in_array(
                $item->fulfillment_status,
                [QuantityStatus::FULL, QuantityStatus::OVER],
                true,
            ),
        );

        $anyReceived = $items->contains(
            static fn (PurchaseOrderItem $item): bool => (float) $item->qty_received > 0,
        );

        $status = match (true) {
            $allSatisfied => PurchaseOrderStatus::COMPLETED,
            $anyReceived => PurchaseOrderStatus::PARTIAL,
            default => $order->status->isEditable() ? $order->status : PurchaseOrderStatus::APPROVED,
        };

        if ($status !== $order->status) {
            $order->forceFill(['status' => $status])->save();
        }
    }

    /**
     * Delivery lines that count towards performance, in receipt order.
     *
     * Ordering by date then id keeps the cumulative replay deterministic when
     * several receipts land on the same day.
     *
     * @return Collection<int, DeliveryItem>
     */
    private function countableLinesFor(PurchaseOrderItem $item): Collection
    {
        return DeliveryItem::query()
            ->with('delivery:id,delivery_date,status')
            ->where('purchase_order_item_id', $item->getKey())
            ->whereHas('delivery', static fn ($q) => $q->where('status', '!=', DeliveryStatus::CANCELLED))
            ->join('deliveries', 'deliveries.id', '=', 'delivery_items.delivery_id')
            ->orderBy('deliveries.delivery_date')
            ->orderBy('delivery_items.id')
            ->select('delivery_items.*')
            ->get();
    }

    /**
     * Reset a cancelled delivery's own lines so they stop showing a verdict.
     */
    public function clearLineStatuses(Delivery $delivery): void
    {
        $this->dashboardCache->flush();

        $delivery->items()->update([
            'timeliness_status' => TimelinessStatus::PENDING,
            'quantity_status' => QuantityStatus::PENDING,
            'overall_status' => OverallDeliveryStatus::PENDING,
            'days_late' => 0,
        ]);
    }
}
