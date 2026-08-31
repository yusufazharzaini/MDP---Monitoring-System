<?php

declare(strict_types=1);

namespace App\Actions\PurchaseOrder;

use App\Models\PurchaseOrder;

/**
 * Keeps purchase_orders.total_amount in step with its lines.
 *
 * The total is denormalised so the order list can show a value without joining
 * and summing every line. This action is its only writer, so the two cannot
 * drift apart.
 */
class RecalculatePurchaseOrderTotal
{
    public function __invoke(PurchaseOrder $order): PurchaseOrder
    {
        $total = $order->items()->sum('amount');

        $order->forceFill(['total_amount' => round((float) $total, 4)])->save();

        return $order;
    }
}
