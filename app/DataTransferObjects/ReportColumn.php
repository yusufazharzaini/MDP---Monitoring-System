<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

/**
 * One column of a report: where the value comes from, what to call it, and how
 * it should sit in a cell.
 *
 * Alignment travels with the column so the Excel sheet, the PDF and the on
 * screen preview line their numbers up the same way without each deciding for
 * itself.
 */
final readonly class ReportColumn
{
    private function __construct(
        public string $key,
        public string $label,
        public string $align,
        public bool $numeric,
        public int $decimals,
    ) {}

    public static function text(string $key, string $label): self
    {
        return new self($key, $label, 'left', false, 0);
    }

    /**
     * A measured value - quantity, price, rate - carrying decimals.
     */
    public static function number(string $key, string $label): self
    {
        return new self($key, $label, 'right', true, 2);
    }

    /**
     * A count or a position. Rendering rank 1 as "1,00" reads as a
     * measurement rather than an ordinal, so integers say so.
     */
    public static function integer(string $key, string $label): self
    {
        return new self($key, $label, 'right', true, 0);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'align' => $this->align,
            'numeric' => $this->numeric,
            'decimals' => $this->decimals,
        ];
    }
}
