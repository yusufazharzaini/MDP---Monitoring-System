<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasEnumMetadata;

enum ProblemStatus: string
{
    use HasEnumMetadata;

    case OPEN = 'OPEN';
    case IN_PROGRESS = 'IN_PROGRESS';
    case CLOSED = 'CLOSED';
    case CANCELLED = 'CANCELLED';

    public function badgeVariant(): string
    {
        return match ($this) {
            self::OPEN => 'danger',
            self::IN_PROGRESS => 'warning',
            self::CLOSED => 'success',
            self::CANCELLED => 'neutral',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::OPEN, self::IN_PROGRESS], true);
    }
}
