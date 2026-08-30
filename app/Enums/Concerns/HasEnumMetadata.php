<?php

declare(strict_types=1);

namespace App\Enums\Concerns;

/**
 * Shared helpers for the application's backed enums.
 *
 * Every status enum exposes a human label and a badge variant so the UI never
 * has to map a status onto a colour by hand.
 */
trait HasEnumMetadata
{
    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Options payload for select inputs and filter bars.
     *
     * @return array<int, array{value: string, label: string, variant: string}>
     */
    public static function options(): array
    {
        return array_map(
            static fn (self $case): array => [
                'value' => $case->value,
                'label' => $case->label(),
                'variant' => $case->badgeVariant(),
            ],
            self::cases(),
        );
    }

    /**
     * Title-case fallback used by enums without a bespoke label map.
     */
    public function label(): string
    {
        return ucwords(strtolower(str_replace('_', ' ', $this->value)));
    }

    public function badgeVariant(): string
    {
        return 'neutral';
    }
}
