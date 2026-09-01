<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasEnumMetadata;

/**
 * Whether a monthly scorecard is still being worked on or has been signed off.
 *
 * The distinction is what makes an evaluation a record rather than a cache: a
 * DRAFT is recomputed from transactions whenever anyone asks, an APPROVED one
 * is frozen, so a later data correction moves today's dashboard without quietly
 * rewriting the figures a manager put their name to.
 */
enum EvaluationStatus: string
{
    use HasEnumMetadata;

    case DRAFT = 'DRAFT';
    case APPROVED = 'APPROVED';

    public function badgeVariant(): string
    {
        return match ($this) {
            self::DRAFT => 'warning',
            self::APPROVED => 'success',
        };
    }

    public function isApproved(): bool
    {
        return $this === self::APPROVED;
    }
}
