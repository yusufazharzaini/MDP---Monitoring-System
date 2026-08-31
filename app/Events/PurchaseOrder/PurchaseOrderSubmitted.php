<?php

declare(strict_types=1);

namespace App\Events\PurchaseOrder;

use App\Enums\AuditAction;

class PurchaseOrderSubmitted extends AbstractPurchaseOrderEvent
{
    public function auditAction(): AuditAction
    {
        return AuditAction::SUBMITTED;
    }

    public function description(): string
    {
        return "Purchase order {$this->purchaseOrder->po_number} diajukan untuk approval.";
    }
}
