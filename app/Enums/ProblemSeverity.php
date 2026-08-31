<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasEnumMetadata;

enum ProblemSeverity: string
{
    use HasEnumMetadata;

    case LOW = 'LOW';
    case MEDIUM = 'MEDIUM';
    case HIGH = 'HIGH';
    case CRITICAL = 'CRITICAL';

    public function badgeVariant(): string
    {
        return match ($this) {
            self::LOW => 'neutral',
            self::MEDIUM => 'info',
            self::HIGH => 'warning',
            self::CRITICAL => 'danger',
        };
    }

    /**
     * Days allowed to close a problem, used to default the due date.
     */
    public function resolutionDays(): int
    {
        return match ($this) {
            self::LOW => 30,
            self::MEDIUM => 14,
            self::HIGH => 7,
            self::CRITICAL => 3,
        };
    }

    public function weight(): int
    {
        return match ($this) {
            self::LOW => 1,
            self::MEDIUM => 2,
            self::HIGH => 3,
            self::CRITICAL => 4,
        };
    }
}
