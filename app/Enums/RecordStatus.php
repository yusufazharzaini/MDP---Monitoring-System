<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasEnumMetadata;

/**
 * Generic active/inactive flag shared by master-data tables.
 */
enum RecordStatus: string
{
    use HasEnumMetadata;

    case ACTIVE = 'ACTIVE';
    case INACTIVE = 'INACTIVE';

    public function badgeVariant(): string
    {
        return $this === self::ACTIVE ? 'success' : 'neutral';
    }
}
