<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasEnumMetadata;

enum SupplierStatus: string
{
    use HasEnumMetadata;

    case ACTIVE = 'ACTIVE';
    case INACTIVE = 'INACTIVE';
    case BLACKLISTED = 'BLACKLISTED';

    public function badgeVariant(): string
    {
        return match ($this) {
            self::ACTIVE => 'success',
            self::INACTIVE => 'neutral',
            self::BLACKLISTED => 'danger',
        };
    }

    /**
     * Only active suppliers may be selected on a new purchase order.
     */
    public function canReceiveOrders(): bool
    {
        return $this === self::ACTIVE;
    }
}
