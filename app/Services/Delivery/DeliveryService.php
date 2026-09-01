<?php

declare(strict_types=1);

namespace App\Services\Delivery;

use App\Actions\Delivery\SyncDeliveryItems;
use App\Enums\DeliveryStatus;
use App\Events\Delivery\DeliveryCancelled;
use App\Events\Delivery\DeliveryReceived;
use App\Events\Delivery\DeliveryUpdated;
use App\Exceptions\BusinessRuleException;
use App\Models\Delivery;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Services\Support\NumberGeneratorService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Booking, correcting and reversing goods receipts.
 *
 * This service owns the *transaction*; DeliveryStatusService owns the
 * *consequences*. Every write here ends by asking that service to settle the
 * derived statuses, the purchase order rollup and the order's own status - so
 * a receipt and the numbers it moves can never be saved apart.
 */
class DeliveryService
{
    public function __construct(
        private readonly SyncDeliveryItems $syncItems,
        private readonly DeliveryStatusService $statuses,
        private readonly NumberGeneratorService $numbers,
    ) {}

    /**
     * Book a receipt against an approved order.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<int, array<string, mixed>>  $lines
     */
    public function receive(
        PurchaseOrder $order,
        array $attributes,
        array $lines,
        ?User $actor = null,
    ): Delivery {
        $this->guardOrderAcceptsDeliveries($order);
        $this->guardDeliveryDate((string) $attributes['delivery_date']);

        return DB::transaction(function () use ($order, $attributes, $lines, $actor): Delivery {
            $delivery = new Delivery;
            $delivery->fill([
                ...$attributes,
                'purchase_order_id' => $order->getKey(),
                // The supplier and plant are the order's, not the form's: a
                // receipt cannot quietly re-attribute itself elsewhere.
                'supplier_id' => $order->supplier_id,
                'plant_id' => $order->plant_id,
            ]);

            $delivery->forceFill([
                'delivery_number' => $this->numbers->deliveryNumber(Carbon::parse((string) $attributes['delivery_date'])),
                'status' => DeliveryStatus::RECEIVED,
                'received_by' => $actor?->getKey(),
            ])->save();

            ($this->syncItems)($delivery, $lines);
            $this->statuses->recalculateForDelivery($delivery->refresh());

            DeliveryReceived::dispatch($delivery->refresh(), $actor);

            return $delivery;
        });
    }

    /**
     * Correct a receipt that has already been booked.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<int, array<string, mixed>>  $lines
     */
    public function update(
        Delivery $delivery,
        array $attributes,
        array $lines,
        ?User $actor = null,
    ): Delivery {
        $this->guardEditable($delivery);
        $this->guardDeliveryDate((string) ($attributes['delivery_date'] ?? $delivery->delivery_date));

        return DB::transaction(function () use ($delivery, $attributes, $lines, $actor): Delivery {
            $delivery->fill($attributes)->save();

            ($this->syncItems)($delivery, $lines);
            $this->statuses->recalculateForDelivery($delivery->refresh());

            DeliveryUpdated::dispatch($delivery->refresh(), $actor);

            return $delivery;
        });
    }

    /**
     * Reverse a receipt.
     *
     * The lines stay on the record - what was booked and later reversed is
     * itself history - but their verdicts are cleared and every order line the
     * receipt touched is recalculated without it.
     */
    public function cancel(Delivery $delivery, ?User $actor = null, ?string $reason = null): Delivery
    {
        if ($delivery->isCancelled()) {
            throw new BusinessRuleException(
                "Delivery {$delivery->delivery_number} sudah dibatalkan."
            );
        }

        return DB::transaction(function () use ($delivery, $actor, $reason): Delivery {
            $delivery->forceFill([
                'status' => DeliveryStatus::CANCELLED,
                'remarks' => $reason ?? $delivery->remarks,
            ])->save();

            $this->statuses->clearLineStatuses($delivery);

            // Recalculate every order line this receipt touched, now that it no
            // longer counts, so the purchase order falls back to PARTIAL or
            // APPROVED as the remaining receipts warrant.
            $this->statuses->recalculateForDelivery($delivery->refresh());

            DeliveryCancelled::dispatch($delivery->refresh(), $actor, $reason);

            return $delivery;
        });
    }

    /**
     * Goods may only be booked against a live, approved order.
     */
    private function guardOrderAcceptsDeliveries(PurchaseOrder $order): void
    {
        if (! $order->acceptsDeliveries()) {
            throw new BusinessRuleException(
                "Purchase order {$order->po_number} berstatus {$order->status->label()} "
                .'dan tidak dapat menerima delivery.'
            );
        }
    }

    private function guardEditable(Delivery $delivery): void
    {
        if ($delivery->isCancelled()) {
            throw new BusinessRuleException(
                "Delivery {$delivery->delivery_number} sudah dibatalkan dan tidak dapat diubah."
            );
        }
    }

    /**
     * Goods cannot arrive tomorrow. A future receipt date would make a delivery
     * count as on time against a schedule it has not yet had to meet.
     */
    private function guardDeliveryDate(string $date): void
    {
        if (Carbon::parse($date)->startOfDay()->isAfter(Carbon::now()->startOfDay())) {
            throw new BusinessRuleException(
                'Tanggal delivery tidak boleh berada di masa depan.'
            );
        }
    }
}
