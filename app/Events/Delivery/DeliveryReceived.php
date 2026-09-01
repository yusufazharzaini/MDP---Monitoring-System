<?php

declare(strict_types=1);

namespace App\Events\Delivery;

use App\Enums\AuditAction;

/**
 * Goods arrived and were booked in. By the time this fires the derived
 * statuses and the purchase order rollup are already settled, so a listener
 * reads a consistent picture.
 */
class DeliveryReceived extends AbstractDeliveryEvent
{
    public function auditAction(): AuditAction
    {
        return AuditAction::CREATED;
    }

    public function description(): string
    {
        return "Delivery {$this->goodsReceipt->delivery_number} diterima pada "
            .$this->goodsReceipt->delivery_date?->toDateString().'.';
    }
}
