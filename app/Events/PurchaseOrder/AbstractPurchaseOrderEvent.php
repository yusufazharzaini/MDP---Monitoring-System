<?php

declare(strict_types=1);

namespace App\Events\PurchaseOrder;

use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Shared construction for the lifecycle events. The contract itself lives in
 * PurchaseOrderLifecycleEvent, which is what listeners bind to.
 */
abstract class AbstractPurchaseOrderEvent implements PurchaseOrderLifecycleEvent
{
    use Dispatchable;

    public function __construct(
        protected readonly PurchaseOrder $purchaseOrder,
        protected readonly ?User $user = null,
    ) {}

    public function order(): PurchaseOrder
    {
        return $this->purchaseOrder;
    }

    public function actor(): ?User
    {
        return $this->user;
    }
}
