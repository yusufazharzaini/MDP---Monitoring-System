<?php

declare(strict_types=1);

namespace App\Events\Delivery;

use App\Models\Delivery;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

abstract class AbstractDeliveryEvent implements DeliveryLifecycleEvent
{
    use Dispatchable;

    public function __construct(
        protected readonly Delivery $goodsReceipt,
        protected readonly ?User $user = null,
    ) {}

    public function delivery(): Delivery
    {
        return $this->goodsReceipt;
    }

    public function actor(): ?User
    {
        return $this->user;
    }
}
