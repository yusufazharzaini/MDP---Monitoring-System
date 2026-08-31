<?php

declare(strict_types=1);

namespace App\Events\PurchaseOrder;

use App\Enums\AuditAction;

class PurchaseOrderApproved extends AbstractPurchaseOrderEvent
{
    public function auditAction(): AuditAction
    {
        return AuditAction::APPROVED;
    }

    public function description(): string
    {
        return "Purchase order {$this->purchaseOrder->po_number} disetujui dan siap menerima delivery.";
    }
}
