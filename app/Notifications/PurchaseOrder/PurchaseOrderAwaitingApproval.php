<?php

declare(strict_types=1);

namespace App\Notifications\PurchaseOrder;

use App\Models\Notification as NotificationRecord;
use App\Models\PurchaseOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * An order is waiting for someone who can release it.
 *
 * Sent when a buyer submits, to the people holding `po.approve` - an order
 * sitting unnoticed in SUBMITTED is a delivery that has not been ordered yet.
 */
class PurchaseOrderAwaitingApproval extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly PurchaseOrder $order,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title())
            ->line($this->message())
            ->action('Buka purchase order', url(route('purchase-orders.show', $this->order->ulid, false)));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title(),
            'message' => $this->message(),
            'severity' => NotificationRecord::SEVERITY_INFO,
            'url' => route('purchase-orders.show', $this->order->ulid, false),
        ];
    }

    private function title(): string
    {
        return "Purchase order {$this->order->po_number} menunggu persetujuan";
    }

    private function message(): string
    {
        return sprintf(
            'Diajukan untuk %s senilai Rp %s.',
            $this->order->supplier?->displayName() ?? 'supplier',
            number_format((float) $this->order->total_amount, 0, ',', '.'),
        );
    }
}
