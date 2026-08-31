<?php

declare(strict_types=1);

namespace App\Events\Delivery;

use App\Enums\AuditAction;

class DeliveryUpdated extends AbstractDeliveryEvent
{
    public function auditAction(): AuditAction
    {
        return AuditAction::UPDATED;
    }

    public function description(): string
    {
        return "Delivery {$this->goodsReceipt->delivery_number} dikoreksi.";
    }
}
