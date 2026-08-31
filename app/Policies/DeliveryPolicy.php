<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Delivery;
use App\Models\User;

/**
 * Receiving goods and cancelling a receipt are separate jobs: a warehouse clerk
 * books what arrived, while reversing that record is a logistics decision.
 *
 * As with purchase orders, the record's own state is part of the answer - a
 * cancelled delivery is closed to everyone.
 */
class DeliveryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('delivery.view');
    }

    public function view(User $user, Delivery $delivery): bool
    {
        return $user->can('delivery.view');
    }

    public function create(User $user): bool
    {
        return $user->can('delivery.create');
    }

    /**
     * A cancelled receipt is a closed record; correcting one means booking a
     * new receipt, not editing the reversal.
     */
    public function update(User $user, Delivery $delivery): bool
    {
        return $user->can('delivery.update') && ! $delivery->isCancelled();
    }

    public function cancel(User $user, Delivery $delivery): bool
    {
        return $user->can('delivery.cancel') && ! $delivery->isCancelled();
    }

    /**
     * Deliveries are never deleted - cancellation keeps the receiving history.
     */
    public function delete(User $user, Delivery $delivery): bool
    {
        return false;
    }
}
