<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Models\User;

/**
 * Purchase orders carry two abilities master data does not: approving one, and
 * cancelling one. Both are separate permissions because they are separate jobs -
 * purchasing raises an order, management releases it.
 *
 * The policy also encodes what the *record's own state* allows. A completed
 * order is immutable no matter who is asking, so "may this user edit" and
 * "is this order still editable" are answered in the same place.
 */
class PurchaseOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('po.view');
    }

    public function view(User $user, PurchaseOrder $order): bool
    {
        return $user->can('po.view');
    }

    public function create(User $user): bool
    {
        return $user->can('po.create');
    }

    /**
     * Only a draft or submitted order may be edited; once approved its lines
     * are a commitment the supplier is already working to.
     */
    public function update(User $user, PurchaseOrder $order): bool
    {
        return $user->can('po.update') && $order->status->isEditable();
    }

    public function submit(User $user, PurchaseOrder $order): bool
    {
        return $user->can('po.update') && $order->status === PurchaseOrderStatus::DRAFT;
    }

    public function approve(User $user, PurchaseOrder $order): bool
    {
        return $user->can('po.approve')
            && $order->status === PurchaseOrderStatus::SUBMITTED;
    }

    /**
     * A completed order is history; a cancelled one is already cancelled.
     */
    public function cancel(User $user, PurchaseOrder $order): bool
    {
        return $user->can('po.cancel') && ! $order->status->isFinal();
    }

    /**
     * Purchase orders are never deleted - cancellation is the only exit.
     */
    public function delete(User $user, PurchaseOrder $order): bool
    {
        return false;
    }
}
