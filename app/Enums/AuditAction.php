<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasEnumMetadata;

enum AuditAction: string
{
    use HasEnumMetadata;

    case CREATED = 'CREATED';
    case UPDATED = 'UPDATED';
    case DELETED = 'DELETED';
    case RESTORED = 'RESTORED';
    case SUBMITTED = 'SUBMITTED';
    case APPROVED = 'APPROVED';
    case CANCELLED = 'CANCELLED';
    case CLOSED = 'CLOSED';
    case IMPORTED = 'IMPORTED';
    case EXPORTED = 'EXPORTED';
    case LOGIN = 'LOGIN';
    case LOGOUT = 'LOGOUT';

    public function badgeVariant(): string
    {
        return match ($this) {
            self::CREATED, self::APPROVED, self::CLOSED => 'success',
            self::UPDATED, self::SUBMITTED, self::IMPORTED, self::EXPORTED => 'info',
            self::DELETED, self::CANCELLED => 'danger',
            self::RESTORED => 'warning',
            self::LOGIN, self::LOGOUT => 'neutral',
        };
    }
}
