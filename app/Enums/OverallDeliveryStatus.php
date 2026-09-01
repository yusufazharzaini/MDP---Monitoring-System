<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasEnumMetadata;
use App\Services\Delivery\DeliveryStatusCalculator;

/**
 * Combined punctuality + fulfilment verdict for a receipt.
 *
 * @see DeliveryStatusCalculator::overall()
 */
enum OverallDeliveryStatus: string
{
    use HasEnumMetadata;

    case PENDING = 'PENDING';
    case ON_TIME_FULL = 'ON_TIME_FULL';
    case LATE_FULL = 'LATE_FULL';
    case ON_TIME_SHORT = 'ON_TIME_SHORT';
    case LATE_SHORT = 'LATE_SHORT';
    case OVER_DELIVERY = 'OVER_DELIVERY';

    public function defaultLabel(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::ON_TIME_FULL => 'On Time - Full',
            self::LATE_FULL => 'Late - Full',
            self::ON_TIME_SHORT => 'On Time - Short',
            self::LATE_SHORT => 'Late - Short',
            self::OVER_DELIVERY => 'Over Delivery',
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::PENDING => 'neutral',
            self::ON_TIME_FULL => 'success',
            self::LATE_FULL, self::LATE_SHORT => 'danger',
            self::ON_TIME_SHORT => 'warning',
            self::OVER_DELIVERY => 'info',
        };
    }

    public function timeliness(): TimelinessStatus
    {
        return match ($this) {
            self::PENDING, self::OVER_DELIVERY => TimelinessStatus::PENDING,
            self::ON_TIME_FULL, self::ON_TIME_SHORT => TimelinessStatus::ON_TIME,
            self::LATE_FULL, self::LATE_SHORT => TimelinessStatus::LATE,
        };
    }
}
