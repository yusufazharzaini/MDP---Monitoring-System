<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasEnumMetadata;

/**
 * Supplier rating band.
 *
 * The score boundaries are NOT defined here - they are read from kpi_settings
 * by SupplierGradeResolver so the business can retune them without a deploy.
 */
enum SupplierGrade: string
{
    use HasEnumMetadata;

    case EXCELLENT = 'EXCELLENT';
    case GOOD = 'GOOD';
    case AVERAGE = 'AVERAGE';
    case POOR = 'POOR';

    public function badgeVariant(): string
    {
        return match ($this) {
            self::EXCELLENT, self::GOOD => 'success',
            self::AVERAGE => 'warning',
            self::POOR => 'danger',
        };
    }

    /**
     * kpi_settings code holding this grade's lower bound.
     */
    public function thresholdCode(): ?string
    {
        return match ($this) {
            self::EXCELLENT => 'GRADE_EXCELLENT',
            self::GOOD => 'GRADE_GOOD',
            self::AVERAGE => 'GRADE_AVERAGE',
            self::POOR => null,
        };
    }
}
