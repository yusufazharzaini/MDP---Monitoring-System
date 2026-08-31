<?php

declare(strict_types=1);

namespace App\Events\PurchaseOrder;

use App\Enums\AuditAction;
use App\Models\PurchaseOrder;
use App\Models\User;

class PurchaseOrderCancelled extends AbstractPurchaseOrderEvent
{
    public function __construct(
        PurchaseOrder $purchaseOrder,
        ?User $user = null,
        public readonly ?string $reason = null,
    ) {
        parent::__construct($purchaseOrder, $user);
    }

    public function auditAction(): AuditAction
    {
        return AuditAction::CANCELLED;
    }

    public function description(): string
    {
        return "Purchase order {$this->purchaseOrder->po_number} dibatalkan."
            .($this->reason === null ? '' : " Alasan: {$this->reason}");
    }
}
