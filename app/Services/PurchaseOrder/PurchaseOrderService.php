<?php

declare(strict_types=1);

namespace App\Services\PurchaseOrder;

use App\Actions\PurchaseOrder\RecalculatePurchaseOrderTotal;
use App\Actions\PurchaseOrder\SyncPurchaseOrderItems;
use App\Enums\AuditAction;
use App\Enums\PurchaseOrderStatus;
use App\Events\PurchaseOrder\PurchaseOrderApproved;
use App\Events\PurchaseOrder\PurchaseOrderCancelled;
use App\Events\PurchaseOrder\PurchaseOrderSubmitted;
use App\Exceptions\BusinessRuleException;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use App\Services\Setting\SystemSettingService;
use App\Services\Support\NumberGeneratorService;
use Illuminate\Support\Facades\DB;

/**
 * The purchase order lifecycle.
 *
 *   DRAFT --submit--> SUBMITTED --approve--> APPROVED --(receipts)--> PARTIAL --> COMPLETED
 *      \                  \                      \
 *       `----------------- cancel ----------------'--> CANCELLED
 *
 * Every transition is guarded here rather than in the controller, so an order
 * cannot be approved twice, cancelled after completion, or edited once the
 * supplier is already working to it - however it is reached.
 */
class PurchaseOrderService
{
    public function __construct(
        private readonly SyncPurchaseOrderItems $syncItems,
        private readonly RecalculatePurchaseOrderTotal $recalculateTotal,
        private readonly NumberGeneratorService $numbers,
        private readonly AuditLogService $audit,
        private readonly SystemSettingService $settings,
    ) {}

    /**
     * Raise a new order, always as a draft. Numbers are allocated here so a
     * saved order always has one.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<int, array<string, mixed>>  $lines
     */
    public function create(array $attributes, array $lines, ?User $actor = null): PurchaseOrder
    {
        $this->guardSupplierIsOrderable((int) $attributes['supplier_id']);

        return DB::transaction(function () use ($attributes, $lines, $actor): PurchaseOrder {
            $order = new PurchaseOrder;
            $order->fill($attributes);
            $order->forceFill([
                'po_number' => $this->numbers->purchaseOrderNumber(),
                'status' => PurchaseOrderStatus::DRAFT,
                'created_by' => $actor?->getKey(),
            ])->save();

            ($this->syncItems)($order, $lines);
            ($this->recalculateTotal)($order);

            $this->audit->record(
                AuditAction::CREATED,
                'PurchaseOrder',
                $order->getKey(),
                null,
                ['po_number' => $order->po_number, 'status' => $order->status->value],
            );

            return $order->refresh();
        });
    }

    /**
     * Amend a draft or submitted order.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<int, array<string, mixed>>  $lines
     */
    public function update(PurchaseOrder $order, array $attributes, array $lines): PurchaseOrder
    {
        $this->guardEditable($order);
        $this->guardSupplierIsOrderable((int) ($attributes['supplier_id'] ?? $order->supplier_id));

        return DB::transaction(function () use ($order, $attributes, $lines): PurchaseOrder {
            $order->fill($attributes);

            if ($order->isDirty()) {
                $this->audit->recordModelChange(AuditAction::UPDATED, $order);
                $order->save();
            }

            ($this->syncItems)($order, $lines);
            ($this->recalculateTotal)($order);

            return $order->refresh();
        });
    }

    /**
     * Send a draft for approval. An order with no lines is not an order.
     */
    public function submit(PurchaseOrder $order, ?User $actor = null): PurchaseOrder
    {
        if ($order->status !== PurchaseOrderStatus::DRAFT) {
            throw new BusinessRuleException(
                "Purchase order {$order->po_number} hanya dapat diajukan dari status DRAFT."
            );
        }

        if ($order->items()->doesntExist()) {
            throw new BusinessRuleException(
                "Purchase order {$order->po_number} belum memiliki baris item."
            );
        }

        return DB::transaction(function () use ($order, $actor): PurchaseOrder {
            $order->forceFill(['status' => PurchaseOrderStatus::SUBMITTED])->save();

            PurchaseOrderSubmitted::dispatch($order, $actor);

            return $order;
        });
    }

    /**
     * Release an order to the supplier. From here deliveries may be received
     * against it, so this is the point the commitment becomes real.
     */
    public function approve(PurchaseOrder $order, User $approver): PurchaseOrder
    {
        if ($order->status !== PurchaseOrderStatus::SUBMITTED) {
            throw new BusinessRuleException(
                "Purchase order {$order->po_number} hanya dapat disetujui dari status SUBMITTED."
            );
        }

        $this->guardSeparationOfDuties($order, $approver);

        return DB::transaction(function () use ($order, $approver): PurchaseOrder {
            $order->forceFill([
                'status' => PurchaseOrderStatus::APPROVED,
                'approved_by' => $approver->getKey(),
                'approved_at' => now(),
            ])->save();

            PurchaseOrderApproved::dispatch($order, $approver);

            return $order;
        });
    }

    /**
     * Stop an order. Receipts already booked against it stay - cancelling ends
     * what is still to come, it does not unmake what already arrived.
     */
    public function cancel(PurchaseOrder $order, ?User $actor = null, ?string $reason = null): PurchaseOrder
    {
        if ($order->status->isFinal()) {
            throw new BusinessRuleException(
                "Purchase order {$order->po_number} berstatus {$order->status->label()} dan tidak dapat dibatalkan."
            );
        }

        return DB::transaction(function () use ($order, $actor, $reason): PurchaseOrder {
            $order->forceFill([
                'status' => PurchaseOrderStatus::CANCELLED,
                'remarks' => $reason ?? $order->remarks,
            ])->save();

            PurchaseOrderCancelled::dispatch($order, $actor, $reason);

            return $order;
        });
    }

    private function guardEditable(PurchaseOrder $order): void
    {
        if (! $order->status->isEditable()) {
            throw new BusinessRuleException(
                "Purchase order {$order->po_number} berstatus {$order->status->label()} dan tidak dapat diubah."
            );
        }
    }

    private function guardSupplierIsOrderable(int $supplierId): void
    {
        $supplier = Supplier::query()->findOrFail($supplierId);

        if (! $supplier->status->canReceiveOrders()) {
            throw new BusinessRuleException(
                "Supplier {$supplier->code} berstatus {$supplier->status->label()} "
                .'dan tidak dapat menerima purchase order baru.'
            );
        }
    }

    /**
     * Whoever raised the order should not be the one releasing it. This is a
     * standard purchasing control, and it is switchable because a small team
     * may legitimately have one person doing both.
     */
    private function guardSeparationOfDuties(PurchaseOrder $order, User $approver): void
    {
        $required = $this->settings->bool(SystemSettingService::PO_REQUIRE_SEPARATE_APPROVER, true);

        if ($required && $order->created_by !== null && $order->created_by === $approver->getKey()) {
            throw new BusinessRuleException(
                'Purchase order tidak dapat disetujui oleh pembuatnya sendiri. '
                .'Aturan ini dapat dinonaktifkan pada System Settings.'
            );
        }
    }
}
