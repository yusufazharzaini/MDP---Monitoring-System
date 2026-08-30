<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasEnumMetadata;

/**
 * Operational lifecycle of a delivery header.
 *
 * Performance verdicts are never stored here - see OverallDeliveryStatus.
 */
enum DeliveryStatus: string
{
    use HasEnumMetadata;

    case PENDING = 'PENDING';
    case RECEIVED = 'RECEIVED';
    case PARTIAL = 'PARTIAL';
    case COMPLETED = 'COMPLETED';
    case CANCELLED = 'CANCELLED';

    public function badgeVariant(): string
    {
        return match ($this) {
            self::PENDING => 'neutral',
            self::RECEIVED, self::COMPLETED => 'success',
            self::PARTIAL => 'warning',
            self::CANCELLED => 'danger',
        };
    }

    /**
     * Cancelled deliveries are excluded from every KPI aggregate.
     */
    public function countsTowardsPerformance(): bool
    {
        return $this !== self::CANCELLED;
    }

    public function isEditable(): bool
    {
        return $this !== self::CANCELLED;
    }
}
