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
     * The label an operator reads, in the language they chose.
     *
     * Every case resolves through lang/<locale>/enums.php under its own enum
     * name, so all 19 enums become translatable from this one point. A missing
     * key falls back to defaultLabel() rather than rendering the raw key, which
     * keeps a half-finished translation legible instead of showing
     * "enums.ProblemSeverity.HIGH" on a dashboard.
     */
    public function label(): string
    {
        $key = 'enums.'.class_basename(static::class).'.'.$this->value;
        $translated = __($key);

        return is_string($translated) && $translated !== $key
            ? $translated
            : $this->defaultLabel();
    }

    /**
     * Title-case fallback used when no translation exists for the case.
     */
    public function defaultLabel(): string
    {
        return ucwords(strtolower(str_replace('_', ' ', $this->value)));
    }

    public function badgeVariant(): string
    {
        return 'neutral';
    }
}
