<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasEnumMetadata;

/**
 * Purchase order lifecycle.
 *
 * DRAFT -> SUBMITTED -> APPROVED -> PARTIAL -> COMPLETED, with CANCELLED
 * reachable from any pre-completion state.
 */
enum PurchaseOrderStatus: string
{
    use HasEnumMetadata;

    case DRAFT = 'DRAFT';
    case SUBMITTED = 'SUBMITTED';
    case APPROVED = 'APPROVED';
    case PARTIAL = 'PARTIAL';
    case COMPLETED = 'COMPLETED';
    case CANCELLED = 'CANCELLED';

    public function badgeVariant(): string
    {
        return match ($this) {
            self::DRAFT => 'neutral',
            self::SUBMITTED => 'info',
            self::APPROVED, self::COMPLETED => 'success',
            self::PARTIAL => 'warning',
            self::CANCELLED => 'danger',
        };
    }

    /**
     * Header and line items may only change while the PO is not yet approved.
     */
    public function isEditable(): bool
    {
        return in_array($this, [self::DRAFT, self::SUBMITTED], true);
    }

    /**
     * Goods may only be received against a live, approved order.
     */
    public function acceptsDeliveries(): bool
    {
        return in_array($this, [self::APPROVED, self::PARTIAL], true);
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::COMPLETED, self::CANCELLED], true);
    }
}
