<?php

declare(strict_types=1);

namespace App\Listeners\Delivery;

use App\Events\Delivery\DeliveryLifecycleEvent;
use App\Services\Audit\AuditLogService;

class RecordDeliveryActivity
{
    public function __construct(
        private readonly AuditLogService $audit,
    ) {}

    public function handle(DeliveryLifecycleEvent $event): void
    {
        $delivery = $event->delivery();

        $this->audit->record(
            $event->auditAction(),
            'Delivery',
            $delivery->getKey(),
            null,
            [
                'delivery_number' => $delivery->delivery_number,
                'status' => $delivery->status->value,
                'description' => $event->description(),
            ],
        );
    }
}
