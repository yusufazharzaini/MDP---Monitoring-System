<?php

declare(strict_types=1);

namespace App\Events\Delivery;

use App\Enums\AuditAction;
use App\Models\Delivery;
use App\Models\User;

class DeliveryCancelled extends AbstractDeliveryEvent
{
    public function __construct(
        Delivery $goodsReceipt,
        ?User $user = null,
        public readonly ?string $reason = null,
    ) {
        parent::__construct($goodsReceipt, $user);
    }

    public function auditAction(): AuditAction
    {
        return AuditAction::CANCELLED;
    }

    public function description(): string
    {
        return "Delivery {$this->goodsReceipt->delivery_number} dibatalkan."
            .($this->reason === null ? '' : " Alasan: {$this->reason}");
    }
}
