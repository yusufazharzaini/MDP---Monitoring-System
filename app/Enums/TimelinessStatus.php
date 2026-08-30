<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasEnumMetadata;

/**
 * Punctuality of a receipt measured against its scheduled delivery date.
 */
enum TimelinessStatus: string
{
    use HasEnumMetadata;

    case PENDING = 'PENDING';
    case ON_TIME = 'ON_TIME';
    case LATE = 'LATE';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::ON_TIME => 'On Time',
            self::LATE => 'Late',
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::PENDING => 'neutral',
            self::ON_TIME => 'success',
            self::LATE => 'danger',
        };
    }
}
