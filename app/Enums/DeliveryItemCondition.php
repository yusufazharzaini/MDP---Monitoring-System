<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasEnumMetadata;

/**
 * Physical condition of goods recorded at receiving.
 */
enum DeliveryItemCondition: string
{
    use HasEnumMetadata;

    case GOOD = 'GOOD';
    case DAMAGED = 'DAMAGED';
    case REJECTED = 'REJECTED';
    case PARTIAL = 'PARTIAL';

    public function badgeVariant(): string
    {
        return match ($this) {
            self::GOOD => 'success',
            self::DAMAGED => 'warning',
            self::REJECTED => 'danger',
            self::PARTIAL => 'info',
        };
    }

    /**
     * Rejected goods never count towards fulfilled quantity.
     */
    public function countsAsReceived(): bool
    {
        return $this !== self::REJECTED;
    }
}
