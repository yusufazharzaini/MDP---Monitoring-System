<?php

declare(strict_types=1);

namespace App\Actions\Delivery;

use App\Exceptions\BusinessRuleException;
use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\PurchaseOrderItem;
use Illuminate\Support\Collection;

/**
 * Reconciles the lines of one goods receipt against what was booked.
 *
 * Two rules keep a receipt honest:
 *
 *   - every line must belong to the delivery's own purchase order, so a clerk
 *     cannot book goods against somebody else's commitment;
 *   - one receipt records a given order line at most once, which is the same
 *     rule the unique key enforces and the reason the KPI grain can be trusted.
 *
 * The derived statuses are deliberately *not* written here. They depend on
 * every other receipt for the same order line, so DeliveryStatusService settles
 * them once the whole receipt is in place.
 */
class SyncDeliveryItems
{
    /**
     * @param  array<int, array<string, mixed>>  $lines
     *
     * @throws BusinessRuleException
     */
    public function __invoke(Delivery $delivery, array $lines): void
    {
        $orderItems = $this->orderItemsFor($delivery);

        $this->guardOwnership($lines, $orderItems);
        $this->guardNoDuplicates($lines);

        $existing = $delivery->items()->get()->keyBy('purchase_order_item_id');
        $keep = [];

        foreach ($lines as $line) {
            $orderItemId = (int) $line['purchase_order_item_id'];
            $orderItem = $orderItems[$orderItemId];

            /** @var DeliveryItem|null $current */
            $current = $existing->get($orderItemId);

            $attributes = [
                'purchase_order_item_id' => $orderItemId,
                // Material and unit come from the order line, never from the
                // form: a receipt describes what was ordered arriving, not
                // something else turning up under its line number.
                'material_id' => $orderItem->material_id,
                'uom_id' => $orderItem->uom_id,
                'qty_received' => $line['qty_received'],
                'condition' => $line['condition'],
                'remarks' => $line['remarks'] ?? null,
            ];

            $keep[] = $current === null
                ? $delivery->items()->create($attributes)->getKey()
                : tap($current)->update($attributes)->getKey();
        }

        // `whereNotIn('id', [])` is always true, so an empty keep list would
        // wipe the lines just written.
        $delivery->items()
            ->when($keep !== [], fn ($query) => $query->whereNotIn('id', $keep))
            ->delete();
    }

    /**
     * @return Collection<int, PurchaseOrderItem>
     */
    private function orderItemsFor(Delivery $delivery): Collection
    {
        return PurchaseOrderItem::query()
            ->where('purchase_order_id', $delivery->purchase_order_id)
            ->get()
            ->keyBy('id');
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @param  Collection<int, PurchaseOrderItem>  $orderItems
     *
     * @throws BusinessRuleException
     */
    private function guardOwnership(array $lines, Collection $orderItems): void
    {
        foreach ($lines as $line) {
            if (! $orderItems->has((int) $line['purchase_order_item_id'])) {
                throw new BusinessRuleException(
                    'Baris penerimaan harus mengacu pada item purchase order yang sama.'
                );
            }
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     *
     * @throws BusinessRuleException
     */
    private function guardNoDuplicates(array $lines): void
    {
        $ids = array_map(static fn (array $line): int => (int) $line['purchase_order_item_id'], $lines);

        if (count($ids) !== count(array_unique($ids))) {
            throw new BusinessRuleException(
                'Satu item purchase order hanya boleh muncul sekali dalam satu delivery.'
            );
        }
    }
}
