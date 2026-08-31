<?php

declare(strict_types=1);

namespace App\Actions\PurchaseOrder;

use App\Exceptions\BusinessRuleException;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Reconciles a purchase order's lines against what the form submitted.
 *
 * Editing an order is not "delete everything and re-insert": a line that has
 * already been received carries a receipt history and a rollup that must
 * survive the edit. So lines are matched by id, and two rules protect what has
 * already happened:
 *
 *   - a line that has receipts cannot be removed;
 *   - its ordered quantity cannot be reduced below what has already arrived,
 *     which would leave the order permanently over-delivered on paper.
 *
 * Line numbers are re-sequenced from 1 on every sync, so the numbering the user
 * sees always matches the order of the rows on screen.
 */
class SyncPurchaseOrderItems
{
    /**
     * Line numbers are unique per order, so a sync that renumbers in place
     * collides the moment one line takes a number another still holds. Every
     * line is therefore parked on a temporary number first and brought down to
     * its final position last.
     *
     * Existing and new lines are parked in *separate* ranges: they are numbered
     * by different counters, so sharing one range would let a new line land on
     * a parked existing one. Both sit well clear of the 500-line cap the
     * request enforces and well inside the column's range.
     */
    private const PARK_EXISTING = 10_000;

    private const PARK_NEW = 20_000;

    /**
     * @param  array<int, array<string, mixed>>  $lines
     *
     * @throws BusinessRuleException
     */
    public function __invoke(PurchaseOrder $order, array $lines): void
    {
        $existing = $order->items()->with('material:id,code')->get()->keyBy('id');
        $submittedIds = collect($lines)->pluck('id')->filter()->map(fn ($id): int => (int) $id);

        $this->guardRemovals($existing, $submittedIds);

        // Park the existing lines out of the way first, so a row moving from
        // position 3 to position 1 cannot collide with the row still sitting
        // at 1. They were already unique, so they stay unique when shifted.
        $order->items()->update(['line_no' => DB::raw('line_no + '.self::PARK_EXISTING)]);

        /**
         * Ids to keep, gathered as we go - a newly created line has no id until
         * it is written, so the survivors cannot be known up front.
         *
         * @var array<int, int> $keep
         */
        $keep = [];
        $position = 0;

        foreach ($lines as $line) {
            $position++;
            $id = isset($line['id']) ? (int) $line['id'] : null;
            $current = $id === null ? null : $existing->get($id);

            $keep[] = $current === null
                ? $this->createLine($order, $line, self::PARK_NEW + $position)->getKey()
                // An existing line keeps the number it was parked on; the final
                // renumber below is what settles its position.
                : $this->updateLine($current, $line)->getKey();
        }

        // Guarded against an empty keep list: `whereNotIn('id', [])` compiles to
        // a condition that is always true, which would delete every line the
        // loop just wrote.
        $order->items()
            ->when($keep !== [], fn ($query) => $query->whereNotIn('id', $keep))
            ->delete();

        $this->renumber($order, $keep);
    }

    /**
     * Bring the parked lines down to 1..n, in the order the form submitted them.
     *
     * @param  array<int, int>  $orderedIds
     */
    private function renumber(PurchaseOrder $order, array $orderedIds): void
    {
        foreach ($orderedIds as $index => $id) {
            $order->items()->whereKey($id)->update(['line_no' => $index + 1]);
        }
    }

    /**
     * @param  Collection<int, PurchaseOrderItem>  $existing
     * @param  Collection<int, int>  $submittedIds
     *
     * @throws BusinessRuleException
     */
    private function guardRemovals(Collection $existing, Collection $submittedIds): void
    {
        $removed = $existing->reject(
            fn (PurchaseOrderItem $item): bool => $submittedIds->contains($item->getKey()),
        );

        foreach ($removed as $item) {
            if ((float) $item->qty_received > 0) {
                throw new BusinessRuleException(
                    "Baris material {$item->material?->code} tidak dapat dihapus karena sudah menerima "
                    .rtrim(rtrim((string) $item->qty_received, '0'), '.').' unit.'
                );
            }
        }
    }

    /**
     * @param  array<string, mixed>  $line
     */
    private function createLine(PurchaseOrder $order, array $line, int $lineNo): PurchaseOrderItem
    {
        /** @var PurchaseOrderItem $item */
        $item = $order->items()->create([
            ...$this->attributes($line),
            'line_no' => $lineNo,
        ]);

        // amount is derived and therefore not fillable, so it is written here.
        $item->forceFill(['amount' => $this->amount($line)])->save();

        return $item;
    }

    /**
     * @param  array<string, mixed>  $line
     *
     * @throws BusinessRuleException
     */
    private function updateLine(PurchaseOrderItem $item, array $line): PurchaseOrderItem
    {
        $ordered = (float) $line['qty_ordered'];

        if ($ordered < (float) $item->qty_received) {
            throw new BusinessRuleException(
                "Quantity material {$item->material?->code} tidak boleh lebih kecil dari "
                .'quantity yang sudah diterima.'
            );
        }

        $item->fill($this->attributes($line));
        $item->forceFill(['amount' => $this->amount($line)])->save();

        return $item;
    }

    /**
     * @param  array<string, mixed>  $line
     * @return array<string, mixed>
     */
    private function attributes(array $line): array
    {
        return [
            'material_id' => $line['material_id'],
            'warehouse_id' => $line['warehouse_id'],
            'uom_id' => $line['uom_id'],
            'schedule_delivery_date' => $line['schedule_delivery_date'],
            'qty_ordered' => $line['qty_ordered'],
            'unit_price' => $line['unit_price'] ?? 0,
            'remarks' => $line['remarks'] ?? null,
        ];
    }

    /**
     * Amount is derived, never taken from the form - a client that posts its own
     * total is a client that can post the wrong one.
     *
     * @param  array<string, mixed>  $line
     */
    private function amount(array $line): float
    {
        return round((float) $line['qty_ordered'] * (float) ($line['unit_price'] ?? 0), 4);
    }
}
