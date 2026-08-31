<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasEnumMetadata;

/**
 * How exposed the business is on a given material inside a period.
 *
 * Derived by CriticalMaterialService from how many of the configured
 * critical-material rules a material trips, weighted by severity.
 */
enum RiskLevel: string
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

    public function weight(): int
    {
        return match ($this) {
            self::LOW => 1,
            self::MEDIUM => 2,
            self::HIGH => 3,
            self::CRITICAL => 4,
        };
    }

    /**
     * Band a risk score (0 and up) into a level.
     */
    public static function fromScore(int $score): self
    {
        return match (true) {
            $score >= 6 => self::CRITICAL,
            $score >= 4 => self::HIGH,
            $score >= 2 => self::MEDIUM,
            default => self::LOW,
        };
    }
}
