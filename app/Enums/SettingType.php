<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasEnumMetadata;

/**
 * Storage type of a system_settings row, used to cast the stored string back
 * into a usable PHP value.
 */
enum SettingType: string
{
    use HasEnumMetadata;

    case STRING = 'STRING';
    case INTEGER = 'INTEGER';
    case DECIMAL = 'DECIMAL';
    case BOOLEAN = 'BOOLEAN';
    case JSON = 'JSON';

    public function cast(?string $value): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($this) {
            self::STRING => $value,
            self::INTEGER => (int) $value,
            self::DECIMAL => (float) $value,
            self::BOOLEAN => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            self::JSON => json_decode($value, true, 512, JSON_THROW_ON_ERROR),
        };
    }

    public function serialize(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return match ($this) {
            self::JSON => json_encode($value, JSON_THROW_ON_ERROR),
            self::BOOLEAN => $value ? '1' : '0',
            default => (string) $value,
        };
    }
}
