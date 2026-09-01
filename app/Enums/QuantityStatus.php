<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasEnumMetadata;

/**
 * Fulfilment of a receipt measured against the ordered quantity.
 */
enum QuantityStatus: string
{
    use HasEnumMetadata;

    case PENDING = 'PENDING';
    case SHORT = 'SHORT';
    case FULL = 'FULL';
    case OVER = 'OVER';

    public function defaultLabel(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::SHORT => 'Short',
            self::FULL => 'Full',
            self::OVER => 'Over',
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::PENDING => 'neutral',
            self::SHORT => 'warning',
            self::FULL => 'success',
            self::OVER => 'info',
        };
    }
}
