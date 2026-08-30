<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasEnumMetadata;

enum CorrectiveActionStatus: string
{
    use HasEnumMetadata;

    case OPEN = 'OPEN';
    case IN_PROGRESS = 'IN_PROGRESS';
    case DONE = 'DONE';

    public function badgeVariant(): string
    {
        return match ($this) {
            self::OPEN => 'danger',
            self::IN_PROGRESS => 'warning',
            self::DONE => 'success',
        };
    }
}
