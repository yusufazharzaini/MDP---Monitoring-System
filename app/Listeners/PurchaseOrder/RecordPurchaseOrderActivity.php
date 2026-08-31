<?php

declare(strict_types=1);

namespace App\Listeners\PurchaseOrder;

use App\Events\PurchaseOrder\PurchaseOrderLifecycleEvent;
use App\Services\Audit\AuditLogService;

/**
 * Writes every purchase order transition into the audit trail.
 *
 * One listener covers all three events because they share a shape: each knows
 * its own audit action and its own sentence. A new transition needs a new
 * event, not a new listener.
 */
class RecordPurchaseOrderActivity
{
    public function __construct(
        private readonly AuditLogService $audit,
    ) {}

    public function handle(PurchaseOrderLifecycleEvent $event): void
    {
        $this->audit->record(
            $event->auditAction(),
            'PurchaseOrder',
            $event->order()->getKey(),
            null,
            [
                'po_number' => $event->order()->po_number,
                'status' => $event->order()->status->value,
                'description' => $event->description(),
            ],
        );
    }
}
