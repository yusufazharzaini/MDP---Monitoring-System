<?php

declare(strict_types=1);

namespace App\Listeners\PurchaseOrder;

use App\Events\PurchaseOrder\PurchaseOrderSubmitted;
use App\Models\User;
use App\Notifications\PurchaseOrder\PurchaseOrderAwaitingApproval;
use Illuminate\Support\Facades\Notification;

/**
 * Tells the people who can release an order that one is waiting.
 *
 * Bound to the concrete submitted event rather than the lifecycle interface:
 * approving, cancelling and the rest are not requests for anybody's attention.
 */
class NotifyApproversOfSubmission
{
    public function handle(PurchaseOrderSubmitted $event): void
    {
        $order = $event->order();

        $approvers = User::query()
            ->active()
            ->permission('po.approve')
            // The buyer already knows; a system that notifies you of your own
            // action teaches people to ignore it.
            ->when($event->actor() !== null, fn ($query) => $query->whereKeyNot($event->actor()->getKey()))
            ->get();

        if ($approvers->isEmpty()) {
            return;
        }

        $order->loadMissing('supplier:id,code,name,short_name');

        Notification::send($approvers, new PurchaseOrderAwaitingApproval($order));
    }
}
